<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Acta;
use App\Models\PlanillaExcel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreaUsuarios;
use Tests\Concerns\SubeActas;
use Tests\TestCase;

/** Eliminar un acta borra su PDF, sus planillas y su informe (M1-13). */
class ActaEliminarTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase, SubeActas;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_eliminar_un_acta_procesada_borra_el_registro_y_todos_sus_archivos(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaProcesada($preceptor);
        $this->reprocesarCon($preceptor, $acta);
        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta));

        $acta->refresh();
        $archivos = [$acta->archivo_pdf, ...$acta->planillas->pluck('archivo_xlsx')->all()];
        $this->assertCount(3, $archivos);

        $this->actingAs($preceptor)
            ->delete(route('actas.destroy', $acta))
            ->assertRedirect(route('actas.index'))
            ->assertSessionHas('estado');

        $this->assertSame(0, Acta::count());
        $this->assertSame(0, PlanillaExcel::count());
        foreach ($archivos as $ruta) {
            Storage::disk('local')->assertMissing($ruta);
        }
    }

    public function test_eliminar_un_acta_analizada_borra_el_pdf_y_la_planilla_subida(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaAnalizada($preceptor);
        $pdf = $acta->archivo_pdf;
        $origen = $acta->planilla_origen;

        $this->actingAs($preceptor)->delete(route('actas.destroy', $acta))->assertRedirect(route('actas.index'));

        $this->assertSame(0, Acta::count());
        Storage::disk('local')->assertMissing($pdf);
        Storage::disk('local')->assertMissing($origen);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_eliminar_un_acta_no_toca_las_demas(): void
    {
        $preceptor = $this->preceptor();
        $una = $this->actaProcesada($preceptor);
        $otra = $this->actaProcesada($preceptor);
        $archivosOtra = [$otra->archivo_pdf, $otra->planillas()->firstOrFail()->archivo_xlsx];

        $this->actingAs($preceptor)->delete(route('actas.destroy', $una));

        $this->assertSame(1, Acta::count());
        $this->assertTrue(Acta::whereKey($otra->id)->exists());
        $this->assertSame(1, PlanillaExcel::where('acta_id', $otra->id)->count());
        foreach ($archivosOtra as $ruta) {
            Storage::disk('local')->assertExists($ruta);
        }
    }

    public function test_si_ya_faltan_los_archivos_igual_se_elimina(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaProcesada($preceptor);
        Storage::disk('local')->delete([$acta->archivo_pdf, $acta->planillas()->firstOrFail()->archivo_xlsx]);

        $this->actingAs($preceptor)->delete(route('actas.destroy', $acta))->assertRedirect(route('actas.index'));

        $this->assertSame(0, Acta::count());
    }

    public function test_un_acta_con_error_se_puede_eliminar_desde_su_pantalla(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaAnalizada($preceptor);
        Storage::disk('local')->put($acta->planilla_origen, 'archivo dañado');
        $this->actingAs($preceptor)->post(route('actas.confirmar', $acta));

        $this->actingAs($preceptor)->get(route('actas.show', $acta))->assertSee('Eliminar esta acta');
        $this->actingAs($preceptor)->delete(route('actas.destroy', $acta))->assertRedirect(route('actas.index'));

        $this->assertSame(0, Acta::count());
    }

    public function test_el_acta_eliminada_desaparece_del_historial(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaProcesada($preceptor);

        $this->actingAs($preceptor)->delete(route('actas.destroy', $acta));

        $this->actingAs($preceptor)
            ->get('/actas')
            ->assertOk()
            ->assertSee('Todavía no se procesaron actas')
            ->assertSee('Se eliminó el acta');
    }

    public function test_solo_preceptoria_y_administracion_eliminan(): void
    {
        $acta = $this->actaProcesada($this->preceptor());

        foreach ([Rol::Alumno, Rol::Profesor] as $rol) {
            $this->actingAs($this->crearUsuario($rol))->delete(route('actas.destroy', $acta))->assertForbidden();
        }

        $this->assertSame(1, Acta::count());
        Storage::disk('local')->assertExists($acta->archivo_pdf);
    }

    public function test_un_invitado_no_puede_eliminar(): void
    {
        $acta = $this->actaProcesada($this->preceptor());
        auth()->logout();

        $this->delete(route('actas.destroy', $acta))->assertRedirect(route('login'));

        $this->assertSame(1, Acta::count());
    }

    public function test_el_administrador_tambien_puede_eliminar(): void
    {
        $acta = $this->actaProcesada($this->preceptor());

        $this->actingAs($this->crearUsuario(Rol::Administrador))
            ->delete(route('actas.destroy', $acta))
            ->assertRedirect(route('actas.index'));

        $this->assertSame(0, Acta::count());
    }
}
