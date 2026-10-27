<?php

namespace Tests\Feature;

use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_invitado_es_enviado_al_login(): void
    {
        $this->get('/inicio')->assertRedirect(route('login'));
    }

    public function test_la_pantalla_de_login_se_muestra(): void
    {
        $this->get('/login')->assertOk()->assertSee('Ingresar');
    }

    public function test_un_usuario_activo_ingresa_y_ve_su_inicio(): void
    {
        $usuario = User::factory()->conRol(Rol::Preceptor)->create([
            'email' => 'preceptor@eleven.test',
            'nombre' => 'Marta',
        ]);

        $this->post('/login', ['email' => 'preceptor@eleven.test', 'password' => 'password'])
            ->assertRedirect(route('inicio'));

        $this->assertAuthenticatedAs($usuario);
        $this->get('/inicio')->assertOk()->assertSee('Hola, Marta');
    }

    public function test_las_credenciales_incorrectas_no_ingresan(): void
    {
        User::factory()->create(['email' => 'alumno@eleven.test']);

        $this->post('/login', ['email' => 'alumno@eleven.test', 'password' => 'incorrecta'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_un_correo_inexistente_recibe_el_mismo_mensaje(): void
    {
        $this->post('/login', ['email' => 'nadie@eleven.test', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Las credenciales no son correctas.']);

        $this->assertGuest();
    }

    public function test_una_cuenta_pendiente_no_ingresa_y_ve_el_motivo(): void
    {
        User::factory()->alumno()->pendiente()->create(['email' => 'nuevo@eleven.test']);

        $this->post('/login', ['email' => 'nuevo@eleven.test', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => EstadoUsuario::Pendiente->mensajeDeAcceso()]);

        $this->assertGuest();
    }

    public function test_una_cuenta_inactiva_no_ingresa_y_ve_el_motivo(): void
    {
        User::factory()->alumno()->inactivo()->create(['email' => 'baja@eleven.test']);

        $this->post('/login', ['email' => 'baja@eleven.test', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => EstadoUsuario::Inactivo->mensajeDeAcceso()]);

        $this->assertGuest();
    }

    public function test_no_se_revela_el_estado_de_la_cuenta_sin_la_contrasena_correcta(): void
    {
        User::factory()->alumno()->inactivo()->create(['email' => 'baja@eleven.test']);

        $this->post('/login', ['email' => 'baja@eleven.test', 'password' => 'incorrecta'])
            ->assertSessionHasErrors(['email' => 'Las credenciales no son correctas.']);
    }

    public function test_se_bloquea_el_login_tras_cinco_intentos_fallidos(): void
    {
        User::factory()->create(['email' => 'alumno@eleven.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'alumno@eleven.test', 'password' => 'incorrecta']);
        }

        // Aun con la contraseña correcta, el sexto intento queda bloqueado.
        $this->post('/login', ['email' => 'alumno@eleven.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_un_ingreso_correcto_reinicia_el_contador_de_intentos(): void
    {
        $usuario = User::factory()->create(['email' => 'alumno@eleven.test']);

        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', ['email' => 'alumno@eleven.test', 'password' => 'incorrecta']);
        }

        $this->post('/login', ['email' => 'alumno@eleven.test', 'password' => 'password'])
            ->assertRedirect(route('inicio'));
        $this->assertAuthenticatedAs($usuario);

        $this->post('/logout');

        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', ['email' => 'alumno@eleven.test', 'password' => 'incorrecta']);
        }

        $this->post('/login', ['email' => 'alumno@eleven.test', 'password' => 'password'])
            ->assertRedirect(route('inicio'));
    }

    public function test_el_logout_cierra_la_sesion(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_una_cuenta_desactivada_pierde_el_acceso_en_la_siguiente_peticion(): void
    {
        $usuario = User::factory()->conRol(Rol::Preceptor)->create();

        $this->actingAs($usuario)->get('/inicio')->assertOk();

        $usuario->forceFill(['estado' => EstadoUsuario::Inactivo])->save();

        $this->get('/inicio')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => EstadoUsuario::Inactivo->mensajeDeAcceso()]);

        $this->assertGuest();
    }

    public function test_una_cuenta_desactivada_igual_puede_cerrar_sesion(): void
    {
        $usuario = User::factory()->inactivo()->create();

        $this->actingAs($usuario)->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
