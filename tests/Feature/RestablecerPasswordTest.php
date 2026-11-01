<?php

namespace Tests\Feature;

use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use App\Notifications\RestablecerPasswordNotification;
use Tests\Concerns\CreaUsuarios;
use Tests\TestCase;

class RestablecerPasswordTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    public function test_las_pantallas_se_muestran(): void
    {
        $this->get('/olvide-password')->assertOk()->assertSee('Recuperar contraseña');
        $this->get('/restablecer-password/token-cualquiera?email=a@b.com')->assertOk()->assertSee('contraseña nueva');
        $this->get('/login')->assertSee('¿Olvidaste tu contraseña?');
    }

    public function test_una_cuenta_activa_recibe_el_enlace(): void
    {
        Notification::fake();
        $u = $this->crearUsuario(Rol::Profesor);

        $this->post('/olvide-password', ['email' => strtoupper($u->email)])->assertSessionHas('estado');

        Notification::assertSentTo($u, RestablecerPasswordNotification::class);
    }

    public function test_un_correo_inexistente_recibe_la_misma_respuesta_y_no_se_envia_nada(): void
    {
        Notification::fake();

        $this->post('/olvide-password', ['email' => 'nadie@example.com'])
            ->assertSessionHas('estado')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_una_cuenta_inactiva_o_pendiente_no_recibe_el_enlace(): void
    {
        Notification::fake();
        $inactiva = $this->crearUsuario(Rol::Profesor, EstadoUsuario::Inactivo);
        $pendiente = $this->crearUsuario(Rol::Alumno, EstadoUsuario::Pendiente);

        $this->post('/olvide-password', ['email' => $inactiva->email])->assertSessionHas('estado');
        $this->post('/olvide-password', ['email' => $pendiente->email])->assertSessionHas('estado');

        Notification::assertNothingSent();
    }

    public function test_el_enlace_cambia_la_clave_y_es_de_un_solo_uso(): void
    {
        $u = $this->crearUsuario(Rol::Profesor, debeCambiar: true);
        $token = Password::createToken($u);
        $datos = [
            'token' => $token,
            'email' => $u->email,
            'password' => 'Clave-nueva9',
            'password_confirmation' => 'Clave-nueva9',
        ];

        $this->post('/restablecer-password', $datos)->assertRedirect(route('login'));

        $u = $u->fresh();
        $this->assertTrue(Hash::check('Clave-nueva9', $u->password));
        $this->assertFalse($u->debe_cambiar_password);

        // El mismo enlace no sirve una segunda vez.
        $this->post('/restablecer-password', array_merge($datos, ['password' => 'Otra-clave7', 'password_confirmation' => 'Otra-clave7']))
            ->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('Clave-nueva9', $u->fresh()->password));
    }

    public function test_un_token_invalido_se_rechaza(): void
    {
        $u = $this->crearUsuario(Rol::Profesor);

        $this->post('/restablecer-password', [
            'token' => 'falso',
            'email' => $u->email,
            'password' => 'Clave-nueva9',
            'password_confirmation' => 'Clave-nueva9',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('Clave-test1', $u->fresh()->password));
    }

    public function test_una_clave_debil_se_rechaza(): void
    {
        $u = $this->crearUsuario(Rol::Profesor);

        $this->post('/restablecer-password', [
            'token' => Password::createToken($u),
            'email' => $u->email,
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ])->assertSessionHasErrors('password');
    }

    public function test_el_enlace_no_sirve_para_una_cuenta_desactivada(): void
    {
        $u = $this->crearUsuario(Rol::Profesor, EstadoUsuario::Inactivo);

        $this->post('/restablecer-password', [
            'token' => Password::createToken($u),
            'email' => $u->email,
            'password' => 'Clave-nueva9',
            'password_confirmation' => 'Clave-nueva9',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('Clave-test1', $u->fresh()->password));
    }
}