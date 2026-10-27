<?php

namespace Tests\Feature;

use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CambioPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(bool $debeCambiar): User
    {
        $rol = Role::firstOrCreate(['nombre' => Rol::Preceptor->value]);

        $usuario = new User;
        $usuario->forceFill([
            'role_id' => $rol->id,
            'nombre' => 'Ana',
            'apellido' => 'Perez',
            'email' => 'ana@example.com',
            'password' => 'Clave-vieja1',
            'estado' => EstadoUsuario::Activo,
            'debe_cambiar_password' => $debeCambiar,
            'email_verified_at' => now(),
        ])->save();

        return $usuario;
    }

    public function test_un_invitado_es_enviado_al_login_desde_el_cambio_de_clave(): void
    {
        $this->get('/cuenta/password')->assertRedirect(route('login'));
    }

    public function test_quien_debe_cambiar_la_clave_es_redirigido_desde_inicio(): void
    {
        $this->actingAs($this->usuario(true))
            ->get('/inicio')
            ->assertRedirect(route('password.cambiar'));
    }

    public function test_quien_debe_cambiar_la_clave_ve_la_pantalla_de_cambio(): void
    {
        $this->actingAs($this->usuario(true))
            ->get('/cuenta/password')
            ->assertOk()
            ->assertSee('contraseña temporal');
    }

    public function test_quien_no_debe_cambiarla_entra_a_inicio(): void
    {
        $this->actingAs($this->usuario(false))->get('/inicio')->assertOk();
    }

    public function test_cambia_la_clave_y_se_libera_el_acceso(): void
    {
        $usuario = $this->usuario(true);

        $this->actingAs($usuario)
            ->put('/cuenta/password', [
                'password_actual' => 'Clave-vieja1',
                'password' => 'Clave-nueva9',
                'password_confirmation' => 'Clave-nueva9',
            ])
            ->assertRedirect(route('inicio'));

        $usuario->refresh();
        $this->assertFalse($usuario->debe_cambiar_password);
        $this->assertTrue(Hash::check('Clave-nueva9', $usuario->password));
        $this->actingAs($usuario)->get('/inicio')->assertOk();
    }

    public function test_rechaza_una_clave_actual_incorrecta(): void
    {
        $usuario = $this->usuario(true);

        $this->actingAs($usuario)
            ->from('/cuenta/password')
            ->put('/cuenta/password', [
                'password_actual' => 'otra-cosa',
                'password' => 'Clave-nueva9',
                'password_confirmation' => 'Clave-nueva9',
            ])
            ->assertSessionHasErrors('password_actual');

        $this->assertTrue($usuario->fresh()->debe_cambiar_password);
    }

    public function test_rechaza_repetir_la_clave_actual(): void
    {
        $this->actingAs($this->usuario(true))
            ->put('/cuenta/password', [
                'password_actual' => 'Clave-vieja1',
                'password' => 'Clave-vieja1',
                'password_confirmation' => 'Clave-vieja1',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_rechaza_una_clave_debil_o_sin_confirmar(): void
    {
        $this->actingAs($this->usuario(true))
            ->put('/cuenta/password', [
                'password_actual' => 'Clave-vieja1',
                'password' => 'corta',
                'password_confirmation' => 'distinta',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_quien_debe_cambiar_la_clave_igual_puede_cerrar_sesion(): void
    {
        $this->actingAs($this->usuario(true))
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}