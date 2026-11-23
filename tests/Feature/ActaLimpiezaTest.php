<?php

namespace Tests\Feature;

use App\Enums\EstadoActa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreaUsuarios;
use Tests\Concerns\SubeActas;
use Tests\TestCase;

/** Limpieza programada de las planillas subidas que no se confirmaron (M1-12). */
class ActaLimpiezaTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase, SubeActas;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_descarta_la_planilla_subida_que_no_se_confirmo_en_24_horas(): void
    {
        $acta = $this->actaAnalizada($this->preceptor());
        $origen = $acta->planilla_origen;
        $this->envejecer($acta, 25);

        $this->artisan('actas:limpiar')
            ->expectsOutputToContain('Planillas subidas descartadas: 1')
            ->assertExitCode(0);

        $acta->refresh();
        $this->assertNull($acta->planilla_origen);
        Storage::disk('local')->assertMissing($origen);
        // El acta, su PDF y su estado se conservan: se puede reprocesar.
        $this->assertSame(EstadoActa::Analizada, $acta->estado);
        Storage::disk('local')->assertExists($acta->archivo_pdf);
    }

    public function test_no_toca_lo_que_todavia_esta_dentro_del_plazo(): void
    {
        $acta = $this->actaAnalizada($this->preceptor());
        $this->envejecer($acta, 23);

        $this->artisan('actas:limpiar')->expectsOutputToContain('Planillas subidas descartadas: 0');

        $this->assertNotNull($acta->fresh()->planilla_origen);
        Storage::disk('local')->assertExists($acta->planilla_origen);
    }

    public function test_respeta_el_plazo_configurado(): void
    {
        config(['actas.horas_limpieza' => 2]);
        $acta = $this->actaAnalizada($this->preceptor());
        $this->envejecer($acta, 3);

        $this->artisan('actas:limpiar')->expectsOutputToContain('Planillas subidas descartadas: 1');

        $this->assertNull($acta->fresh()->planilla_origen);
    }

    public function test_conserva_el_pdf_el_informe_y_las_planillas_generadas(): void
    {
        $acta = $this->actaProcesada($this->preceptor());
        $this->envejecer($acta, 100);

        $this->artisan('actas:limpiar')->assertExitCode(0);

        $acta->refresh();
        $this->assertSame(EstadoActa::Procesada, $acta->estado);
        $this->assertNotNull($acta->informe);
        Storage::disk('local')->assertExists($acta->archivo_pdf);
        Storage::disk('local')->assertExists($acta->planillas()->firstOrFail()->archivo_xlsx);
    }

    public function test_descarta_la_planilla_de_un_reproceso_vencido_y_el_acta_sigue_procesada(): void
    {
        $preceptor = $this->preceptor();
        $acta = $this->actaProcesada($preceptor);
        $this->reprocesarCon($preceptor, $acta);
        $pendiente = $acta->fresh()->planilla_origen;
        $this->envejecer($acta, 30);

        $this->artisan('actas:limpiar')->expectsOutputToContain('Planillas subidas descartadas: 1');

        $acta->refresh();
        $this->assertNull($acta->planilla_origen);
        $this->assertSame(EstadoActa::Procesada, $acta->estado);
        $this->assertSame(1, $acta->planillas()->count());
        Storage::disk('local')->assertMissing($pendiente);
    }

    public function test_descarta_los_archivos_huerfanos_viejos_y_deja_los_recientes(): void
    {
        $disco = Storage::disk('local');
        $disco->put('actas/origen/huerfano_viejo.xlsx', 'x');
        $disco->put('actas/origen/huerfano_reciente.xlsx', 'x');
        touch($disco->path('actas/origen/huerfano_viejo.xlsx'), time() - 25 * 3600);

        $this->artisan('actas:limpiar')->expectsOutputToContain('Archivos huérfanos descartados: 1');

        $disco->assertMissing('actas/origen/huerfano_viejo.xlsx');
        $disco->assertExists('actas/origen/huerfano_reciente.xlsx');
    }

    public function test_no_descarta_una_planilla_pendiente_reciente_aunque_el_archivo_sea_viejo(): void
    {
        $acta = $this->actaAnalizada($this->preceptor());
        touch(Storage::disk('local')->path($acta->planilla_origen), time() - 48 * 3600);

        $this->artisan('actas:limpiar')->expectsOutputToContain('Archivos huérfanos descartados: 0');

        Storage::disk('local')->assertExists($acta->planilla_origen);
    }

    public function test_sin_nada_que_limpiar_no_falla(): void
    {
        $this->artisan('actas:limpiar')
            ->expectsOutputToContain('Planillas subidas descartadas: 0. Archivos huérfanos descartados: 0.')
            ->assertExitCode(0);
    }

    public function test_la_limpieza_esta_programada_cada_hora(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('actas:limpiar')->assertExitCode(0);
    }
}
