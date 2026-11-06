<?php

namespace Tests\Feature;

use App\Enums\EstadoActa;
use App\Enums\Rol;
use App\Models\Acta;
use App\Models\PlanillaExcel;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaUsuarios;
use Tests\TestCase;

class ActasBaseTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    private function acta(User $usuario, array $extra = []): Acta
    {
        return Acta::factory()->create(array_merge(['user_id' => $usuario->id], $extra));
    }

    // ---------------------------------------------------------------- Acceso

    public function test_un_invitado_es_enviado_al_login(): void
    {
        $this->get('/actas')->assertRedirect(route('login'));
    }

    public function test_preceptoria_y_administracion_ven_el_historial(): void
    {
        foreach ([Rol::Preceptor, Rol::Administrador] as $rol) {
            $this->actingAs($this->crearUsuario($rol))->get('/actas')->assertOk()->assertSee('Actas');
        }
    }

    public function test_alumnos_y_profesores_reciben_403(): void
    {
        foreach ([Rol::Alumno, Rol::Profesor] as $rol) {
            $this->actingAs($this->crearUsuario($rol))
                ->get('/actas')
                ->assertForbidden()
                ->assertSee('No tenés permiso');
        }
    }

    public function test_el_menu_muestra_actas_solo_a_quien_corresponde(): void
    {
        $enlace = 'href="'.route('actas.index').'"';

        $this->actingAs($this->crearUsuario(Rol::Preceptor))->get('/inicio')->assertSee($enlace, false);
        $this->actingAs($this->crearUsuario(Rol::Profesor))->get('/inicio')->assertDontSee($enlace, false);
        $this->actingAs($this->crearUsuario(Rol::Alumno))->get('/inicio')->assertDontSee($enlace, false);
    }

    // -------------------------------------------------------------- Historial

    public function test_el_historial_vacio_muestra_un_mensaje(): void
    {
        $this->actingAs($this->crearUsuario(Rol::Preceptor))
            ->get('/actas')
            ->assertOk()
            ->assertSee('Todavía no se procesaron actas');
    }

    public function test_el_historial_muestra_materia_tomo_folio_y_estado(): void
    {
        $preceptor = $this->crearUsuario(Rol::Preceptor);
        $this->acta($preceptor, ['folio' => '404-406'])->update(['estado' => EstadoActa::Procesada]);

        $this->actingAs($preceptor)
            ->get('/actas')
            ->assertOk()
            ->assertSee('Gestión de Desarrollo de Software')
            ->assertSee('156')
            ->assertSee('404-406')
            ->assertSee('Procesada')
            ->assertSee($preceptor->nombreCompleto());
    }

    public function test_el_historial_va_del_mas_reciente_al_mas_antiguo(): void
    {
        $preceptor = $this->crearUsuario(Rol::Preceptor);
        $this->acta($preceptor, ['materia' => 'Materia Vieja', 'created_at' => now()->subDays(3)]);
        $this->acta($preceptor, ['materia' => 'Materia Nueva', 'created_at' => now()]);

        $this->actingAs($preceptor)->get('/actas')->assertSeeInOrder(['Materia Nueva', 'Materia Vieja']);
    }

    public function test_el_historial_se_pagina_de_a_quince(): void
    {
        $preceptor = $this->crearUsuario(Rol::Preceptor);
        Acta::factory()->count(16)->create(['user_id' => $preceptor->id]);

        $pagina1 = $this->actingAs($preceptor)->get('/actas')->assertOk();
        $this->assertSame(15, substr_count($pagina1->getContent(), 'Gestión de Desarrollo de Software'));

        $pagina2 = $this->actingAs($preceptor)->get('/actas?page=2')->assertOk();
        $this->assertSame(1, substr_count($pagina2->getContent(), 'Gestión de Desarrollo de Software'));
    }

    // ------------------------------------------------------------ Modelo y datos

    public function test_el_informe_se_guarda_como_json_y_el_estado_como_enum(): void
    {
        $acta = $this->acta($this->crearUsuario(Rol::Preceptor), [
            'informe' => ['alumnos_leidos' => 52, 'no_encontrados' => []],
            'estado' => 'procesada',
        ])->fresh();

        $this->assertSame(52, $acta->informe['alumnos_leidos']);
        $this->assertSame(EstadoActa::Procesada, $acta->estado);
    }

    public function test_el_hash_no_es_unico_para_poder_reprocesar(): void
    {
        $preceptor = $this->crearUsuario(Rol::Preceptor);
        $hash = hash('sha256', 'mismo-pdf');

        $this->acta($preceptor, ['hash_sha256' => $hash]);
        $this->acta($preceptor, ['hash_sha256' => $hash]);

        $this->assertSame(2, Acta::where('hash_sha256', $hash)->count());
    }

    public function test_al_borrar_un_acta_se_borran_sus_planillas(): void
    {
        $acta = $this->acta($this->crearUsuario(Rol::Preceptor));
        PlanillaExcel::create([
            'acta_id' => $acta->id,
            'nombre_original' => 'SEGUIMIENTO.xlsx',
            'archivo_xlsx' => 'actas/planillas/1.xlsx',
            'generada_en' => now(),
        ]);

        $this->assertSame(1, $acta->planillas()->count());

        $acta->delete();

        $this->assertDatabaseCount('planillas_excel', 0);
    }

    public function test_no_se_borra_un_usuario_con_actas(): void
    {
        $preceptor = $this->crearUsuario(Rol::Preceptor);
        $this->acta($preceptor);

        $this->expectException(QueryException::class);
        $preceptor->delete();
    }

    // ----------------------------------------------------------- Configuración

    public function test_la_configuracion_refleja_la_planilla_real(): void
    {
        $this->assertSame([3, 4], config('actas.filas_materia'));
        $this->assertSame(4, config('actas.columna_legajo'));
        $this->assertSame(5, config('actas.fila_inicial'));
        $this->assertSame(10240, config('actas.max_kb'));
        $this->assertSame('{nota} ({letras})', config('actas.formato_nota'));
        $this->assertSame('prog', config('actas.abreviaturas')['programacion']);
        $this->assertSame('sist', config('actas.abreviaturas')['sistemas']);
    }
}
