<?php

namespace Tests\Feature;

use App\Enums\AnioCursada;
use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use App\Enums\TipoEvento;
use App\Models\Aviso;
use App\Models\Evento;
use App\Models\User;
use App\Notifications\EventoReprogramadoNotification;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CalendarioEventosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
    }

    // ------------------------------------------------------ Permisos (HU 12-14)

    public function test_todos_los_roles_activos_consultan_el_calendario(): void
    {
        foreach (Rol::cases() as $rol) {
            $usuario = $rol === Rol::Alumno
                ? User::factory()->alumno(AnioCursada::Primero)->create()
                : User::factory()->conRol($rol)->create();

            $this->actingAs($usuario)->get('/calendario')->assertOk();
            $this->actingAs($usuario)->get('/calendario/eventos')->assertOk();
        }
    }

    public function test_alumnos_y_profesores_reciben_403_al_intentar_gestionar_eventos(): void
    {
        $evento = Evento::factory()->create();

        foreach ([
            User::factory()->alumno(AnioCursada::Primero)->create(),
            User::factory()->conRol(Rol::Profesor)->create(),
        ] as $usuarioSinPermiso) {
            $this->actingAs($usuarioSinPermiso)->get('/gestion/eventos')->assertForbidden();
            $this->actingAs($usuarioSinPermiso)->get('/gestion/eventos/crear')->assertForbidden();
            $this->actingAs($usuarioSinPermiso)->post('/gestion/eventos', [
                'titulo' => 'Intento no autorizado',
                'tipo' => TipoEvento::Otro->value,
                'fecha_inicio' => '2026-11-20',
            ])->assertForbidden();
            $this->actingAs($usuarioSinPermiso)->get("/gestion/eventos/{$evento->id}/editar")->assertForbidden();
            $this->actingAs($usuarioSinPermiso)->put("/gestion/eventos/{$evento->id}", [
                'titulo' => 'Modificado',
                'tipo' => TipoEvento::Otro->value,
                'fecha_inicio' => '2026-11-21',
            ])->assertForbidden();
            $this->actingAs($usuarioSinPermiso)->delete("/gestion/eventos/{$evento->id}")->assertForbidden();
        }
    }

    public function test_preceptoria_y_administracion_crean_editan_y_eliminan_eventos(): void
    {
        foreach ([Rol::Preceptor, Rol::Administrador] as $rolAutorizado) {
            $actor = User::factory()->conRol($rolAutorizado)->create();

            $this->actingAs($actor)
                ->post('/gestion/eventos', [
                    'titulo' => 'Feriado institucional '.$rolAutorizado->value,
                    'descripcion' => 'Sin actividad',
                    'tipo' => TipoEvento::Feriado->value,
                    'fecha_inicio' => '2026-11-15',
                    'fecha_fin' => '',
                    'hora' => '',
                    'anio_cursada' => '',
                ])
                ->assertRedirect(route('gestion.eventos.index'));

            $evento = Evento::where('titulo', 'Feriado institucional '.$rolAutorizado->value)->firstOrFail();

            // M2-01: si fecha_fin se omite, toma por defecto fecha_inicio.
            $this->assertSame('2026-11-15', $evento->fecha_fin->format('Y-m-d'));
            $this->assertNull($evento->anio_cursada);

            $this->actingAs($actor)
                ->put("/gestion/eventos/{$evento->id}", [
                    'titulo' => 'Receso actualizado '.$rolAutorizado->value,
                    'descripcion' => 'Dos días',
                    'tipo' => TipoEvento::Receso->value,
                    'fecha_inicio' => '2026-11-15',
                    'fecha_fin' => '2026-11-16',
                    'anio_cursada' => '1',
                ])
                ->assertRedirect(route('gestion.eventos.index'));

            $this->assertSame(AnioCursada::Primero, $evento->fresh()->anio_cursada);

            $this->actingAs($actor)
                ->delete("/gestion/eventos/{$evento->id}")
                ->assertRedirect(route('gestion.eventos.index'));

            $this->assertDatabaseMissing('eventos', ['id' => $evento->id]);
        }
    }

    // --------------------------------------------- Visibilidad por año (M2-02)

    public function test_el_endpoint_json_segmenta_eventos_segun_anio_del_alumno_y_permite_filtrar_al_personal(): void
    {
        $general = Evento::factory()->create([
            'titulo' => 'Inicio ciclo lectivo',
            'tipo' => TipoEvento::InicioClases,
            'anio_cursada' => null,
            'fecha_inicio' => '2026-11-10',
            'fecha_fin' => '2026-11-10',
        ]);
        $primero = Evento::factory()->paraAnio(AnioCursada::Primero)->parcial()->create([
            'titulo' => 'Parcial Matemática I',
            'fecha_inicio' => '2026-11-12',
            'fecha_fin' => '2026-11-12',
        ]);
        $segundo = Evento::factory()->paraAnio(AnioCursada::Segundo)->parcial()->create([
            'titulo' => 'Parcial Base de Datos II',
            'fecha_inicio' => '2026-11-14',
            'fecha_fin' => '2026-11-14',
        ]);

        $alumnoPrimero = User::factory()->alumno(AnioCursada::Primero)->create();
        $profesor = User::factory()->conRol(Rol::Profesor)->create();

        $respuestaAlumno = $this->actingAs($alumnoPrimero)
            ->getJson('/calendario/eventos?start=2026-11-01&end=2026-11-30')
            ->assertOk()
            ->json();

        $idsAlumno = array_column($respuestaAlumno, 'id');
        $this->assertContains((string) $general->id, $idsAlumno);
        $this->assertContains((string) $primero->id, $idsAlumno);
        $this->assertNotContains((string) $segundo->id, $idsAlumno);

        // M2-03: cada ítem incluye color y rótulo de texto del tipo.
        $this->assertNotEmpty($respuestaAlumno[0]['backgroundColor']);
        $this->assertNotEmpty($respuestaAlumno[0]['extendedProps']['tipo_etiqueta']);
        $this->assertFalse($respuestaAlumno[0]['extendedProps']['puede_gestionar']);

        // El personal ve todos y puede filtrar por Segundo año.
        $respuestaProfesorTodos = $this->actingAs($profesor)
            ->getJson('/calendario/eventos?start=2026-11-01&end=2026-11-30')
            ->assertOk()
            ->json();
        $this->assertCount(3, $respuestaProfesorTodos);

        $respuestaProfesorSegundo = $this->actingAs($profesor)
            ->getJson('/calendario/eventos?start=2026-11-01&end=2026-11-30&anio=2')
            ->assertOk()
            ->json();
        $this->assertCount(1, $respuestaProfesorSegundo);
        $this->assertSame((string) $segundo->id, $respuestaProfesorSegundo[0]['id']);
    }

    // ----------------------------------------- Validaciones y reprogramación

    public function test_valida_campos_obligatorios_y_rango_de_fechas_en_eventos(): void
    {
        $admin = User::factory()->conRol(Rol::Administrador)->create();

        $this->actingAs($admin)
            ->post('/gestion/eventos', [
                'titulo' => '',
                'tipo' => 'invalido',
                'fecha_inicio' => '2026-11-20',
                'fecha_fin' => '2026-11-18',
            ])
            ->assertSessionHasErrors(['titulo', 'tipo', 'fecha_fin']);
    }

    public function test_cambiar_fecha_de_parcial_o_mesa_notifica_reprogramacion_y_reinicia_recordatorio(): void
    {
        Notification::fake();

        $preceptor = User::factory()->conRol(Rol::Preceptor)->create();
        $alumnoPrimeroActivo = User::factory()->alumno(AnioCursada::Primero)->create();
        $alumnoPrimeroInactivo = User::factory()->alumno(AnioCursada::Primero)->inactivo()->create();
        $alumnoSegundoActivo = User::factory()->alumno(AnioCursada::Segundo)->create();

        $parcial = Evento::factory()->paraAnio(AnioCursada::Primero)->parcial()->create([
            'titulo' => 'Parcial Programación I',
            'fecha_inicio' => '2026-11-15',
            'fecha_fin' => '2026-11-15',
            'recordado_en' => now()->subHour(),
        ]);

        // Editar sin cambiar fecha_inicio no notifica reprogramación ni borra recordado_en.
        $this->actingAs($preceptor)->put("/gestion/eventos/{$parcial->id}", [
            'titulo' => 'Parcial Programación I (Aula 2)',
            'descripcion' => 'Traer DNI',
            'tipo' => TipoEvento::Parcial->value,
            'fecha_inicio' => '2026-11-15',
            'fecha_fin' => '2026-11-15',
            'anio_cursada' => '1',
        ]);
        Notification::assertNothingSent();
        $this->assertNotNull($parcial->fresh()->recordado_en);

        // Cambiar fecha_inicio sí reinicia recordado_en y notifica solo a activos de 1.° año.
        $this->actingAs($preceptor)->put("/gestion/eventos/{$parcial->id}", [
            'titulo' => 'Parcial Programación I (Aula 2)',
            'descripcion' => 'Traer DNI',
            'tipo' => TipoEvento::Parcial->value,
            'fecha_inicio' => '2026-11-22',
            'fecha_fin' => '2026-11-22',
            'anio_cursada' => '1',
        ]);

        $this->assertNull($parcial->fresh()->recordado_en);
        Notification::assertSentTo($alumnoPrimeroActivo, EventoReprogramadoNotification::class);
        Notification::assertNotSentTo($alumnoPrimeroInactivo, EventoReprogramadoNotification::class);
        Notification::assertNotSentTo($alumnoSegundoActivo, EventoReprogramadoNotification::class);
    }

    public function test_eliminar_evento_no_notifica_y_desvincula_avisos_asociados(): void
    {
        Notification::fake();

        $admin = User::factory()->conRol(Rol::Administrador)->create();
        User::factory()->alumno(AnioCursada::Primero)->create();

        $evento = Evento::factory()->mesaExamen()->create();
        $aviso = Aviso::factory()->create(['evento_id' => $evento->id]);

        $this->actingAs($admin)
            ->delete("/gestion/eventos/{$evento->id}")
            ->assertRedirect(route('gestion.eventos.index'));

        Notification::assertNothingSent();
        $this->assertNull($aviso->fresh()->evento_id);
    }
}

