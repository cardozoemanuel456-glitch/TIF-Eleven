<?php

namespace Tests\Feature;

use App\Enums\AnioCursada;
use App\Enums\Rol;
use App\Models\Aviso;
use App\Models\Evento;
use App\Models\NotificacionProgramada;
use App\Models\User;
use App\Notifications\AvisoPublicadoNotification;
use App\Notifications\NotificacionInstitucionalNotification;
use App\Notifications\RecordatorioEventoNotification;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AvisosNotificacionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
    }

    // --------------------------------------------------- Permisos y visibilidad

    public function test_matriz_de_permisos_en_gestion_de_avisos_y_notificaciones_programadas(): void
    {
        $aviso = Aviso::factory()->borrador()->create();

        foreach ([
            User::factory()->alumno(AnioCursada::Primero)->create(),
            User::factory()->conRol(Rol::Profesor)->create(),
        ] as $sinPermiso) {
            $this->actingAs($sinPermiso)->get('/gestion/avisos')->assertForbidden();
            $this->actingAs($sinPermiso)->post('/gestion/avisos', [
                'titulo' => 'Aviso bloqueado',
                'contenido' => 'Contenido',
                'modo_publicacion' => 'publicar',
            ])->assertForbidden();
            $this->actingAs($sinPermiso)->get("/avisos/{$aviso->id}")->assertForbidden();
            $this->actingAs($sinPermiso)->get('/gestion/notificaciones')->assertForbidden();
            $this->actingAs($sinPermiso)->post('/gestion/notificaciones', [
                'titulo' => 'Recordatorio',
                'contenido' => 'Texto',
                'programada_para' => now()->addHour()->format('Y-m-d\TH:i'),
            ])->assertForbidden();
        }
    }

    public function test_alumno_solo_ve_avisos_publicados_generales_o_de_su_anio_paginados_de_a_diez(): void
    {
        $alumnoPrimero = User::factory()->alumno(AnioCursada::Primero)->create();

        $general = Aviso::factory()->create([
            'titulo' => 'Aviso General Visible',
            'anio_cursada' => null,
            'publicado_en' => now()->subMinutes(2),
        ]);
        $primero = Aviso::factory()->paraAnio(AnioCursada::Primero)->create([
            'titulo' => 'Aviso Primer Año Visible',
            'publicado_en' => now()->subMinute(),
        ]);
        $segundo = Aviso::factory()->paraAnio(AnioCursada::Segundo)->create([
            'titulo' => 'Aviso Segundo Año Oculto',
        ]);
        $borrador = Aviso::factory()->borrador()->create([
            'titulo' => 'Borrador General Oculto',
        ]);

        // Creamos 9 avisos adicionales para verificar paginación de 10 en 10 (total visibles = 11).
        Aviso::factory()->count(9)->create([
            'anio_cursada' => null,
            'publicado_en' => now()->subHours(2),
        ]);

        $this->actingAs($alumnoPrimero)
            ->get('/avisos')
            ->assertOk()
            ->assertSee($general->titulo)
            ->assertSee($primero->titulo)
            ->assertSee('Sin leer')
            ->assertDontSee($segundo->titulo)
            ->assertDontSee($borrador->titulo);

        $this->actingAs($alumnoPrimero)->get('/avisos?page=2')->assertOk();
        $this->actingAs($alumnoPrimero)->get("/avisos/{$segundo->id}")->assertForbidden();
        $this->actingAs($alumnoPrimero)->get("/avisos/{$borrador->id}")->assertForbidden();

        // Al abrir el aviso se marca como leído.
        $this->actingAs($alumnoPrimero)->get("/avisos/{$primero->id}")->assertOk();
        $this->assertDatabaseHas('aviso_lecturas', [
            'aviso_id' => $primero->id,
            'user_id' => $alumnoPrimero->id,
        ]);
    }

    // ------------------------------ Publicación, edición y escapado HTML (M2-06/07)

    public function test_publicar_aviso_notifica_una_sola_vez_y_editarlo_no_repite_notificaciones(): void
    {
        Notification::fake();

        $admin = User::factory()->conRol(Rol::Administrador)->create();
        $alumnoPrimero = User::factory()->alumno(AnioCursada::Primero)->create();
        $alumnoSegundo = User::factory()->alumno(AnioCursada::Segundo)->create();
        $alumnoPendiente = User::factory()->alumno(AnioCursada::Primero)->pendiente()->create();

        // 1) Guardar como borrador no envía notificaciones.
        $this->actingAs($admin)->post('/gestion/avisos', [
            'titulo' => 'Cambio de horario laboratorio',
            'contenido' => 'Pasamos a las 19:00 hs.',
            'anio_cursada' => '1',
            'modo_publicacion' => 'borrador',
        ])->assertRedirect(route('gestion.avisos.index'));

        Notification::assertNothingSent();

        $aviso = Aviso::where('titulo', 'Cambio de horario laboratorio')->firstOrFail();
        $this->assertTrue($aviso->esBorrador());

        // 2) Publicar el borrador envía exactamente una notificación a alumnos activos de 1.° año.
        $this->actingAs($admin)
            ->post("/gestion/avisos/{$aviso->id}/publicar")
            ->assertRedirect(route('gestion.avisos.index'));

        Notification::assertSentToTimes($alumnoPrimero, AvisoPublicadoNotification::class, 1);
        Notification::assertNotSentTo($alumnoSegundo, AvisoPublicadoNotification::class);
        Notification::assertNotSentTo($alumnoPendiente, AvisoPublicadoNotification::class);

        // 3) Editar el aviso ya publicado registra fecha de actualización y NO vuelve a notificar.
        $this->actingAs($admin)->put("/gestion/avisos/{$aviso->id}", [
            'titulo' => 'Cambio de horario laboratorio (confirmado)',
            'contenido' => 'Pasamos a las 19:30 hs.',
            'anio_cursada' => '1',
        ])->assertRedirect(route('gestion.avisos.index'));

        Notification::assertSentToTimes($alumnoPrimero, AvisoPublicadoNotification::class, 1);
        $this->assertTrue($aviso->fresh()->fueEditado());

        $this->actingAs($alumnoPrimero)
            ->get("/avisos/{$aviso->id}")
            ->assertOk()
            ->assertSee('Actualizado el');
    }

    public function test_aviso_escapa_html_como_texto_y_convierte_enlaces_web(): void
    {
        $alumno = User::factory()->alumno()->create();
        $aviso = Aviso::factory()->create([
            'contenido' => 'Atención <script>alert("xss")</script> <b>importante</b> visitar https://utn.edu.ar/formosa.',
        ]);

        $respuesta = $this->actingAs($alumno)->get("/avisos/{$aviso->id}")->assertOk();

        $html = $respuesta->getContent();
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;importante&lt;/b&gt;', $html);
        $this->assertStringContainsString('<a href="https://utn.edu.ar/formosa"', $html);
    }

    // ------------------- Campana, preferencia de email y aviso eliminado (M2-09..14)

    public function test_desactivar_email_mantiene_la_campana_y_el_correo_cumple_formato_sin_datos_personales(): void
    {
        Notification::fake();

        $preceptor = User::factory()->conRol(Rol::Preceptor)->create();
        $conEmail = User::factory()->alumno(AnioCursada::Primero)->create([
            'nombre' => 'Valentina',
            'dni' => '42999888',
            'notificar_email' => true,
        ]);
        $sinEmail = User::factory()->alumno(AnioCursada::Primero)->create([
            'notificar_email' => true,
        ]);

        // El usuario desactiva correo desde su cuenta (M2-11).
        $this->actingAs($sinEmail)
            ->put('/cuenta/notificaciones', ['notificar_email' => '0'])
            ->assertRedirect();

        $this->assertFalse($sinEmail->fresh()->notificar_email);

        $textoLargo = str_repeat('Información académica relevante de la subsede. ', 10);

        $this->actingAs($preceptor)->post('/gestion/avisos', [
            'titulo' => 'Cronograma de finales',
            'contenido' => $textoLargo,
            'anio_cursada' => '1',
            'modo_publicacion' => 'publicar',
        ]);

        Notification::assertSentTo($conEmail, AvisoPublicadoNotification::class, function (AvisoPublicadoNotification $n, array $canales) use ($conEmail): bool {
            $this->assertEqualsCanonicalizing(['database', 'mail'], $canales);
            $this->assertSame(3, $n->tries);

            $correo = $n->toMail($conEmail);
            $this->assertSame('[Eleven] Cronograma de finales', $correo->subject);
            $this->assertSame('Ver en Eleven', $correo->actionText);
            $this->assertLessThanOrEqual(300, mb_strlen($correo->introLines[0]));
            $this->assertStringNotContainsString('Valentina', implode(' ', $correo->introLines));
            $this->assertStringNotContainsString('42999888', implode(' ', $correo->introLines));

            return true;
        });

        Notification::assertSentTo($sinEmail, AvisoPublicadoNotification::class, function (AvisoPublicadoNotification $n, array $canales): bool {
            $this->assertSame(['database'], $canales);

            return true;
        });
    }

    public function test_campana_marca_notificaciones_leidas_y_muestra_aviso_no_disponible_si_se_elimina(): void
    {
        $admin = User::factory()->conRol(Rol::Administrador)->create();
        $alumno = User::factory()->alumno(AnioCursada::Primero)->create(['notificar_email' => false]);

        $this->actingAs($admin)->post('/gestion/avisos', [
            'titulo' => 'Aviso temporal',
            'contenido' => 'Detalle temporal',
            'anio_cursada' => '1',
            'modo_publicacion' => 'publicar',
        ]);

        $aviso = Aviso::where('titulo', 'Aviso temporal')->firstOrFail();
        $this->assertSame(1, $alumno->unreadNotifications()->count());

        // M2-14: al eliminar el aviso, la notificación muestra «Aviso no disponible».
        $this->actingAs($admin)->delete("/gestion/avisos/{$aviso->id}");

        $this->actingAs($alumno)
            ->get('/notificaciones')
            ->assertOk()
            ->assertSee('Aviso no disponible')
            ->assertDontSee('Aviso temporal');

        // Marcar todas como leídas deja el contador en cero.
        $this->actingAs($alumno)
            ->post('/notificaciones/leer')
            ->assertRedirect();

        $this->assertSame(0, $alumno->fresh()->unreadNotifications()->count());
    }

    // ----------------------- Recordatorios diarios y programación (M2-13, HU 16, HU 17)

    public function test_comando_eventos_recordar_envia_recordatorios_de_hoy_y_maniana_sin_duplicar(): void
    {
        Notification::fake();

        $alumnoPrimero = User::factory()->alumno(AnioCursada::Primero)->create();
        $alumnoSegundo = User::factory()->alumno(AnioCursada::Segundo)->create();

        $parcialManiana = Evento::factory()->paraAnio(AnioCursada::Primero)->parcial()->create([
            'titulo' => 'Parcial Arquitectura',
            'fecha_inicio' => today()->addDay()->toDateString(),
            'fecha_fin' => today()->addDay()->toDateString(),
        ]);

        $mesaHoyRezago = Evento::factory()->mesaExamen()->create([
            'titulo' => 'Mesa Inglés Técnico',
            'anio_cursada' => null,
            'fecha_inicio' => today()->toDateString(),
            'fecha_fin' => today()->toDateString(),
        ]);

        $feriadoManiana = Evento::factory()->create([
            'titulo' => 'Feriado puente',
            'fecha_inicio' => today()->addDay()->toDateString(),
            'fecha_fin' => today()->addDay()->toDateString(),
        ]);

        $this->artisan('eventos:recordar')->assertSuccessful();

        $this->assertNotNull($parcialManiana->fresh()->recordado_en);
        $this->assertNotNull($mesaHoyRezago->fresh()->recordado_en);
        $this->assertNull($feriadoManiana->fresh()->recordado_en);

        // Alumno de 1.° año recibe ambos (el parcial de 1.° y la mesa general); el de 2.° solo la mesa general.
        Notification::assertSentToTimes($alumnoPrimero, RecordatorioEventoNotification::class, 2);
        Notification::assertSentToTimes($alumnoSegundo, RecordatorioEventoNotification::class, 1);

        // Ejecutar el comando una segunda vez no duplica recordatorios.
        $this->artisan('eventos:recordar')->assertSuccessful();
        Notification::assertSentToTimes($alumnoPrimero, RecordatorioEventoNotification::class, 2);
    }

    public function test_programacion_de_avisos_y_notificaciones_se_valida_y_se_despacha_en_fecha(): void
    {
        Notification::fake();

        $admin = User::factory()->conRol(Rol::Administrador)->create();
        $alumnoSegundo = User::factory()->alumno(AnioCursada::Segundo)->create();

        // Fecha pasada o inválida rechazada (HU 16).
        $this->actingAs($admin)
            ->post('/gestion/notificaciones', [
                'titulo' => '',
                'contenido' => 'Falta título y fecha pasada',
                'programada_para' => now()->subHour()->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasErrors(['titulo', 'programada_para']);

        // Programación válida vinculada a Segundo año.
        $this->actingAs($admin)
            ->post('/gestion/notificaciones', [
                'titulo' => 'Recordatorio entrega de carpeta',
                'contenido' => 'Traer carpeta firmada mañana.',
                'anio_cursada' => '2',
                'programada_para' => now()->addMinutes(30)->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('gestion.notificaciones.index'))
            ->assertSessionHas('estado');

        $programada = NotificacionProgramada::firstOrFail();
        Notification::assertNothingSent();

        // Avanzamos el reloj al momento programado y ejecutamos el despachador.
        $this->travel(35)->minutes();
        $this->artisan('avisos:despachar-programados')->assertSuccessful();

        $this->assertNotNull($programada->fresh()->enviada_en);
        Notification::assertSentTo($alumnoSegundo, NotificacionInstitucionalNotification::class);
    }

    public function test_panel_de_inicio_muestra_avisos_y_proximos_eventos_segun_permisos_o_mensajes_vacios(): void
    {
        $alumnoPrimero = User::factory()->alumno(AnioCursada::Primero)->create();

        // Estado vacío informativo (HU 17).
        $this->actingAs($alumnoPrimero)
            ->get('/inicio')
            ->assertOk()
            ->assertSee('Todavía no hay avisos publicados para mostrar.')
            ->assertSee('No hay eventos próximos programados.');

        Aviso::factory()->paraAnio(AnioCursada::Primero)->create(['titulo' => 'Aviso Inicio Primero']);
        Aviso::factory()->paraAnio(AnioCursada::Segundo)->create(['titulo' => 'Aviso Inicio Segundo']);
        Evento::factory()->paraAnio(AnioCursada::Primero)->parcial()->create([
            'titulo' => 'Evento Inicio Primero',
            'fecha_inicio' => today()->addDays(3)->toDateString(),
            'fecha_fin' => today()->addDays(3)->toDateString(),
        ]);

        $this->actingAs($alumnoPrimero)
            ->get('/inicio')
            ->assertOk()
            ->assertSee('Aviso Inicio Primero')
            ->assertDontSee('Aviso Inicio Segundo')
            ->assertSee('Evento Inicio Primero');
    }
}

