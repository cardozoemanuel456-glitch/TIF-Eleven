<?php

namespace Tests\Feature;

use App\Enums\EstadoActa;
use App\Enums\Rol;
use App\Models\PlanillaExcel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\CreaUsuarios;
use Tests\Concerns\SubeActas;
use Tests\TestCase;

/**
 * Reprocesar (M1-13, opción A): se reutiliza el PDF guardado y se pide la planilla de
 * seguimiento actual. Se genera una planilla nueva y se conservan las anteriores.
 */
class ActaReprocesoTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase, SubeActas;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_el_historial_ofrece_reprocesar_y_eliminar(): void
    {
        $preceptor = $this->preceptor();
        $this->actaProcesada($preceptor);

        $this->actingAs($preceptor)
            ->get('/actas')
            ->assertOk()
            ->assertSee('Reprocesar')
            ->assertSee('Eliminar');
    }

    public function test_el_formulario_pide_solo_la_planilla_y_avisa_que_debe_ser_la_actual(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaProcesada($preceptor);

        $this->actingAs($preceptor)
            ->get(route('actas.reprocesar', $acta))
            ->assertOk()
            ->assertSee('Gestión de Desarrollo de Software')
            ->assertSee('No hace falta subir el PDF')
            ->assertSee('planilla actual')
            ->assertDontSee('Acta en PDF');
    }

    public function test_reprocesar_aplica_de_nuevo_el_acta_guardada_sin_subir_el_pdf(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaProcesada($preceptor);
        $pdf = $acta->archivo_pdf;

        // Se envía solo la planilla; el PDF no viaja.
        $this->reprocesarCon($preceptor, $acta);

        $acta->refresh();
        $this->assertSame(EstadoActa::Procesada, $acta->estado);
        $this->assertNotNull($acta->planilla_origen);
        $this->assertSame($pdf, $acta->archivo_pdf);

        $this->actingAs($preceptor)
            ->get(route('actas.show', $acta))
            ->assertOk()
            ->assertSee('Reproceso: se aplica el acta ya guardada')
            ->assertSee('Notas que se cargarán (52)');
    }

    public function test_al_confirmar_el_reproceso_se_genera_otra_planilla_y_se_conservan_las_anteriores(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaProcesada($preceptor);
        $primera = $acta->planillas()->firstOrFail();

        $this->reprocesarCon($preceptor, $acta);
        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta))->assertSessionHas('estado');

        $acta->refresh();
        $this->assertSame(EstadoActa::Procesada, $acta->estado);
        $this->assertNull($acta->planilla_origen);
        $this->assertSame(2, $acta->planillas()->count());
        $this->assertSame(52, $acta->informe['notas_cargadas']);

        foreach ($acta->planillas as $planilla) {
            Storage::disk('local')->assertExists($planilla->archivo_xlsx);
        }
        $this->assertTrue($acta->planillas->contains($primera));
    }

    public function test_el_informe_lista_todas_las_planillas_generadas(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaProcesada($preceptor);
        $this->reprocesarCon($preceptor, $acta);
        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta));

        $pagina = $this->actingAs($preceptor)->get(route('actas.show', $acta))->assertOk();

        $this->assertSame(2, substr_count($pagina->getContent(), 'Descargar planilla actualizada'));
        $pagina->assertSee('la más reciente');
    }

    public function test_reprocesar_con_la_planilla_ya_generada_no_tiene_nada_para_cargar(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaProcesada($preceptor);
        $generada = $acta->planillas()->firstOrFail();
        $actual = UploadedFile::fake()->createWithContent(
            'SEGUIMIENTO_ACADEMICO_TUP.xlsx',
            Storage::disk('local')->get($generada->archivo_xlsx),
        );

        $this->reprocesarCon($preceptor, $acta, $actual);

        $this->actingAs($preceptor)
            ->get(route('actas.show', $acta))
            ->assertOk()
            ->assertSee('No hay notas nuevas para cargar');

        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta))->assertSessionHas('error');
        $this->assertSame(1, $acta->planillas()->count());
    }

    public function test_un_conflicto_del_reproceso_se_conserva_por_defecto(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaProcesada($preceptor);

        $this->reprocesarCon($preceptor, $acta, $this->planillaConCeldas([['2023', 'U41', '8 (ocho)']]));
        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta));

        $acta->refresh();
        $nueva = PlanillaExcel::where('acta_id', $acta->id)->latest('id')->firstOrFail(); // la más reciente
        $libro = IOFactory::load(Storage::disk('local')->path($nueva->archivo_xlsx));

        $this->assertSame('8 (ocho)', $libro->getSheetByName('2023')->getCell('U41')->getValue());
        $this->assertSame(51, $acta->informe['notas_cargadas']);
        $this->assertSame('conservar', $acta->informe['conflictos'][0]['resolucion']);
    }

    public function test_el_reproceso_rechaza_una_planilla_que_no_es_xlsx(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaProcesada($preceptor);

        $this->actingAs($preceptor)
            ->from(route('actas.reprocesar', $acta))
            ->post(route('actas.reprocesar.guardar', $acta), ['planilla' => UploadedFile::fake()->createWithContent('p.xlsx', 'texto')])
            ->assertSessionHasErrors('planilla');

        $this->assertNull($acta->fresh()->planilla_origen);
    }

    public function test_si_la_planilla_no_tiene_la_materia_el_reproceso_se_detiene_sin_dejar_archivos(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaProcesada($preceptor);
        $antes = Storage::disk('local')->allFiles();

        $planilla = $this->planillaMinima(['2025' => ['E4' => 'PROG. I', 'D5' => 29065]]);

        $this->actingAs($preceptor)
            ->from(route('actas.reprocesar', $acta))
            ->post(route('actas.reprocesar.guardar', $acta), ['planilla' => $planilla])
            ->assertSessionHas('error');

        $this->assertStringContainsString('no coincide con ninguna columna', session('error'));
        $this->assertNull($acta->fresh()->planilla_origen);
        $this->assertEqualsCanonicalizing($antes, Storage::disk('local')->allFiles());
    }

    public function test_sin_el_pdf_guardado_no_se_puede_reprocesar(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaProcesada($preceptor);
        Storage::disk('local')->delete($acta->archivo_pdf);

        $this->actingAs($preceptor)
            ->get(route('actas.reprocesar', $acta))
            ->assertOk()
            ->assertSee('ya no está disponible');

        $this->actingAs($preceptor)
            ->post(route('actas.reprocesar.guardar', $acta), ['planilla' => $this->planillaReal()])
            ->assertSessionHas('error');
        $this->assertNull($acta->fresh()->planilla_origen);
    }

    public function test_si_ya_hay_una_planilla_pendiente_el_formulario_lleva_a_la_vista_previa(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaProcesada($preceptor);
        $this->reprocesarCon($preceptor, $acta);

        $this->actingAs($preceptor)->get(route('actas.reprocesar', $acta))->assertRedirect(route('actas.show', $acta));
    }

    public function test_reprocesar_dos_veces_seguidas_reemplaza_la_planilla_pendiente(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaProcesada($preceptor);

        $this->reprocesarCon($preceptor, $acta);
        $primera = $acta->fresh()->planilla_origen;
        $this->reprocesarCon($preceptor, $acta);
        $segunda = $acta->fresh()->planilla_origen;

        $this->assertNotSame($primera, $segunda);
        Storage::disk('local')->assertMissing($primera);
        Storage::disk('local')->assertExists($segunda);
    }

    public function test_un_acta_con_error_se_puede_reprocesar(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaAnalizada($preceptor);
        Storage::disk('local')->put($acta->planilla_origen, 'archivo dañado');
        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta));
        $this->assertSame(EstadoActa::Error, $acta->fresh()->estado);

        $this->actingAs($preceptor)
            ->get(route('actas.show', $acta))
            ->assertSee('Reprocesar con mi planilla actual');

        $this->reprocesarCon($preceptor, $acta);
        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta))->assertSessionHas('estado');

        $acta->refresh();
        $this->assertSame(EstadoActa::Procesada, $acta->estado);
        $this->assertNull($acta->mensaje_error);
        $this->assertSame(1, $acta->planillas()->count());
    }

    public function test_un_acta_analizada_cuya_planilla_vencio_se_puede_reprocesar(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaAnalizada($preceptor);
        $this->envejecer($acta, 25);
        $this->artisan('actas:limpiar')->assertExitCode(0);

        $this->actingAs($preceptor)
            ->get(route('actas.show', $acta))
            ->assertOk()
            ->assertSee('ya no están disponibles')
            ->assertSee('Reprocesar con mi planilla actual');

        $this->reprocesarCon($preceptor, $acta);
        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta))->assertSessionHas('estado');

        $this->assertSame(EstadoActa::Procesada, $acta->fresh()->estado);
    }

    public function test_si_el_reproceso_falla_el_acta_sigue_procesada_con_sus_planillas(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaProcesada($preceptor);
        $this->reprocesarCon($preceptor, $acta);
        Storage::disk('local')->put($acta->fresh()->planilla_origen, 'archivo dañado');

        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta))->assertSessionHas('error');

        $acta->refresh();
        $this->assertSame(EstadoActa::Procesada, $acta->estado);
        $this->assertNotNull($acta->mensaje_error);
        $this->assertNull($acta->planilla_origen);
        $this->assertSame(1, PlanillaExcel::count());

        $this->actingAs($preceptor)
            ->get(route('actas.show', $acta))
            ->assertOk()
            ->assertSee('Informe de la carga')
            ->assertSee('Descargar planilla actualizada');
    }

    public function test_confirmar_sin_una_planilla_pendiente_remite_a_reprocesar(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaProcesada($preceptor);

        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta))->assertSessionHas('error');

        $this->assertStringContainsString('Reprocesar', session('error'));
        $this->assertSame(1, $acta->planillas()->count());
    }

    public function test_solo_preceptoria_y_administracion_reprocesan(): void
    {
        $acta = $this->actaProcesada($this->preceptor());

        foreach ([Rol::Alumno, Rol::Profesor] as $rol) {
            $u = $this->crearUsuario($rol);

            $this->actingAs($u)->get(route('actas.reprocesar', $acta))->assertForbidden();
            $this->actingAs($u)->post(route('actas.reprocesar.guardar', $acta), ['planilla' => $this->planillaReal()])->assertForbidden();
        }

        $this->assertNull($acta->fresh()->planilla_origen);
    }
}
