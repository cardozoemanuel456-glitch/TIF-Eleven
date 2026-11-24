<?php

namespace Tests\Feature;

use App\Enums\AnioCursada;
use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use App\Models\User;
use App\Notifications\CuentaAprobadaNotification;
use App\Services\GestionAlumnosService;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** M3-04, M3-06, M3-07, M3-10 a M3-13 · Gestión de alumnos (Jira HU-22). */
class GestionAlumnosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
    }

    private function personal(Rol $rol): User
    {
        return User::factory()->conRol($rol)->create();
    }

    private function pendiente(array $extra = []): User
    {
        return User::factory()->alumno()->pendiente()->create($extra);
    }

    // ----------------------------------------------------------------- Acceso

    public function test_un_invitado_es_enviado_al_login(): void
    {
        $this->get('/alumnos')->assertRedirect(route('login'));
    }

    public function test_alumnos_y_profesores_no_acceden_a_la_gestion(): void
    {
        $alumno = User::factory()->alumno()->create();
        $otro = User::factory()->alumno()->create();

        foreach ([$alumno, $this->personal(Rol::Profesor)] as $usuario) {
            $this->actingAs($usuario)->get('/alumnos')->assertForbidden();
            $this->actingAs($usuario)->get(route('alumnos.show', ['alumno' => $otro]))->assertForbidden();
            $this->actingAs($usuario)->post(route('alumnos.desactivar', ['alumno' => $otro]))->assertForbidden();
        }
    }

    public function test_preceptoria_y_administracion_ven_el_listado(): void
    {
        $alumno = User::factory()->alumno()->create(['apellido' => 'Visible']);

        foreach ([Rol::Preceptor, Rol::Administrador] as $rol) {
            $this->actingAs($this->personal($rol))->get('/alumnos')->assertOk()->assertSee('Visible');
        }
    }

    public function test_el_personal_no_es_un_alumno_gestionable(): void
    {
        $profesor = $this->personal(Rol::Profesor);

        $this->actingAs($this->personal(Rol::Administrador))
            ->get(route('alumnos.show', ['alumno' => $profesor]))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------- Listado

    public function test_el_listado_solo_muestra_cuentas_con_correo_verificado(): void
    {
        $this->pendiente(['apellido' => 'Verificado']);
        $this->pendiente(['apellido' => 'SinVerificar', 'email_verified_at' => null]);

        $this->actingAs($this->personal(Rol::Preceptor))->get('/alumnos')
            ->assertOk()->assertSee('Verificado')->assertDontSee('SinVerificar');
    }

    public function test_las_cuentas_pendientes_aparecen_destacadas(): void
    {
        $this->pendiente(['apellido' => 'Espera']);

        $this->actingAs($this->personal(Rol::Preceptor))->get('/alumnos')
            ->assertOk()->assertSee('Pendiente')->assertSee('bg-amber-50', false);
    }

    public function test_el_listado_busca_por_nombre_apellido_y_dni(): void
    {
        User::factory()->alumno()->create(['nombre' => 'Lucia', 'apellido' => 'Buscada', 'dni' => '12345678']);
        User::factory()->alumno()->create(['nombre' => 'Pedro', 'apellido' => 'Ajeno', 'dni' => '87654321']);
        $preceptor = $this->personal(Rol::Preceptor);

        foreach (['Lucia', 'Buscada', '12345678'] as $termino) {
            $this->actingAs($preceptor)->get(route('alumnos.index', ['q' => $termino]))
                ->assertOk()->assertSee('Buscada')->assertDontSee('Ajeno');
        }
    }

    public function test_el_listado_filtra_por_aula_y_por_estado(): void
    {
        User::factory()->alumno(AnioCursada::Primero)->create(['apellido' => 'DePrimero']);
        User::factory()->alumno(AnioCursada::Segundo)->create(['apellido' => 'DeSegundo']);
        User::factory()->alumno()->inactivo()->create(['apellido' => 'DeBaja']);
        $preceptor = $this->personal(Rol::Preceptor);

        $this->actingAs($preceptor)->get(route('alumnos.index', ['anio' => 2]))
            ->assertSee('DeSegundo')->assertDontSee('DePrimero');

        $this->actingAs($preceptor)->get(route('alumnos.index', ['estado' => 'inactivo']))
            ->assertSee('DeBaja')->assertDontSee('DeSegundo');
    }

    // --------------------------------------------------------------- Aprobación

    public function test_preceptoria_aprueba_un_registro_y_el_alumno_recibe_un_correo(): void
    {
        Notification::fake();
        $preceptor = $this->personal(Rol::Preceptor);
        $alumno = $this->pendiente();

        $this->actingAs($preceptor)->post(route('alumnos.aprobar', ['alumno' => $alumno]))->assertRedirect();

        $alumno->refresh();
        $this->assertSame(EstadoUsuario::Activo, $alumno->estado);
        $this->assertSame($preceptor->id, $alumno->aprobado_por);
        $this->assertNotNull($alumno->aprobado_en);
        Notification::assertSentTo($alumno, CuentaAprobadaNotification::class);
    }

    public function test_un_alumno_aprobado_puede_ingresar(): void
    {
        Notification::fake();
        $alumno = $this->pendiente();

        app(GestionAlumnosService::class)->aprobar($alumno, $this->personal(Rol::Preceptor));

        $this->post('/login', ['email' => $alumno->email, 'password' => 'password'])->assertRedirect(route('inicio'));
        $this->assertAuthenticatedAs($alumno);
    }

    public function test_no_se_aprueba_una_cuenta_con_el_correo_sin_verificar(): void
    {
        $alumno = $this->pendiente(['email_verified_at' => null]);

        $this->actingAs($this->personal(Rol::Preceptor))
            ->post(route('alumnos.aprobar', ['alumno' => $alumno]))->assertForbidden();
        $this->assertSame(EstadoUsuario::Pendiente, $alumno->fresh()->estado);
    }

    public function test_no_se_aprueba_una_cuenta_que_ya_esta_activa(): void
    {
        $alumno = User::factory()->alumno()->create();

        $this->actingAs($this->personal(Rol::Preceptor))
            ->post(route('alumnos.aprobar', ['alumno' => $alumno]))->assertForbidden();
    }

    public function test_rechazar_elimina_la_cuenta_pendiente(): void
    {
        $alumno = $this->pendiente();

        $this->actingAs($this->personal(Rol::Administrador))
            ->post(route('alumnos.rechazar', ['alumno' => $alumno]))
            ->assertRedirect(route('alumnos.index'));

        $this->assertModelMissing($alumno);
    }

    public function test_no_se_rechaza_una_cuenta_activa(): void
    {
        $alumno = User::factory()->alumno()->create();

        $this->actingAs($this->personal(Rol::Preceptor))
            ->post(route('alumnos.rechazar', ['alumno' => $alumno]))->assertForbidden();
        $this->assertModelExists($alumno);
    }

    // ---------------------------------------------------- Baja y reactivación

    public function test_desactivar_corta_el_acceso(): void
    {
        $alumno = User::factory()->alumno()->create();

        $this->actingAs($this->personal(Rol::Preceptor))
            ->post(route('alumnos.desactivar', ['alumno' => $alumno]))->assertRedirect();

        $this->assertSame(EstadoUsuario::Inactivo, $alumno->fresh()->estado);

        // Aunque tuviera una sesión abierta, el middleware "activo" lo saca.
        $this->actingAs($alumno->fresh())->get('/inicio')->assertRedirect(route('login'));
    }

    public function test_se_puede_reactivar_una_cuenta_inactiva(): void
    {
        $alumno = User::factory()->alumno()->inactivo()->create();

        $this->actingAs($this->personal(Rol::Preceptor))
            ->post(route('alumnos.reactivar', ['alumno' => $alumno]))->assertRedirect();

        $this->assertSame(EstadoUsuario::Activo, $alumno->fresh()->estado);
    }

    public function test_no_se_desactiva_una_cuenta_inactiva_ni_se_reactiva_una_activa(): void
    {
        $preceptor = $this->personal(Rol::Preceptor);
        $inactivo = User::factory()->alumno()->inactivo()->create();
        $activo = User::factory()->alumno()->create();

        $this->actingAs($preceptor)->post(route('alumnos.desactivar', ['alumno' => $inactivo]))->assertForbidden();
        $this->actingAs($preceptor)->post(route('alumnos.reactivar', ['alumno' => $activo]))->assertForbidden();
    }

    // -------------------------------------------------------------------- Aula

    public function test_preceptoria_cambia_el_aula(): void
    {
        $alumno = User::factory()->alumno(AnioCursada::Primero)->create();

        $this->actingAs($this->personal(Rol::Preceptor))
            ->put(route('alumnos.aula', ['alumno' => $alumno]), ['anio_cursada' => 2])->assertRedirect();

        $this->assertSame(AnioCursada::Segundo, $alumno->fresh()->anio_cursada);
    }

    public function test_el_aula_debe_ser_valida(): void
    {
        $alumno = User::factory()->alumno(AnioCursada::Primero)->create();

        $this->actingAs($this->personal(Rol::Preceptor))
            ->put(route('alumnos.aula', ['alumno' => $alumno]), ['anio_cursada' => 3])
            ->assertSessionHasErrors('anio_cursada');
        $this->assertSame(AnioCursada::Primero, $alumno->fresh()->anio_cursada);
    }

    public function test_un_alumno_no_puede_cambiar_su_propia_aula(): void
    {
        $alumno = User::factory()->alumno(AnioCursada::Primero)->create();

        $this->actingAs($alumno)
            ->put(route('alumnos.aula', ['alumno' => $alumno]), ['anio_cursada' => 2])->assertForbidden();
        $this->assertSame(AnioCursada::Primero, $alumno->fresh()->anio_cursada);
    }

    // ---------------------------------------------- Eliminación (anonimización)

    public function test_eliminar_anonimiza_una_cuenta_inactiva(): void
    {
        $alumno = User::factory()->alumno()->inactivo()->create();

        $this->actingAs($this->personal(Rol::Preceptor))
            ->delete(route('alumnos.destroy', ['alumno' => $alumno]), ['confirmacion' => 'ELIMINAR'])
            ->assertRedirect(route('alumnos.index'));

        $alumno->refresh();
        $this->assertSame('Alumno eliminado', $alumno->nombreCompleto());
        $this->assertNull($alumno->dni);
        $this->assertNull($alumno->email);
        $this->assertNotNull($alumno->anonimizado_en);
        $this->assertSame(EstadoUsuario::Inactivo, $alumno->estado);
    }

    public function test_eliminar_exige_la_segunda_confirmacion(): void
    {
        $alumno = User::factory()->alumno()->inactivo()->create();
        $preceptor = $this->personal(Rol::Preceptor);

        $this->actingAs($preceptor)->delete(route('alumnos.destroy', ['alumno' => $alumno]))
            ->assertSessionHasErrors('confirmacion');
        $this->actingAs($preceptor)->delete(route('alumnos.destroy', ['alumno' => $alumno]), ['confirmacion' => 'si'])
            ->assertSessionHasErrors('confirmacion');

        $this->assertNull($alumno->fresh()->anonimizado_en);
    }

    public function test_solo_se_elimina_una_cuenta_inactiva_y_una_sola_vez(): void
    {
        $preceptor = $this->personal(Rol::Preceptor);
        $activo = User::factory()->alumno()->create();
        $inactivo = User::factory()->alumno()->inactivo()->create();

        $this->actingAs($preceptor)->delete(route('alumnos.destroy', ['alumno' => $activo]), ['confirmacion' => 'ELIMINAR'])
            ->assertForbidden();

        app(GestionAlumnosService::class)->anonimizar($inactivo, $preceptor);

        $this->actingAs($preceptor)->delete(route('alumnos.destroy', ['alumno' => $inactivo->fresh()]), ['confirmacion' => 'ELIMINAR'])
            ->assertForbidden();
    }

    public function test_una_cuenta_anonimizada_no_se_puede_reactivar(): void
    {
        $preceptor = $this->personal(Rol::Preceptor);
        $alumno = User::factory()->alumno()->inactivo()->create();
        app(GestionAlumnosService::class)->anonimizar($alumno, $preceptor);

        $this->actingAs($preceptor)->post(route('alumnos.reactivar', ['alumno' => $alumno->fresh()]))->assertForbidden();
    }

    public function test_el_dni_y_el_correo_de_una_cuenta_eliminada_quedan_libres(): void
    {
        $alumno = User::factory()->alumno()->inactivo()->create(['dni' => '40123456', 'email' => 'libre@example.com']);
        app(GestionAlumnosService::class)->anonimizar($alumno, $this->personal(Rol::Preceptor));

        Notification::fake();
        $this->post('/registro', [
            'nombre' => 'Nuevo', 'apellido' => 'Alumno', 'dni' => '40123456', 'email' => 'libre@example.com',
            'anio_cursada' => 1, 'password' => 'Clave-test1', 'password_confirmation' => 'Clave-test1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['dni' => '40123456', 'email' => 'libre@example.com']);
    }

    // --------------------------------------------------------- Trazabilidad

    public function test_las_operaciones_quedan_en_el_log_sin_datos_personales(): void
    {
        Notification::fake();
        $preceptor = $this->personal(Rol::Preceptor);
        $pendiente = $this->pendiente();
        $activo = User::factory()->alumno()->create();
        Log::spy();
        $servicio = app(GestionAlumnosService::class);

        $servicio->aprobar($pendiente, $preceptor);
        $servicio->desactivar($activo, $preceptor);
        $servicio->reactivar($activo, $preceptor);
        $servicio->desactivar($activo, $preceptor);
        $servicio->anonimizar($activo, $preceptor);

        foreach (['alumno.aprobado' => $pendiente, 'alumno.reactivado' => $activo, 'alumno.eliminado' => $activo] as $evento => $usuario) {
            Log::shouldHaveReceived('info')->with($evento, ['actor' => $preceptor->id, 'usuario' => $usuario->id])->once();
        }
        Log::shouldHaveReceived('info')->with('alumno.desactivado', ['actor' => $preceptor->id, 'usuario' => $activo->id])->twice();
    }

    public function test_consultar_el_perfil_de_un_alumno_queda_registrado(): void
    {
        $alumno = User::factory()->alumno()->create();
        $preceptor = $this->personal(Rol::Preceptor);
        Log::spy();

        $this->actingAs($preceptor)->get(route('alumnos.show', ['alumno' => $alumno]))->assertOk();

        Log::shouldHaveReceived('info')->with('alumno.consultado', ['actor' => $preceptor->id, 'usuario' => $alumno->id])->once();
    }

    // ------------------------------------------------------------ Panel de inicio

    public function test_el_contador_de_pendientes_lleva_a_la_gestion(): void
    {
        $this->pendiente();

        $this->actingAs($this->personal(Rol::Preceptor))->get('/inicio')
            ->assertOk()->assertSee('Revisar registros');
    }
}
