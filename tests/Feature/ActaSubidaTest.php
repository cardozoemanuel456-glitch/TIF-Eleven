<?php

namespace Tests\Feature;

use App\Enums\EstadoActa;
use App\Enums\Rol;
use App\Models\Acta;
use App\Models\PlanillaExcel;
use App\Models\User;
use App\Services\ActaProcessorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreaUsuarios;
use Tests\TestCase;

/**
 * Subida, vista previa, confirmación y descarga. Usa un disco falso (no toca storage/app).
 * El acta y la planilla de tests/Fixtures son simulados.
 */
class ActaSubidaTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    // ------------------------------------------------------------------ Ayudas

    private function fixture(string $nombre): string
    {
        return base_path('tests/Fixtures/'.$nombre);
    }

    private function pdf(string $nombre = 'LIBRO_156.pdf', string $fixture = 'acta_ejemplo.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, file_get_contents($this->fixture($fixture)));
    }

    private function planillaReal(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'SEGUIMIENTO_ACADEMICO_TUP.xlsx',
            file_get_contents($this->fixture('planilla_ejemplo.xlsx')),
        );
    }

    /** La planilla real con algunas celdas modificadas: [[pestaña, celda, valor], ...]. */
    private function planillaConCeldas(array $celdas): UploadedFile
    {
        $libro = IOFactory::load($this->fixture('planilla_ejemplo.xlsx'));

        foreach ($celdas as [$hoja, $celda, $valor]) {
            $libro->getSheetByName($hoja)->setCellValueExplicit($celda, $valor, DataType::TYPE_STRING);
        }

        return $this->aUploadedFile($libro, 'SEGUIMIENTO_ACADEMICO_TUP.xlsx');
    }

    /** Una planilla mínima: [pestaña => [celda => valor]]. */
    private function planillaMinima(array $hojas): UploadedFile
    {
        $libro = new Spreadsheet;
        $primera = true;

        foreach ($hojas as $titulo => $celdas) {
            $hoja = $primera ? $libro->getActiveSheet() : $libro->createSheet();
            $primera = false;
            $hoja->setTitle((string) $titulo);

            foreach ($celdas as $celda => $valor) {
                $hoja->setCellValue($celda, $valor);
            }
        }

        return $this->aUploadedFile($libro, 'planilla.xlsx');
    }

    private function aUploadedFile(Spreadsheet $libro, string $nombre): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'plan');
        (new Xlsx($libro))->save($ruta);
        $contenido = file_get_contents($ruta);
        @unlink($ruta);

        return UploadedFile::fake()->createWithContent($nombre, $contenido);
    }

    private function subir(User $usuario, ?UploadedFile $pdf = null, ?UploadedFile $planilla = null)
    {
        return $this->actingAs($usuario)->from('/actas/subir')->post('/actas/analizar', [
            'acta_pdf' => $pdf ?? $this->pdf(),
            'planilla' => $planilla ?? $this->planillaReal(),
        ]);
    }

    private function preceptor(): User
    {
        return $this->crearUsuario(Rol::Preceptor);
    }

    /** Sube con la planilla indicada y devuelve el acta creada. */
    private function actaAnalizada(User $usuario, ?UploadedFile $planilla = null): Acta
    {
        $this->subir($usuario, null, $planilla)->assertRedirect();

        return Acta::latest('id')->firstOrFail();
    }

    // ------------------------------------------------------------------ Acceso

    public function test_un_invitado_es_enviado_al_login(): void
    {
        $this->get('/actas/subir')->assertRedirect(route('login'));
        $this->post('/actas/analizar')->assertRedirect(route('login'));
    }

    public function test_alumnos_y_profesores_reciben_403_en_todo_el_modulo(): void
    {
        $acta = Acta::factory()->create(['user_id' => $this->preceptor()->id]);
        $planilla = PlanillaExcel::create([
            'acta_id' => $acta->id, 'nombre_original' => 'p.xlsx', 'archivo_xlsx' => 'actas/planillas/x.xlsx', 'generada_en' => now(),
        ]);

        foreach ([Rol::Alumno, Rol::Profesor] as $rol) {
            $u = $this->crearUsuario($rol);

            $this->actingAs($u)->get('/actas/subir')->assertForbidden();
            $this->actingAs($u)->post('/actas/analizar', ['acta_pdf' => $this->pdf(), 'planilla' => $this->planillaReal()])->assertForbidden();
            $this->actingAs($u)->get(route('actas.show', $acta))->assertForbidden();
            $this->actingAs($u)->post(route('actas.confirmar', $acta))->assertForbidden();
            $this->actingAs($u)->get(route('actas.pdf', $acta))->assertForbidden();
            $this->actingAs($u)->get(route('actas.planilla', [$acta, $planilla]))->assertForbidden();
        }

        $this->assertSame(1, Acta::count());
    }

    public function test_el_formulario_se_muestra_a_preceptoria_y_administracion(): void
    {
        foreach ([Rol::Preceptor, Rol::Administrador] as $rol) {
            $this->actingAs($this->crearUsuario($rol))
                ->get('/actas/subir')
                ->assertOk()
                ->assertSee('Analizar acta');
        }
    }

    public function test_el_historial_ofrece_subir_un_acta(): void
    {
        $this->actingAs($this->preceptor())->get('/actas')->assertOk()->assertSee('Subir acta');
    }

    // ----------------------------------------------------- Subida y validaciones

    public function test_subir_un_acta_la_registra_y_lleva_a_la_vista_previa(): void
    {
        $preceptor = $this->preceptor();

        $this->subir($preceptor)->assertRedirect(route('actas.show', Acta::firstOrFail()));

        $acta = Acta::firstOrFail();
        $this->assertSame(EstadoActa::Analizada, $acta->estado);
        $this->assertSame($preceptor->id, $acta->user_id);
        $this->assertSame('Gestión de Desarrollo de Software', $acta->materia);
        $this->assertSame('156', $acta->tomo);
        $this->assertSame('404-406', $acta->folio);
        $this->assertSame('LIBRO_156.pdf', $acta->nombre_original);
        $this->assertSame('SEGUIMIENTO_ACADEMICO_TUP.xlsx', $acta->planilla_nombre);
        $this->assertSame(hash_file('sha256', $this->fixture('acta_ejemplo.pdf')), $acta->hash_sha256);

        Storage::disk('local')->assertExists($acta->archivo_pdf);
        Storage::disk('local')->assertExists($acta->planilla_origen);
    }

    public function test_los_archivos_se_guardan_con_nombre_aleatorio(): void
    {
        $acta = $this->actaAnalizada($this->preceptor());

        $this->assertStringNotContainsString('LIBRO_156', $acta->archivo_pdf);
        $this->assertStringNotContainsString('SEGUIMIENTO', $acta->planilla_origen);
    }

    public function test_la_vista_previa_muestra_lo_que_se_cargaria_sin_escribir_nada(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaAnalizada($preceptor);
        $antes = md5_file(Storage::disk('local')->path($acta->planilla_origen));

        $this->actingAs($preceptor)
            ->get(route('actas.show', $acta))
            ->assertOk()
            ->assertSee('Vista previa de la carga')
            ->assertSee('Gestión de Desarrollo de Software')
            ->assertSee('Notas que se cargarán (52)')
            ->assertSee('El acta indica 52 inscriptos y se identificaron 52 líneas')
            ->assertSee('Confirmar y cargar las notas');

        $this->assertSame($antes, md5_file(Storage::disk('local')->path($acta->planilla_origen)));
        $this->assertSame(EstadoActa::Analizada, $acta->fresh()->estado);
    }

    public function test_rechaza_un_pdf_que_no_es_pdf_aunque_tenga_la_extension(): void
    {
        $falso = UploadedFile::fake()->createWithContent('acta.pdf', 'esto no es un pdf');

        $this->subir($this->preceptor(), $falso)->assertSessionHasErrors('acta_pdf');

        $this->assertSame(0, Acta::count());
    }

    public function test_rechaza_una_planilla_que_no_es_xlsx(): void
    {
        $preceptor = $this->preceptor();

        // texto con extensión .xlsx
        $this->subir($preceptor, null, UploadedFile::fake()->createWithContent('p.xlsx', 'texto'))
            ->assertSessionHasErrors('planilla');
        // un PDF disfrazado de .xlsx
        $this->subir($preceptor, null, UploadedFile::fake()->createWithContent('p.xlsx', file_get_contents($this->fixture('acta_ejemplo.pdf'))))
            ->assertSessionHasErrors('planilla');
        // un xlsx verdadero pero con otra extensión
        $this->subir($preceptor, null, UploadedFile::fake()->createWithContent('p.csv', file_get_contents($this->fixture('planilla_ejemplo.xlsx'))))
            ->assertSessionHasErrors('planilla');

        $this->assertSame(0, Acta::count());
    }

    public function test_rechaza_archivos_de_mas_de_10_mb(): void
    {
        $this->subir($this->preceptor(), UploadedFile::fake()->create('acta.pdf', 10241, 'application/pdf'))
            ->assertSessionHasErrors('acta_pdf');
    }

    public function test_los_dos_archivos_son_obligatorios(): void
    {
        $this->actingAs($this->preceptor())
            ->post('/actas/analizar', [])
            ->assertSessionHasErrors(['acta_pdf', 'planilla']);
    }

    public function test_un_pdf_sin_texto_se_detiene_sin_dejar_nada_guardado(): void
    {
        $this->subir($this->preceptor(), $this->pdf('vacio.pdf', 'pdf_sin_texto.pdf'))
            ->assertSessionHas('error');

        $this->assertStringContainsString('no contiene texto', session('error'));
        $this->assertSame(0, Acta::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_si_la_materia_no_tiene_columna_se_detiene_con_un_mensaje_claro(): void
    {
        $planilla = $this->planillaMinima(['2025' => ['E4' => 'PROG. I', 'D5' => 29065]]);

        $this->subir($this->preceptor(), null, $planilla)->assertSessionHas('error');

        $this->assertStringContainsString('no coincide con ninguna columna', session('error'));
        $this->assertSame(0, Acta::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_si_la_planilla_tiene_otra_estructura_se_detiene(): void
    {
        $planilla = $this->planillaMinima(['2025' => ['E4' => 'GESTIÓN DE DESARROLLO DE SOFTWARE', 'C5' => 29065]]);

        $this->subir($this->preceptor(), null, $planilla)->assertSessionHas('error');

        $this->assertStringContainsString('no tiene la estructura esperada', session('error'));
        $this->assertSame(0, Acta::count());
    }

    public function test_advierte_si_el_mismo_pdf_ya_fue_procesado(): void
    {
        $preceptor = $this->preceptor();
        $otra = $this->crearUsuario(Rol::Administrador);
        Acta::factory()->procesada()->create([
            'user_id' => $otra->id,
            'hash_sha256' => hash_file('sha256', $this->fixture('acta_ejemplo.pdf')),
        ]);

        $acta = $this->actaAnalizada($preceptor);

        $this->actingAs($preceptor)
            ->get(route('actas.show', $acta))
            ->assertOk()
            ->assertSee('ya fue procesado')
            ->assertSee($otra->nombreCompleto());
    }

    // -------------------------------------------------------------- Confirmación

    public function test_confirmar_genera_la_planilla_y_registra_el_informe(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaAnalizada($preceptor);
        $origen = $acta->planilla_origen;

        $this->actingAs($preceptor)
            ->post(route('actas.confirmar', $acta))
            ->assertRedirect(route('actas.show', $acta))
            ->assertSessionHas('estado');

        $acta->refresh();
        $this->assertSame(EstadoActa::Procesada, $acta->estado);
        $this->assertNotNull($acta->procesada_en);
        $this->assertNull($acta->planilla_origen);
        $this->assertSame(52, $acta->informe['notas_cargadas']);
        $this->assertSame(0, $acta->informe['sin_cambios']);
        $this->assertSame([], $acta->informe['conflictos']);
        $this->assertSame(52, $acta->informe['alumnos_leidos']);
        $this->assertSame('404-406', $acta->informe['folio']);

        $planilla = $acta->planillas()->firstOrFail();
        $this->assertSame('SEGUIMIENTO_ACADEMICO_TUP.xlsx', $planilla->nombre_original);
        Storage::disk('local')->assertExists($planilla->archivo_xlsx);
        Storage::disk('local')->assertMissing($origen); // M1-12: la planilla subida se descarta
        Storage::disk('local')->assertExists($acta->archivo_pdf);
    }

    public function test_la_planilla_generada_cambia_solo_las_celdas_previstas_y_conserva_el_formato(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaAnalizada($preceptor);
        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta));

        $planilla = $acta->refresh()->planillas()->firstOrFail();

        $original = IOFactory::load($this->fixture('planilla_ejemplo.xlsx'));
        $generada = IOFactory::load(Storage::disk('local')->path($planilla->archivo_xlsx));

        $this->assertSame($original->getSheetNames(), $generada->getSheetNames());

        $cambios = [];
        foreach ($original->getAllSheets() as $i => $hojaA) {
            $hojaB = $generada->getSheet($i);
            $a = $hojaA->toArray(null, false, false, true);
            $b = $hojaB->toArray(null, false, false, true);

            foreach ($a as $fila => $columnas) {
                foreach ($columnas as $columna => $valor) {
                    if ((string) ($b[$fila][$columna] ?? '') !== (string) $valor) {
                        $cambios[] = $hojaA->getTitle().'!'.$columna.$fila;
                    }
                }
            }

            // Formato: celdas combinadas, anchos, panel inmovilizado y fuente de las notas.
            $this->assertSame($hojaA->getMergeCells(), $hojaB->getMergeCells(), 'celdas combinadas en '.$hojaA->getTitle());
            $this->assertSame($hojaA->getFreezePane(), $hojaB->getFreezePane(), 'panel inmovilizado en '.$hojaA->getTitle());
            $this->assertSame($hojaA->getColumnDimension('B')->getWidth(), $hojaB->getColumnDimension('B')->getWidth());
        }

        $esperadas = array_map(
            fn (array $c) => $c['hoja'].'!'.$c['celda'],
            app(ActaProcessorService::class)->analizar(
                $this->fixture('planilla_ejemplo.xlsx'),
                app(ActaProcessorService::class)->parseTexto(file_get_contents($this->fixture('acta_ejemplo.txt'))),
            )['cargas'],
        );

        $this->assertCount(52, $esperadas);
        $this->assertEqualsCanonicalizing($esperadas, $cambios);

        $celda = $generada->getSheetByName('2023')->getCell('U41');
        $this->assertSame('9 (nueve)', $celda->getValue());
        $this->assertSame(
            $original->getSheetByName('2023')->getStyle('U41')->getFont()->getName(),
            $generada->getSheetByName('2023')->getStyle('U41')->getFont()->getName(),
        );
    }

    public function test_por_defecto_un_conflicto_conserva_la_nota_existente(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaAnalizada($preceptor, $this->planillaConCeldas([['2023', 'U41', '8 (ocho)']]));

        $this->actingAs($preceptor)
            ->get(route('actas.show', $acta))
            ->assertOk()
            ->assertSee('Conflictos: celdas que ya tienen otra nota (1)')
            ->assertSee('8 (ocho)');

        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta))->assertSessionHas('estado');

        $acta->refresh();
        $generada = IOFactory::load(Storage::disk('local')->path($acta->planillas()->firstOrFail()->archivo_xlsx));

        $this->assertSame('8 (ocho)', $generada->getSheetByName('2023')->getCell('U41')->getValue());
        $this->assertSame(51, $acta->informe['notas_cargadas']);
        $this->assertSame('conservar', $acta->informe['conflictos'][0]['resolucion']);
        $this->assertSame('29065', $acta->informe['conflictos'][0]['legajo']);
    }

    public function test_un_conflicto_se_sobrescribe_solo_si_se_elige(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaAnalizada($preceptor, $this->planillaConCeldas([['2023', 'U41', '8 (ocho)']]));

        $this->actingAs($preceptor)
            ->post(route('actas.confirmar', $acta), ['decision' => ['29065' => 'sobrescribir']])
            ->assertSessionHas('estado');

        $acta->refresh();
        $generada = IOFactory::load(Storage::disk('local')->path($acta->planillas()->firstOrFail()->archivo_xlsx));

        $this->assertSame('9 (nueve)', $generada->getSheetByName('2023')->getCell('U41')->getValue());
        $this->assertSame(52, $acta->informe['notas_cargadas']);
        $this->assertSame('sobrescribir', $acta->informe['conflictos'][0]['resolucion']);
    }

    public function test_una_decision_invalida_se_rechaza(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaAnalizada($preceptor);

        $this->actingAs($preceptor)
            ->post(route('actas.confirmar', $acta), ['decision' => ['29065' => 'borrar']])
            ->assertSessionHasErrors('decision.29065');

        $this->assertSame(EstadoActa::Analizada, $acta->fresh()->estado);
    }

    public function test_una_celda_con_la_misma_nota_queda_sin_cambios(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaAnalizada($preceptor, $this->planillaConCeldas([['2023', 'U5', '9 (nueve)']]));

        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta));

        $acta->refresh();
        $this->assertSame(1, $acta->informe['sin_cambios']);
        $this->assertSame(51, $acta->informe['notas_cargadas']);
    }

    public function test_un_acta_no_se_confirma_dos_veces(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaAnalizada($preceptor);

        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta));
        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta))->assertSessionHas('error');

        $this->assertSame(1, PlanillaExcel::count());
    }

    public function test_si_no_hay_nada_para_cargar_no_se_genera_planilla(): void
    {
        $preceptor = $this->preceptor();
        $servicio = app(ActaProcessorService::class);

        // Las 52 celdas que recibirían nota ya tienen otra: todo son conflictos y se conservan.
        $plan = $servicio->analizar(
            $this->fixture('planilla_ejemplo.xlsx'),
            $servicio->parseTexto(file_get_contents($this->fixture('acta_ejemplo.txt'))),
        );
        $ocupadas = array_map(fn (array $c) => [$c['hoja'], $c['celda'], '5 (cinco)'], $plan['cargas']);
        $this->assertCount(52, $ocupadas);

        $acta = $this->actaAnalizada($preceptor, $this->planillaConCeldas($ocupadas));

        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta))->assertSessionHas('error');

        $this->assertStringContainsString('No hay notas para cargar', session('error'));
        $this->assertSame(EstadoActa::Analizada, $acta->fresh()->estado);
        $this->assertSame(0, PlanillaExcel::count());
    }

    public function test_si_faltan_los_archivos_se_informa_y_el_acta_sigue_analizada(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaAnalizada($preceptor);
        Storage::disk('local')->delete($acta->planilla_origen);

        $this->actingAs($preceptor)
            ->get(route('actas.show', $acta))
            ->assertOk()
            ->assertSee('ya no están disponibles');

        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta))->assertSessionHas('error');
        $this->assertSame(EstadoActa::Analizada, $acta->fresh()->estado);
    }

    public function test_si_la_carga_falla_el_acta_queda_con_error_y_no_se_entrega_planilla(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaAnalizada($preceptor);
        // La planilla subida se daña después del análisis.
        Storage::disk('local')->put($acta->planilla_origen, 'archivo dañado');

        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta))->assertSessionHas('error');

        $acta->refresh();
        $this->assertSame(EstadoActa::Error, $acta->estado);
        $this->assertStringContainsString('dañado', $acta->mensaje_error);
        $this->assertNull($acta->planilla_origen);
        $this->assertSame(0, PlanillaExcel::count());

        $this->actingAs($preceptor)
            ->get(route('actas.show', $acta))
            ->assertOk()
            ->assertSee('No se pudo continuar con esta acta')
            ->assertSee('no se modificó');
    }

    // ----------------------------------------------------- Informe y descargas

    public function test_el_informe_muestra_lo_cargado_y_ofrece_las_descargas(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaAnalizada($preceptor, $this->planillaConCeldas([['2023', 'U41', '8 (ocho)']]));
        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta));

        $this->actingAs($preceptor)
            ->get(route('actas.show', $acta))
            ->assertOk()
            ->assertSee('Informe de la carga')
            ->assertSee('Descargar planilla actualizada')
            ->assertSee('Se conservó la existente')
            ->assertSee('404-406');
    }

    public function test_se_descargan_la_planilla_y_el_pdf(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaAnalizada($preceptor);
        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta));
        $planilla = $acta->refresh()->planillas()->firstOrFail();

        $this->actingAs($preceptor)
            ->get(route('actas.planilla', [$acta, $planilla]))
            ->assertOk()
            ->assertDownload('SEGUIMIENTO_ACADEMICO_TUP_actualizada.xlsx');

        $this->actingAs($preceptor)
            ->get(route('actas.pdf', $acta))
            ->assertOk()
            ->assertDownload('LIBRO_156.pdf');
    }

    public function test_una_planilla_de_otra_acta_no_se_descarga_por_esta_url(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaAnalizada($preceptor);
        $otra = Acta::factory()->procesada()->create(['user_id' => $preceptor->id]);
        $ajena = PlanillaExcel::create([
            'acta_id' => $otra->id, 'nombre_original' => 'p.xlsx', 'archivo_xlsx' => 'actas/planillas/ajena.xlsx', 'generada_en' => now(),
        ]);

        $this->actingAs($preceptor)->get(route('actas.planilla', [$acta, $ajena]))->assertNotFound();
    }

    public function test_un_archivo_que_ya_no_existe_responde_404(): void
    {
        $preceptor = $this->preceptor();
        $acta = Acta::factory()->procesada()->create(['user_id' => $preceptor->id]);

        $this->actingAs($preceptor)->get(route('actas.pdf', $acta))->assertNotFound();
    }
}
