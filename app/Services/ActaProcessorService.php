<?php

namespace App\Services;

use App\Exceptions\ActaIlegibleException;
use App\Exceptions\PlanillaInvalidaException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Lee las actas de notas en PDF (formato institucional fijo, sin OCR) y las cruza con
 * la planilla de seguimiento.
 *
 * Parte del prototipo. Cambios respecto de él:
 *  - separa la lectura del PDF (parsePdfActa) del análisis del texto (parseTexto), que
 *    se puede probar sin un PDF;
 *  - limpia el legajo quitando todo lo que no sea un dígito (puntos y espacios invisibles);
 *  - devuelve las líneas con número de orden y legajo que no cumplen el formato, en lugar
 *    de ignorarlas en silencio (M1-04);
 *  - guarda el rango de folios de un acta de varias páginas ("404-406");
 *  - cruza el total de inscriptos que trae el acta con las líneas identificadas.
 *
 * Las filas extraídas no se guardan en la base ni se escriben en logs.
 */
class ActaProcessorService
{
    /** Línea que empieza con número de orden y legajo (con o sin puntos de miles). */
    private const LINEA_CANDIDATA = '/^\d{1,3} (?:\d{1,3}(?:\.\d{3})+|\d{4,7})(?: |$)/';

    /** Orden, legajo, apellido y nombre, nota numérica y nota en letras. */
    private const LINEA_ALUMNO = '/^(\d{1,3}) (\d{1,3}(?:\.\d{3})+|\d{4,7}) (.+) (\d{1,2}) (\p{L}+)$/u';

    /** Nota numérica y su escritura en letras. Si no coinciden, la línea queda sin leer. */
    private const NOTAS_EN_LETRAS = [
        1 => 'uno', 2 => 'dos', 3 => 'tres', 4 => 'cuatro', 5 => 'cinco',
        6 => 'seis', 7 => 'siete', 8 => 'ocho', 9 => 'nueve', 10 => 'diez',
    ];

    /**
     * Lee un PDF y devuelve los datos del acta.
     *
     * @return array<string, mixed> ver parseTexto()
     *
     * @throws ActaIlegibleException si el archivo no se puede leer, no tiene texto o no
     *                               se identifica ningún alumno (M1-11)
     */
    public function parsePdfActa(string $ruta): array
    {
        $datos = $this->parseTexto($this->extraerTexto($ruta));

        if ($datos['alumnos'] === []) {
            throw new ActaIlegibleException(
                'No se pudo leer ningún alumno del acta. Verificá que sea un acta con el formato institucional.'
            );
        }

        return $datos;
    }

    /**
     * Interpreta el texto ya extraído del acta.
     *
     * @return array{
     *     materia: ?string,
     *     materias: array<int, string>,
     *     tomo: ?string,
     *     folio: ?string,
     *     folios: array<int, int>,
     *     alumnos: array<int, array{orden: int, legajo: string, nombre: string, nota_num: int, nota_letras: string, formato_excel: string}>,
     *     total_alumnos: int,
     *     lineas_no_leidas: array<int, string>,
     *     legajos_repetidos: array<int, string>,
     *     totales: ?array{inscriptos: int, examinados: int, aprobados: int, ausentes: int, no_aprobados: int},
     *     control: array{verificado: bool, coincide: ?bool, mensaje: string},
     *     advertencias: array<int, string>
     * }
     */
    public function parseTexto(string $texto): array
    {
        $texto = mb_scrub(str_replace("\u{00A0}", ' ', $texto));

        $alumnos = [];
        $noLeidas = [];

        foreach (preg_split('/\R/u', $texto) ?: [] as $linea) {
            // Espacios y tabulaciones se unifican para no depender del diseño del PDF.
            $limpia = trim((string) preg_replace('/\s+/u', ' ', str_replace('|', ' ', $linea)));

            if (! preg_match(self::LINEA_CANDIDATA, $limpia)) {
                continue;
            }

            $alumno = $this->leerAlumno($limpia);

            if ($alumno === null) {
                $noLeidas[] = $limpia;
            } else {
                $alumnos[] = $alumno;
            }
        }

        $materias = $this->valoresUnicos('/Asignatura:\s*(?:\([^)]*\))?\s*([^\r\n]+)/iu', $texto);
        $tomos = $this->valoresUnicos('/Tomo\s*:\s*(\d+)/i', $texto);
        $folios = array_map('intval', $this->valoresUnicos('/Folio\s*:\s*(\d+)/i', $texto));
        sort($folios);

        $advertencias = [];
        if (count($materias) > 1) {
            $advertencias[] = 'El PDF contiene actas de más de una materia: '.implode(' / ', $materias).'.';
        }
        if (count($tomos) > 1) {
            $advertencias[] = 'El PDF contiene actas de más de un tomo: '.implode(', ', $tomos).'.';
        }

        $totales = $this->leerTotales($texto);

        return [
            'materia' => $materias[0] ?? null,
            'materias' => $materias,
            'tomo' => $tomos[0] ?? null,
            'folio' => $this->etiquetaFolios($folios),
            'folios' => $folios,
            'alumnos' => $alumnos,
            'total_alumnos' => count($alumnos),
            'lineas_no_leidas' => $noLeidas,
            'legajos_repetidos' => $this->legajosRepetidos($alumnos),
            'totales' => $totales,
            'control' => $this->controlarTotales($totales, count($alumnos) + count($noLeidas)),
            'advertencias' => $advertencias,
        ];
    }

    /**
     * Deja solo los dígitos de un legajo: "29.097", " 29097" o "\u{00A0}29076" dan el
     * mismo valor. Se usa para el acta y para la planilla.
     */
    public static function normalizarLegajo(mixed $valor): string
    {
        return (string) preg_replace('/\D+/', '', (string) $valor);
    }

    /**
     * Simula la carga (M1-05) sin escribir nada: ubica la columna de la materia en cada
     * pestaña, busca cada legajo en todas las pestañas y clasifica el resultado.
     *
     * Reglas:
     *  - la materia se busca en las filas de config('actas.filas_materia') de cada pestaña;
     *    una coincidencia exacta gana sobre una por palabra completa ("prog i" no coincide
     *    con "prog iii");
     *  - cada alumno se carga en la primera pestaña (la más reciente) donde aparece y que
     *    tiene columna para la materia; si aparece en más de una se informa en
     *    legajos_repetidos;
     *  - una celda vacía se carga, una con la misma nota queda sin cambios y una con otra
     *    cosa (otra nota, "AR=2/3") es un conflicto que decide la preceptora;
     *  - el cruce es siempre por legajo; si el nombre difiere solo se informa.
     *
     * @param  array<string, mixed>  $datosActa resultado de parsePdfActa() / parseTexto()
     * @return array<string, mixed> columnas, cargas, sin_cambios, conflictos, no_encontrados,
     *                              sin_columna, legajos_repetidos, diferencias_nombre,
     *                              hojas_sin_columna, advertencias y resumen
     *
     * @throws ActaIlegibleException si el acta no trae la materia
     * @throws PlanillaInvalidaException si la planilla está dañada, no tiene la estructura
     *                                   esperada o ninguna columna coincide con la materia
     */
    public function analizar(string $rutaExcel, array $datosActa): array
    {
        if (trim((string) ($datosActa['materia'] ?? '')) === '') {
            throw new ActaIlegibleException('No se pudo identificar la materia del acta (falta la línea «Asignatura:»).');
        }

        return $this->planificar($this->abrirPlanilla($rutaExcel, soloLectura: true), $datosActa);
    }

    /**
     * Minúsculas, sin tildes ni signos, con las abreviaturas de config('actas.abreviaturas').
     * Sirve para comparar el nombre de una materia del acta con el encabezado de la planilla.
     */
    public static function normalizarTexto(string $texto): string
    {
        $texto = self::limpiarTexto($texto);

        $abreviaturas = (array) config('actas.abreviaturas', []);
        uksort($abreviaturas, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        foreach ($abreviaturas as $largo => $corto) {
            $texto = (string) preg_replace('/\b'.preg_quote((string) $largo, '/').'\b/', (string) $corto, $texto);
        }

        return trim((string) preg_replace('/\s+/', ' ', $texto));
    }

    /** Para comparar nombres de personas: minúsculas, sin tildes ni signos. */
    public static function normalizarNombre(string $nombre): string
    {
        return self::limpiarTexto($nombre);
    }

    // ------------------------------------------------------------------ Privados

    private function extraerTexto(string $ruta): string
    {
        if (! is_file($ruta) || ! is_readable($ruta)) {
            throw new ActaIlegibleException('No se encontró el archivo del acta.');
        }

        try {
            $config = new Config;
            $config->setRetainImageContent(false);

            $texto = (new Parser([], $config))->parseFile($ruta)->getText();
        } catch (Throwable) {
            throw new ActaIlegibleException('No se pudo leer el PDF: el archivo está dañado o no es un PDF válido.');
        }

        if (trim($texto) === '') {
            throw new ActaIlegibleException(
                'El PDF no contiene texto. Si es un acta escaneada como imagen, Eleven no puede leerla (no hay reconocimiento de texto).'
            );
        }

        return $texto;
    }

    /** @return array{orden: int, legajo: string, nombre: string, nota_num: int, nota_letras: string, formato_excel: string}|null */
    private function leerAlumno(string $linea): ?array
    {
        if (! preg_match(self::LINEA_ALUMNO, $linea, $m)) {
            return null;
        }

        $nota = (int) $m[4];
        $letras = mb_strtolower($m[5], 'UTF-8');

        if ((self::NOTAS_EN_LETRAS[$nota] ?? null) !== $letras) {
            return null;
        }

        return [
            'orden' => (int) $m[1],
            'legajo' => self::normalizarLegajo($m[2]),
            'nombre' => trim($m[3]),
            'nota_num' => $nota,
            'nota_letras' => $letras,
            'formato_excel' => str_replace(
                ['{nota}', '{letras}'],
                [(string) $nota, $letras],
                (string) config('actas.formato_nota', '{nota} ({letras})'),
            ),
        ];
    }

    /** @return array<int, string> valores distintos, en orden de aparición, sin espacios sobrantes */
    private function valoresUnicos(string $patron, string $texto): array
    {
        preg_match_all($patron, $texto, $coincidencias);

        $valores = array_map(
            fn (string $v) => trim((string) preg_replace('/\s+/u', ' ', $v)),
            $coincidencias[1] ?? [],
        );

        return array_values(array_unique(array_filter($valores, fn (string $v) => $v !== '')));
    }

    /** "404", "404-406" si son consecutivos, o "404, 406" si hay saltos. */
    private function etiquetaFolios(array $folios): ?string
    {
        if ($folios === []) {
            return null;
        }

        $primero = $folios[0];
        $ultimo = $folios[array_key_last($folios)];

        if (count($folios) === 1) {
            return (string) $primero;
        }

        return $ultimo - $primero + 1 === count($folios)
            ? "{$primero}-{$ultimo}"
            : implode(', ', $folios);
    }

    /**
     * Suma el resumen que cada folio trae al pie ("INSCRIPTOS:20 EXAMINADOS:20 ...").
     *
     * @return array{inscriptos: int, examinados: int, aprobados: int, ausentes: int, no_aprobados: int}|null
     */
    private function leerTotales(string $texto): ?array
    {
        $patron = '/INSCRIPTOS:\s*(\d+)\s+EXAMINADOS:\s*(\d+)\s+APROBADOS:\s*(\d+)\s+AUSENTES:\s*(\d+)\s+NO\s+APROBADOS:\s*(\d+)/i';

        if (! preg_match_all($patron, $texto, $filas, PREG_SET_ORDER)) {
            return null;
        }

        $totales = ['inscriptos' => 0, 'examinados' => 0, 'aprobados' => 0, 'ausentes' => 0, 'no_aprobados' => 0];

        foreach ($filas as $fila) {
            $totales['inscriptos'] += (int) $fila[1];
            $totales['examinados'] += (int) $fila[2];
            $totales['aprobados'] += (int) $fila[3];
            $totales['ausentes'] += (int) $fila[4];
            $totales['no_aprobados'] += (int) $fila[5];
        }

        return $totales;
    }

    /**
     * Compara los inscriptos que declara el acta con las líneas identificadas (leídas
     * más no leídas). Si no coinciden, puede faltar algún alumno.
     *
     * @return array{verificado: bool, coincide: ?bool, mensaje: string}
     */
    private function controlarTotales(?array $totales, int $lineasIdentificadas): array
    {
        if ($totales === null) {
            return [
                'verificado' => false,
                'coincide' => null,
                'mensaje' => 'El acta no trae el resumen de totales: no se pudo verificar la lectura.',
            ];
        }

        $coincide = $totales['inscriptos'] === $lineasIdentificadas;

        return [
            'verificado' => true,
            'coincide' => $coincide,
            'mensaje' => $coincide
                ? "El acta indica {$totales['inscriptos']} inscriptos y se identificaron {$lineasIdentificadas} líneas."
                : "El acta indica {$totales['inscriptos']} inscriptos pero se identificaron {$lineasIdentificadas} líneas: revisá que no falte ningún alumno.",
        ];
    }

    /** @return array<int, string> */
    private function legajosRepetidos(array $alumnos): array
    {
        $cuenta = array_count_values(array_column($alumnos, 'legajo'));

        return array_map('strval', array_keys(array_filter($cuenta, fn (int $n) => $n > 1)));
    }

    private static function limpiarTexto(string $texto): string
    {
        $texto = mb_strtolower(str_replace("\u{00A0}", ' ', $texto), 'UTF-8');
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $texto = str_replace('.', '', $texto);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $texto));
    }

    private function abrirPlanilla(string $ruta, bool $soloLectura): Spreadsheet
    {
        if (! is_file($ruta) || ! is_readable($ruta)) {
            throw new PlanillaInvalidaException('No se encontró el archivo de la planilla.');
        }

        try {
            $lector = IOFactory::createReader('Xlsx');
            $lector->setReadDataOnly($soloLectura);

            return $lector->load($ruta);
        } catch (Throwable) {
            throw new PlanillaInvalidaException('No se pudo leer la planilla: el archivo está dañado o no es un .xlsx válido.');
        }
    }

    /**
     * Arma el plan de carga: es la base de analizar() y de la carga real, para que lo
     * que se muestra en la vista previa sea exactamente lo que se escribe.
     *
     * @return array<string, mixed>
     */
    private function planificar(Spreadsheet $libro, array $datosActa): array
    {
        $materiaNorm = self::normalizarTexto((string) $datosActa['materia']);
        $columnaLegajo = (int) config('actas.columna_legajo');
        $filaInicial = (int) config('actas.fila_inicial');
        $columnaNombre = config('actas.columna_nombre');

        $hojas = [];
        $indice = [];
        $totalLegajos = 0;
        $advertencias = [];

        // Se lee cada pestaña una sola vez, en el orden del libro (de la más reciente a la más antigua).
        foreach ($libro->getAllSheets() as $hoja) {
            $titulo = $hoja->getTitle();
            $filas = $hoja->toArray(null, false, false, false);
            [$columna, $ambigua] = $this->buscarColumnaMateria($filas, $materiaNorm);

            if ($ambigua) {
                $advertencias[] = "En la pestaña {$titulo} hay más de una columna posible para la materia; se usó la más específica.";
            }

            for ($i = $filaInicial - 1; $i < count($filas); $i++) {
                $legajo = self::normalizarLegajo($filas[$i][$columnaLegajo - 1] ?? null);

                if ($legajo !== '') {
                    $indice[$legajo][] = ['hoja' => $titulo, 'fila' => $i + 1];
                    $totalLegajos++;
                }
            }

            $hojas[$titulo] = ['filas' => $filas, 'columna' => $columna];
        }

        if ($totalLegajos === 0) {
            $letra = Coordinate::stringFromColumnIndex($columnaLegajo);

            throw new PlanillaInvalidaException(
                "La planilla no tiene la estructura esperada: no se encontraron legajos en la columna {$letra} a partir de la fila {$filaInicial}."
            );
        }

        $columnas = [];
        foreach ($hojas as $titulo => $info) {
            if ($info['columna'] !== null) {
                $columnas[$titulo] = Coordinate::stringFromColumnIndex($info['columna']);
            }
        }

        if ($columnas === []) {
            $filasTexto = implode(' y ', (array) config('actas.filas_materia'));

            throw new PlanillaInvalidaException(
                "La materia «{$datosActa['materia']}» no coincide con ninguna columna de la planilla (se buscó en las filas {$filasTexto} de cada pestaña)."
            );
        }

        $plan = [
            'cargas' => [],
            'sin_cambios' => [],
            'conflictos' => [],
            'no_encontrados' => [],
            'sin_columna' => [],
            'legajos_repetidos' => [],
            'diferencias_nombre' => [],
        ];
        $vistos = [];

        foreach ($datosActa['alumnos'] as $alumno) {
            $legajo = (string) $alumno['legajo'];

            if (isset($vistos[$legajo])) {
                $advertencias[] = "El legajo {$legajo} figura más de una vez en el acta; se usó la primera aparición.";

                continue;
            }
            $vistos[$legajo] = true;

            $ubicaciones = $indice[$legajo] ?? [];

            if ($ubicaciones === []) {
                $plan['no_encontrados'][] = [
                    'legajo' => $legajo,
                    'nombre' => $alumno['nombre'],
                    'nota' => $alumno['formato_excel'],
                ];

                continue;
            }

            if (count($ubicaciones) > 1) {
                $plan['legajos_repetidos'][] = [
                    'legajo' => $legajo,
                    'hojas' => array_values(array_unique(array_column($ubicaciones, 'hoja'))),
                    'veces' => count($ubicaciones),
                ];
            }

            // Primera ubicación cuya pestaña tiene columna para la materia.
            $elegida = null;
            foreach ($ubicaciones as $ubicacion) {
                if ($hojas[$ubicacion['hoja']]['columna'] !== null) {
                    $elegida = $ubicacion;
                    break;
                }
            }

            if ($elegida === null) {
                $plan['sin_columna'][] = [
                    'legajo' => $legajo,
                    'nombre' => $alumno['nombre'],
                    'nota' => $alumno['formato_excel'],
                    'hoja' => $ubicaciones[0]['hoja'],
                ];

                continue;
            }

            $info = $hojas[$elegida['hoja']];
            $letra = Coordinate::stringFromColumnIndex($info['columna']);
            $actual = $info['filas'][$elegida['fila'] - 1][$info['columna'] - 1] ?? null;
            $nombrePlanilla = $columnaNombre
                ? trim((string) ($info['filas'][$elegida['fila'] - 1][(int) $columnaNombre - 1] ?? ''))
                : '';

            $item = [
                'legajo' => $legajo,
                'nombre_acta' => $alumno['nombre'],
                'nombre_planilla' => $nombrePlanilla,
                'nota' => $alumno['formato_excel'],
                'hoja' => $elegida['hoja'],
                'fila' => $elegida['fila'],
                'columna' => $letra,
                'celda' => $letra.$elegida['fila'],
            ];

            if ($nombrePlanilla !== '' && self::normalizarNombre($alumno['nombre']) !== self::normalizarNombre($nombrePlanilla)) {
                $plan['diferencias_nombre'][] = [
                    'legajo' => $legajo,
                    'nombre_acta' => $alumno['nombre'],
                    'nombre_planilla' => $nombrePlanilla,
                    'hoja' => $elegida['hoja'],
                ];
            }

            $textoActual = trim((string) $actual);

            if ($textoActual === '') {
                $plan['cargas'][] = $item;
            } elseif (self::normalizarTexto($textoActual) === self::normalizarTexto($item['nota'])) {
                $plan['sin_cambios'][] = $item + ['actual' => $textoActual];
            } else {
                $plan['conflictos'][] = $item + ['actual' => $textoActual];
            }
        }

        return [
            'materia' => $datosActa['materia'],
            'columnas' => $columnas,
            'hojas_sin_columna' => array_values(array_map('strval', array_diff(array_keys($hojas), array_keys($columnas)))),
            'cargas' => $plan['cargas'],
            'sin_cambios' => $plan['sin_cambios'],
            'conflictos' => $plan['conflictos'],
            'no_encontrados' => $plan['no_encontrados'],
            'sin_columna' => $plan['sin_columna'],
            'legajos_repetidos' => $plan['legajos_repetidos'],
            'diferencias_nombre' => $plan['diferencias_nombre'],
            'advertencias' => $advertencias,
            'resumen' => [
                'alumnos_acta' => count($datosActa['alumnos']),
                'a_cargar' => count($plan['cargas']),
                'sin_cambios' => count($plan['sin_cambios']),
                'conflictos' => count($plan['conflictos']),
                'no_encontrados' => count($plan['no_encontrados']),
                'sin_columna' => count($plan['sin_columna']),
            ],
        ];
    }

    /**
     * Busca en las filas de encabezado la columna de la materia. Gana la coincidencia exacta
     * sobre la de palabra completa y, a igual puntaje, el encabezado más largo (más específico).
     *
     * @return array{0: ?int, 1: bool} columna (base 1) y si hubo empate entre columnas distintas
     */
    private function buscarColumnaMateria(array $filas, string $materiaNorm): array
    {
        $mejor = null;
        $empates = false;

        foreach ((array) config('actas.filas_materia') as $filaExcel) {
            foreach ($filas[$filaExcel - 1] ?? [] as $i => $valor) {
                if (! is_scalar($valor)) {
                    continue;
                }

                $encabezado = self::normalizarTexto((string) $valor);
                $puntaje = $this->puntajeCoincidencia($materiaNorm, $encabezado);

                if ($puntaje === 0) {
                    continue;
                }

                $candidato = [$puntaje, mb_strlen($encabezado), $i + 1];

                if ($mejor === null || $candidato[0] > $mejor[0] || ($candidato[0] === $mejor[0] && $candidato[1] > $mejor[1])) {
                    $mejor = $candidato;
                    $empates = false;
                } elseif ($candidato[0] === $mejor[0] && $candidato[1] === $mejor[1] && $candidato[2] !== $mejor[2]) {
                    $empates = true;
                }
            }
        }

        return [$mejor[2] ?? null, $empates];
    }

    /** 2 si son iguales, 1 si el encabezado aparece como palabra completa en la materia, 0 si no. */
    private function puntajeCoincidencia(string $materiaNorm, string $encabezadoNorm): int
    {
        if ($encabezadoNorm === '') {
            return 0;
        }

        if ($materiaNorm === $encabezadoNorm) {
            return 2;
        }

        return preg_match('/\b'.preg_quote($encabezadoNorm, '/').'\b/', $materiaNorm) === 1 ? 1 : 0;
    }
}
