<?php

namespace App\Services;

use App\Exceptions\ActaIlegibleException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Lee las actas de notas en PDF (formato institucional fijo, sin OCR).
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
}
