<?php

namespace Tests\Feature;

use App\Exceptions\ActaIlegibleException;
use App\Services\ActaProcessorService;
use Tests\TestCase;

/**
 * Lectura del acta en PDF. Los datos de tests/Fixtures son simulados.
 * No usa base de datos.
 */
class ActaParserTest extends TestCase
{
    private function servicio(): ActaProcessorService
    {
        return app(ActaProcessorService::class);
    }

    private function fixture(string $nombre): string
    {
        return base_path('tests/Fixtures/'.$nombre);
    }

    private function texto(string ...$lineas): string
    {
        return implode("\n", $lineas);
    }

    // ------------------------------------------------------ Acta real (3 folios)

    public function test_lee_el_pdf_real_de_tres_folios(): void
    {
        $datos = $this->servicio()->parsePdfActa($this->fixture('acta_ejemplo.pdf'));

        $this->assertSame('Gestión de Desarrollo de Software', $datos['materia']);
        $this->assertSame('156', $datos['tomo']);
        $this->assertSame('404-406', $datos['folio']);
        $this->assertSame([404, 405, 406], $datos['folios']);
        $this->assertSame(52, $datos['total_alumnos']);
        $this->assertCount(52, $datos['alumnos']);
        $this->assertSame([], $datos['lineas_no_leidas']);
        $this->assertSame([], $datos['legajos_repetidos']);
        $this->assertTrue($datos['control']['coincide']);
        $this->assertSame('29065', $datos['alumnos'][0]['legajo']);
        $this->assertSame('30036', $datos['alumnos'][51]['legajo']);
    }

    public function test_lee_el_texto_del_acta_real_sin_depender_del_lector_de_pdf(): void
    {
        $texto = file_get_contents($this->fixture('acta_ejemplo.txt'));

        $datos = $this->servicio()->parseTexto($texto);

        $this->assertSame(52, $datos['total_alumnos']);
        $this->assertSame('404-406', $datos['folio']);
        $this->assertSame(
            ['inscriptos' => 52, 'examinados' => 52, 'aprobados' => 52, 'ausentes' => 0, 'no_aprobados' => 0],
            $datos['totales'],
        );
        $this->assertTrue($datos['control']['verificado']);
        $this->assertTrue($datos['control']['coincide']);
    }

    public function test_cada_alumno_trae_nota_en_el_formato_de_la_planilla(): void
    {
        $datos = $this->servicio()->parseTexto(file_get_contents($this->fixture('acta_ejemplo.txt')));
        $primero = $datos['alumnos'][0];

        $this->assertSame(1, $primero['orden']);
        $this->assertSame('29065', $primero['legajo']);
        $this->assertSame('Gonzalez, Olga Mercedes', $primero['nombre']);
        $this->assertSame(9, $primero['nota_num']);
        $this->assertSame('nueve', $primero['nota_letras']);
        $this->assertSame('9 (nueve)', $primero['formato_excel']);
    }

    // --------------------------------------------------------- Datos del encabezado

    public function test_la_materia_no_incluye_el_codigo_entre_parentesis(): void
    {
        $datos = $this->servicio()->parseTexto($this->texto(
            'Asignatura: (34-2024-209)  Gestión de Desarrollo de Software',
            '1 29.065 Gomez, Ana 9 nueve',
        ));

        $this->assertSame('Gestión de Desarrollo de Software', $datos['materia']);
    }

    public function test_un_solo_folio_se_informa_solo_y_los_salteados_separados(): void
    {
        $uno = $this->servicio()->parseTexto($this->texto('Folio : 404', '1 29.065 Gomez, Ana 9 nueve'));
        $saltado = $this->servicio()->parseTexto($this->texto('Folio : 404', '1 29.065 Gomez, Ana 9 nueve', 'Folio : 406'));

        $this->assertSame('404', $uno['folio']);
        $this->assertSame('404, 406', $saltado['folio']);
    }

    public function test_sin_encabezado_los_datos_quedan_vacios(): void
    {
        $datos = $this->servicio()->parseTexto('1 29.065 Gomez, Ana 9 nueve');

        $this->assertNull($datos['materia']);
        $this->assertNull($datos['tomo']);
        $this->assertNull($datos['folio']);
        $this->assertSame(1, $datos['total_alumnos']);
    }

    public function test_advierte_si_el_pdf_trae_mas_de_una_materia_o_tomo(): void
    {
        $datos = $this->servicio()->parseTexto($this->texto(
            'Asignatura: (1-2-3) Prog. I',
            'Tomo : 1',
            'Asignatura: (4-5-6) Prog. II',
            'Tomo : 2',
            '1 29.065 Gomez, Ana 9 nueve',
        ));

        $this->assertSame(['Prog. I', 'Prog. II'], $datos['materias']);
        $this->assertCount(2, $datos['advertencias']);
    }

    // ----------------------------------------------------- Líneas no leídas (M1-04)

    public function test_las_lineas_que_no_cumplen_el_formato_quedan_como_no_leidas(): void
    {
        $datos = $this->servicio()->parseTexto($this->texto(
            '1 29.065 Gonzalez, Olga Mercedes 9 nueve',
            '2 29.069 Masacote, Victor Nicolás Nahuel 8 ocho',
            '3 29.070 Medina, Walter 8 ocho',
            '4 29.071 Perez, Ana Ausente',
            '5 29.072 Libre, Juan',
            '6 29.073 Raro, Pedro 9 ocho',
            'OBSERVACIONES:',
        ));

        $this->assertSame(['29065', '29069', '29070'], array_column($datos['alumnos'], 'legajo'));
        $this->assertSame([
            '4 29.071 Perez, Ana Ausente',
            '5 29.072 Libre, Juan',
            '6 29.073 Raro, Pedro 9 ocho', // la nota 9 no coincide con «ocho»
        ], $datos['lineas_no_leidas']);
    }

    public function test_los_encabezados_y_pies_no_se_toman_por_alumnos_ni_por_no_leidos(): void
    {
        $datos = $this->servicio()->parseTexto($this->texto(
            'Nro.Legajo Apellido y Nombre Nota En Letras',
            '______________________________________',
            '-1- PRESIDENTE Coordinadora de Carrera',
            'INSCRIPTOS:20 EXAMINADOS:20 APROBADOS:20 AUSENTES:0 NO APROBADOS:0',
        ));

        $this->assertSame([], $datos['alumnos']);
        $this->assertSame([], $datos['lineas_no_leidas']);
    }

    // ------------------------------------------------------------- Legajo y notas

    public function test_el_legajo_se_normaliza_a_solo_digitos(): void
    {
        $this->assertSame('29097', ActaProcessorService::normalizarLegajo('29.097'));
        $this->assertSame('29097', ActaProcessorService::normalizarLegajo(29097));
        $this->assertSame('29076', ActaProcessorService::normalizarLegajo("\u{00A0}29076"));
        $this->assertSame('29076', ActaProcessorService::normalizarLegajo(' 29076 '));
        $this->assertSame('', ActaProcessorService::normalizarLegajo(null));
    }

    public function test_los_espacios_de_no_separacion_del_pdf_no_rompen_la_lectura(): void
    {
        $datos = $this->servicio()->parseTexto("1\u{00A0}29.065\u{00A0}Gomez, Ana 9 nueve");

        $this->assertSame('29065', $datos['alumnos'][0]['legajo']);
    }

    public function test_acepta_legajos_sin_puntos_y_la_nota_diez(): void
    {
        $datos = $this->servicio()->parseTexto($this->texto(
            '1 29065 Gomez, Ana 10 diez',
            '2 29.066 Lopez, Luis 7 siete',
        ));

        $this->assertSame('29065', $datos['alumnos'][0]['legajo']);
        $this->assertSame('10 (diez)', $datos['alumnos'][0]['formato_excel']);
        $this->assertSame('7 (siete)', $datos['alumnos'][1]['formato_excel']);
    }

    public function test_acepta_saltos_de_linea_de_windows_y_tabulaciones(): void
    {
        $datos = $this->servicio()->parseTexto("Folio : 1\r\n1\t29.065\tGomez, Ana\t9\tnueve\r\n2 29.066 Perez, Juan 7 siete\r\n");

        $this->assertSame(2, $datos['total_alumnos']);
    }

    public function test_detecta_legajos_repetidos_dentro_del_acta(): void
    {
        $datos = $this->servicio()->parseTexto($this->texto(
            '1 29.065 Gomez, Ana 9 nueve',
            '2 29.065 Gomez, Ana 8 ocho',
            '3 29.066 Perez, Juan 7 siete',
        ));

        $this->assertSame(['29065'], $datos['legajos_repetidos']);
    }

    // ------------------------------------------------------- Control de totales

    public function test_el_control_avisa_si_los_inscriptos_no_coinciden_con_las_lineas(): void
    {
        $datos = $this->servicio()->parseTexto($this->texto(
            '1 29.065 Gomez, Ana 9 nueve',
            'INSCRIPTOS:5 EXAMINADOS:1 APROBADOS:1 AUSENTES:0 NO APROBADOS:0',
        ));

        $this->assertTrue($datos['control']['verificado']);
        $this->assertFalse($datos['control']['coincide']);
        $this->assertStringContainsString('5 inscriptos', $datos['control']['mensaje']);
        $this->assertStringContainsString('revisá', $datos['control']['mensaje']);
    }

    public function test_el_control_cuenta_tambien_las_lineas_no_leidas(): void
    {
        $datos = $this->servicio()->parseTexto($this->texto(
            '1 29.065 Gomez, Ana 9 nueve',
            '2 29.066 Perez, Juan Ausente',
            'INSCRIPTOS:2 EXAMINADOS:1 APROBADOS:1 AUSENTES:1 NO APROBADOS:0',
        ));

        $this->assertTrue($datos['control']['coincide']);
    }

    public function test_sin_resumen_de_totales_el_control_no_se_puede_verificar(): void
    {
        $datos = $this->servicio()->parseTexto('1 29.065 Gomez, Ana 9 nueve');

        $this->assertNull($datos['totales']);
        $this->assertFalse($datos['control']['verificado']);
        $this->assertNull($datos['control']['coincide']);
    }

    // ------------------------------------------------- Errores de lectura (M1-11)

    public function test_un_pdf_sin_texto_se_rechaza_con_un_mensaje_claro(): void
    {
        $this->expectException(ActaIlegibleException::class);
        $this->expectExceptionMessage('no contiene texto');

        $this->servicio()->parsePdfActa($this->fixture('pdf_sin_texto.pdf'));
    }

    public function test_un_archivo_que_no_es_pdf_se_rechaza(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'acta');
        file_put_contents($ruta, 'esto no es un pdf');

        try {
            $this->servicio()->parsePdfActa($ruta);
            $this->fail('Debía lanzar ActaIlegibleException.');
        } catch (ActaIlegibleException $e) {
            $this->assertStringContainsString('PDF', $e->getMessage());
        } finally {
            @unlink($ruta);
        }
    }

    public function test_un_archivo_inexistente_se_rechaza(): void
    {
        $this->expectException(ActaIlegibleException::class);
        $this->expectExceptionMessage('No se encontró el archivo');

        $this->servicio()->parsePdfActa($this->fixture('no_existe.pdf'));
    }
}
