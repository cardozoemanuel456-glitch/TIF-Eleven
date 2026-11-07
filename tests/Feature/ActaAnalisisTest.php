<?php

namespace Tests\Feature;

use App\Exceptions\ActaIlegibleException;
use App\Exceptions\PlanillaInvalidaException;
use App\Services\ActaProcessorService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Análisis en seco (sin escribir) del acta contra la planilla de seguimiento.
 * Los archivos de tests/Fixtures son simulados; el resto de las planillas se arman acá.
 * No usa base de datos.
 */
class ActaAnalisisTest extends TestCase
{
    /** @var array<int, string> */
    private array $temporales = [];

    protected function tearDown(): void
    {
        foreach ($this->temporales as $ruta) {
            @unlink($ruta);
        }

        parent::tearDown();
    }

    private function servicio(): ActaProcessorService
    {
        return app(ActaProcessorService::class);
    }

    private function fixture(string $nombre): string
    {
        return base_path('tests/Fixtures/'.$nombre);
    }

    /**
     * Arma un .xlsx temporal. Cada pestaña: 'fila3' y 'fila4' (columna => texto) y 'filas'
     * (a partir de la fila 5, columna => valor).
     *
     * @param  array<string, array<string, mixed>>  $hojas
     */
    private function planilla(array $hojas): string
    {
        $libro = new Spreadsheet;
        $primera = true;

        foreach ($hojas as $titulo => $definicion) {
            $hoja = $primera ? $libro->getActiveSheet() : $libro->createSheet();
            $primera = false;
            $hoja->setTitle((string) $titulo);

            foreach ($definicion['fila3'] ?? [] as $columna => $valor) {
                $hoja->setCellValue($columna.'3', $valor);
            }
            foreach ($definicion['fila4'] ?? [] as $columna => $valor) {
                $hoja->setCellValue($columna.'4', $valor);
            }

            $fila = 5;
            foreach ($definicion['filas'] ?? [] as $celdas) {
                foreach ($celdas as $columna => $valor) {
                    if ($valor !== null) {
                        $hoja->setCellValue($columna.$fila, $valor);
                    }
                }
                $fila++;
            }
        }

        $base = tempnam(sys_get_temp_dir(), 'plan');
        $ruta = $base.'.xlsx';
        @unlink($base);
        (new Xlsx($libro))->save($ruta);
        $this->temporales[] = $ruta;

        return $ruta;
    }

    /**
     * Arma los datos del acta pasando por el lector real.
     *
     * @param  array<int, array{0: string, 1: string, 2: int, 3: string}>  $alumnos legajo, nombre, nota, letras
     */
    private function acta(?string $materia, array $alumnos): array
    {
        $lineas = $materia === null ? [] : ["Asignatura: (1-2-3) {$materia}"];

        foreach ($alumnos as $i => [$legajo, $nombre, $nota, $letras]) {
            $lineas[] = ($i + 1)." {$legajo} {$nombre} {$nota} {$letras}";
        }

        return $this->servicio()->parseTexto(implode("\n", $lineas));
    }

    private const GDS = 'GESTION DE DESARROLLO DE SOFTWARE';

    // ------------------------------------------- Acta real contra planilla real

    public function test_analiza_el_acta_real_contra_la_planilla_real(): void
    {
        $acta = $this->servicio()->parseTexto(file_get_contents($this->fixture('acta_ejemplo.txt')));

        $plan = $this->servicio()->analizar($this->fixture('planilla_ejemplo.xlsx'), $acta);

        $this->assertSame(['2025' => 'S', '2024' => 'S', '2023' => 'U'], $plan['columnas']);
        $this->assertCount(52, $plan['cargas']);
        $this->assertSame([], $plan['conflictos']);
        $this->assertSame([], $plan['sin_cambios']);
        $this->assertSame([], $plan['no_encontrados']);
        $this->assertSame([], $plan['sin_columna']);
        $this->assertSame([], $plan['legajos_repetidos']);
        $this->assertSame(
            ['alumnos_acta' => 52, 'a_cargar' => 52, 'sin_cambios' => 0, 'conflictos' => 0, 'no_encontrados' => 0, 'sin_columna' => 0],
            $plan['resumen'],
        );

        $porLegajo = array_column($plan['cargas'], null, 'legajo');
        $this->assertSame('U41', $porLegajo['29065']['celda']);
        $this->assertSame('2023', $porLegajo['29065']['hoja']);
        $this->assertSame('9 (nueve)', $porLegajo['29065']['nota']);
        $this->assertSame('U5', $porLegajo['29097']['celda']);
        $this->assertSame('U86', $porLegajo['30036']['celda']);
    }

    public function test_informa_los_nombres_que_difieren_entre_acta_y_planilla(): void
    {
        $acta = $this->servicio()->parseTexto(file_get_contents($this->fixture('acta_ejemplo.txt')));

        $plan = $this->servicio()->analizar($this->fixture('planilla_ejemplo.xlsx'), $acta);

        $this->assertSame(
            ['29081', '29097', '29099', '29121', '29122', '29154'],
            array_column($plan['diferencias_nombre'], 'legajo'),
        );

        $agu = array_column($plan['diferencias_nombre'], null, 'legajo')['29097'];
        $this->assertSame('Agu, Federico Migdonio', $agu['nombre_acta']);
        $this->assertSame('Agu, Fedrico Migdonio', $agu['nombre_planilla']);
    }

    public function test_analizar_no_modifica_la_planilla(): void
    {
        $acta = $this->servicio()->parseTexto(file_get_contents($this->fixture('acta_ejemplo.txt')));
        $ruta = $this->fixture('planilla_ejemplo.xlsx');
        $antes = md5_file($ruta);

        $this->servicio()->analizar($ruta, $acta);

        $this->assertSame($antes, md5_file($ruta));
    }

    // ------------------------------------------------ Celdas vacías, iguales y en conflicto

    private function planillaConEstados(): string
    {
        return $this->planilla(['2025' => [
            'fila4' => ['E' => self::GDS],
            'filas' => [
                ['B' => 'Gomez, Ana', 'D' => 29065],                               // vacía
                ['B' => 'Lopez, Luis', 'D' => 29066, 'E' => '8 (ocho)'],            // otra nota
                ['B' => 'Ruiz, Pedro', 'D' => 29067, 'E' => '9 (nueve)'],           // misma nota
                ['B' => 'Diaz, Eva', 'D' => 29068, 'E' => 'AR=2/3'],                // otra cosa
            ],
        ]]);
    }

    private function actaConEstados(): array
    {
        return $this->acta('Gestión de Desarrollo de Software', [
            ['29065', 'Gomez, Ana', 9, 'nueve'],
            ['29066', 'Lopez, Luis', 9, 'nueve'],
            ['29067', 'Ruiz, Pedro', 9, 'nueve'],
            ['29068', 'Diaz, Eva', 7, 'siete'],
            ['29099', 'Nadie, Nadia', 7, 'siete'],
        ]);
    }

    public function test_clasifica_vacias_iguales_conflictos_y_no_encontrados(): void
    {
        $plan = $this->servicio()->analizar($this->planillaConEstados(), $this->actaConEstados());

        $this->assertSame(['29065'], array_column($plan['cargas'], 'legajo'));
        $this->assertSame('E5', $plan['cargas'][0]['celda']);

        $this->assertSame(['29067'], array_column($plan['sin_cambios'], 'legajo'));

        $this->assertSame(['29066', '29068'], array_column($plan['conflictos'], 'legajo'));
        $this->assertSame('8 (ocho)', $plan['conflictos'][0]['actual']);
        $this->assertSame('9 (nueve)', $plan['conflictos'][0]['nota']);
        $this->assertSame('E6', $plan['conflictos'][0]['celda']);
        $this->assertSame('AR=2/3', $plan['conflictos'][1]['actual']);

        $this->assertSame([['legajo' => '29099', 'nombre' => 'Nadie, Nadia', 'nota' => '7 (siete)']], $plan['no_encontrados']);
    }

    public function test_el_resumen_cuenta_cada_categoria(): void
    {
        $plan = $this->servicio()->analizar($this->planillaConEstados(), $this->actaConEstados());

        $this->assertSame(
            ['alumnos_acta' => 5, 'a_cargar' => 1, 'sin_cambios' => 1, 'conflictos' => 2, 'no_encontrados' => 1, 'sin_columna' => 0],
            $plan['resumen'],
        );
    }

    // ----------------------------------------------------------------- Legajos

    public function test_encuentra_un_legajo_guardado_como_texto_con_espacio_invisible(): void
    {
        $planilla = $this->planilla(['2023' => [
            'fila4' => ['E' => self::GDS],
            'filas' => [['B' => 'Mendoza, Victoria', 'D' => "\u{00A0}29076"]],
        ]]);

        $plan = $this->servicio()->analizar($planilla, $this->acta('Gestión de Desarrollo de Software', [
            ['29.076', 'Mendoza, Victoria', 8, 'ocho'],
        ]));

        $this->assertSame(['29076'], array_column($plan['cargas'], 'legajo'));
        $this->assertSame([], $plan['no_encontrados']);
    }

    public function test_un_legajo_en_dos_pestanas_se_carga_en_la_primera_y_se_informa(): void
    {
        $hoja = [
            'fila4' => ['E' => self::GDS],
            'filas' => [['B' => 'Gomez, Ana', 'D' => 29065]],
        ];

        $plan = $this->servicio()->analizar(
            $this->planilla(['2025' => $hoja, '2024' => $hoja]),
            $this->acta('Gestión de Desarrollo de Software', [['29065', 'Gomez, Ana', 9, 'nueve']]),
        );

        $this->assertCount(1, $plan['cargas']);
        $this->assertSame('2025', $plan['cargas'][0]['hoja']);
        $this->assertSame(
            [['legajo' => '29065', 'hojas' => ['2025', '2024'], 'veces' => 2]],
            $plan['legajos_repetidos'],
        );
    }

    public function test_un_legajo_repetido_en_el_acta_se_carga_una_sola_vez(): void
    {
        $planilla = $this->planilla(['2025' => [
            'fila4' => ['E' => self::GDS],
            'filas' => [['B' => 'Gomez, Ana', 'D' => 29065]],
        ]]);

        $plan = $this->servicio()->analizar($planilla, $this->acta('Gestión de Desarrollo de Software', [
            ['29065', 'Gomez, Ana', 9, 'nueve'],
            ['29065', 'Gomez, Ana', 8, 'ocho'],
        ]));

        $this->assertCount(1, $plan['cargas']);
        $this->assertSame('9 (nueve)', $plan['cargas'][0]['nota']);
        $this->assertStringContainsString('más de una vez', $plan['advertencias'][0]);
    }

    public function test_se_ignoran_las_filas_sin_legajo_debajo_de_la_tabla(): void
    {
        $planilla = $this->planilla(['2025' => [
            'fila4' => ['E' => self::GDS],
            'filas' => [
                ['B' => 'Gomez, Ana', 'D' => 29065],
                ['B' => 'AR = Aprobado por regularidad'],
            ],
        ]]);

        $plan = $this->servicio()->analizar($planilla, $this->acta('Gestión de Desarrollo de Software', [
            ['29065', 'Gomez, Ana', 9, 'nueve'],
        ]));

        $this->assertCount(1, $plan['cargas']);
    }

    // ------------------------------------------------ Búsqueda de la columna de la materia

    public function test_prog_i_no_coincide_con_prog_ii_ni_prog_iii(): void
    {
        $planilla = $this->planilla(['2025' => [
            'fila4' => ['E' => 'PROG. I', 'F' => 'PROG. II', 'G' => 'PROG. III'],
            'filas' => [['B' => 'Gomez, Ana', 'D' => 29065]],
        ]]);

        foreach (['Programación I' => 'E', 'Programación II' => 'F', 'Programación III' => 'G'] as $materia => $letra) {
            $plan = $this->servicio()->analizar($planilla, $this->acta($materia, [['29065', 'Gomez, Ana', 9, 'nueve']]));

            $this->assertSame(['2025' => $letra], $plan['columnas'], $materia);
        }
    }

    public function test_la_coincidencia_exacta_gana_sobre_la_parcial(): void
    {
        $planilla = $this->planilla(['2025' => [
            'fila4' => ['E' => 'MATEMÁTICA', 'F' => 'MATEMÁTICA DISCRETA'],
            'filas' => [['B' => 'Gomez, Ana', 'D' => 29065]],
        ]]);

        $discreta = $this->servicio()->analizar($planilla, $this->acta('Matemática Discreta', [['29065', 'Gomez, Ana', 9, 'nueve']]));
        $simple = $this->servicio()->analizar($planilla, $this->acta('Matemática', [['29065', 'Gomez, Ana', 9, 'nueve']]));

        $this->assertSame('F', $discreta['columnas']['2025']);
        $this->assertSame('E', $simple['columnas']['2025']);
    }

    public function test_las_abreviaturas_igualan_el_acta_con_la_planilla(): void
    {
        $planilla = $this->planilla(['2025' => [
            'fila4' => ['E' => 'ARQUIT. Y SIST OPERATIVOS'],
            'filas' => [['B' => 'Gomez, Ana', 'D' => 29065]],
        ]]);

        $plan = $this->servicio()->analizar($planilla, $this->acta('Arquitectura y Sistemas Operativos', [['29065', 'Gomez, Ana', 9, 'nueve']]));

        $this->assertSame(['2025' => 'E'], $plan['columnas']);
    }

    public function test_tambien_busca_la_materia_en_la_fila_3(): void
    {
        $planilla = $this->planilla(['2025' => [
            'fila3' => ['F' => 'LEGISLACIÓN'],
            'filas' => [['B' => 'Gomez, Ana', 'D' => 29065]],
        ]]);

        $plan = $this->servicio()->analizar($planilla, $this->acta('Legislación', [['29065', 'Gomez, Ana', 9, 'nueve']]));

        $this->assertSame('F', $plan['columnas']['2025']);
    }

    public function test_si_la_pestana_del_alumno_no_tiene_la_materia_se_carga_en_otra_donde_aparece(): void
    {
        $planilla = $this->planilla([
            '2025' => ['fila4' => ['E' => 'PROG. I'], 'filas' => [['B' => 'Gomez, Ana', 'D' => 29065]]],
            '2024' => ['fila4' => ['E' => self::GDS], 'filas' => [['B' => 'Gomez, Ana', 'D' => 29065]]],
        ]);

        $plan = $this->servicio()->analizar($planilla, $this->acta('Gestión de Desarrollo de Software', [['29065', 'Gomez, Ana', 9, 'nueve']]));

        $this->assertSame('2024', $plan['cargas'][0]['hoja']);
        $this->assertSame(['2025'], $plan['hojas_sin_columna']);
    }

    public function test_un_alumno_solo_en_una_pestana_sin_la_materia_queda_como_sin_columna(): void
    {
        $planilla = $this->planilla([
            '2025' => ['fila4' => ['E' => self::GDS], 'filas' => [['B' => 'Ruiz, Pedro', 'D' => 29070]]],
            '2023' => ['fila4' => ['E' => 'PROG. I'], 'filas' => [['B' => 'Gomez, Ana', 'D' => 29065]]],
        ]);

        $plan = $this->servicio()->analizar($planilla, $this->acta('Gestión de Desarrollo de Software', [
            ['29065', 'Gomez, Ana', 9, 'nueve'],
            ['29070', 'Ruiz, Pedro', 8, 'ocho'],
        ]));

        $this->assertSame(['29070'], array_column($plan['cargas'], 'legajo'));
        $this->assertSame([['legajo' => '29065', 'nombre' => 'Gomez, Ana', 'nota' => '9 (nueve)', 'hoja' => '2023']], $plan['sin_columna']);
    }

    // ---------------------------------------------------------- Nombres

    public function test_los_nombres_iguales_salvo_tildes_y_mayusculas_no_se_informan(): void
    {
        $planilla = $this->planilla(['2025' => [
            'fila4' => ['E' => self::GDS],
            'filas' => [
                ['B' => 'GOMEZ, ANA', 'D' => 29065],
                ['B' => 'Lopez, Luis', 'D' => 29066],
            ],
        ]]);

        $plan = $this->servicio()->analizar($planilla, $this->acta('Gestión de Desarrollo de Software', [
            ['29065', 'Gómez, Ana', 9, 'nueve'],
            ['29066', 'Lopez, Luis Alberto', 8, 'ocho'],
        ]));

        $this->assertSame(['29066'], array_column($plan['diferencias_nombre'], 'legajo'));
    }

    // ---------------------------------------------------- Errores (M1-11)

    public function test_se_detiene_si_la_materia_no_coincide_con_ninguna_columna(): void
    {
        $planilla = $this->planilla(['2025' => [
            'fila4' => ['E' => self::GDS],
            'filas' => [['B' => 'Gomez, Ana', 'D' => 29065]],
        ]]);

        $this->expectException(PlanillaInvalidaException::class);
        $this->expectExceptionMessage('no coincide con ninguna columna');

        $this->servicio()->analizar($planilla, $this->acta('Física Cuántica', [['29065', 'Gomez, Ana', 9, 'nueve']]));
    }

    public function test_se_detiene_si_la_planilla_no_tiene_legajos_en_la_columna_d(): void
    {
        $planilla = $this->planilla(['2025' => [
            'fila4' => ['E' => self::GDS],
            'filas' => [['B' => 'Gomez, Ana', 'C' => 29065]], // legajo en otra columna
        ]]);

        $this->expectException(PlanillaInvalidaException::class);
        $this->expectExceptionMessage('no tiene la estructura esperada');

        $this->servicio()->analizar($planilla, $this->acta('Gestión de Desarrollo de Software', [['29065', 'Gomez, Ana', 9, 'nueve']]));
    }

    public function test_se_detiene_si_el_archivo_no_es_un_xlsx(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'plan');
        file_put_contents($ruta, 'esto no es una planilla');
        $this->temporales[] = $ruta;

        $this->expectException(PlanillaInvalidaException::class);
        $this->expectExceptionMessage('dañado o no es un .xlsx');

        $this->servicio()->analizar($ruta, $this->acta('Gestión de Desarrollo de Software', [['29065', 'Gomez, Ana', 9, 'nueve']]));
    }

    public function test_se_detiene_si_la_planilla_no_existe(): void
    {
        $this->expectException(PlanillaInvalidaException::class);
        $this->expectExceptionMessage('No se encontró el archivo');

        $this->servicio()->analizar($this->fixture('no_existe.xlsx'), $this->acta('Gestión de Desarrollo de Software', [['29065', 'Gomez, Ana', 9, 'nueve']]));
    }

    public function test_se_detiene_si_el_acta_no_trae_la_materia(): void
    {
        $planilla = $this->planilla(['2025' => [
            'fila4' => ['E' => self::GDS],
            'filas' => [['B' => 'Gomez, Ana', 'D' => 29065]],
        ]]);

        $this->expectException(ActaIlegibleException::class);
        $this->expectExceptionMessage('Asignatura');

        $this->servicio()->analizar($planilla, $this->acta(null, [['29065', 'Gomez, Ana', 9, 'nueve']]));
    }

    // ----------------------------------------------------------- Normalización

    public function test_normaliza_materias_con_tildes_puntos_y_abreviaturas(): void
    {
        $this->assertSame('prog iii', ActaProcessorService::normalizarTexto('Programación III'));
        $this->assertSame('prog iv', ActaProcessorService::normalizarTexto('PROG. IV'));
        $this->assertSame('gestion de desarrollo de software', ActaProcessorService::normalizarTexto('GESTIÓN DE DESARROLLO DE SOFTWARE'));
        $this->assertSame('metodologia de sist i', ActaProcessorService::normalizarTexto('METODOLOGÍA DE SISTEMAS I'));
        $this->assertSame(
            ActaProcessorService::normalizarTexto('ARQUIT. Y SIST OPERATIVOS'),
            ActaProcessorService::normalizarTexto('Arquitectura y Sistemas Operativos'),
        );
        $this->assertSame(
            ActaProcessorService::normalizarTexto('SIST. DE PROCES. DE DATOS'),
            ActaProcessorService::normalizarTexto('Sistemas de Procesamiento de Datos'),
        );
    }
}
