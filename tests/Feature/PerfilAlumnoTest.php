<?php

namespace Tests\Feature;

use App\Enums\AnioCursada;
use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use App\Models\User;
use App\Notifications\VerificarCorreoNotification;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** M3-09 · Perfil propio del alumno. */
class PerfilAlumnoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
    }

    public function test_un_invitado_es_enviado_al_login(): void
    {
        $this->get('/mi-perfil')->assertRedirect(route('login'));
    }

    public function test_el_alumno_ve_su_perfil(): void
    {
        $alumno = User::factory()->alumno()->create(['nombre' => 'Lucia']);

        $this->actingAs($alumno)->get('/mi-perfil')->assertOk()->assertSee('Mi perfil')->assertSee('Lucia');
    }

    public function test_el_personal_no_tiene_perfil_de_alumno(): void
    {
        $this->actingAs(User::factory()->conRol(Rol::Preceptor)->create())->get('/mi-perfil')->assertForbidden();
    }

    public function test_el_alumno_edita_su_nombre_y_apellido(): void
    {
        Notification::fake();
        $alumno = User::factory()->alumno()->create();

        $this->actingAs($alumno)->put('/mi-perfil', ['nombre' => 'Ana', 'apellido' => 'Perez', 'email' => $alumno->email])
            ->assertRedirect()->assertSessionHas('estado');

        $alumno->refresh();
        $this->assertSame('Ana', $alumno->nombre);
        $this->assertSame('Perez', $alumno->apellido);
        Notification::assertNothingSent();
    }

    public function test_el_dni_el_aula_el_estado_y_el_rol_no_se_pueden_cambiar(): void
    {
        $alumno = User::factory()->alumno(AnioCursada::Primero)->create(['dni' => '40123456']);
        $rolOriginal = $alumno->role_id;

        $this->actingAs($alumno)->put('/mi-perfil', [
            'nombre' => 'Ana', 'apellido' => 'Perez', 'email' => $alumno->email,
            'dni' => '99999999', 'anio_cursada' => 2, 'estado' => 'inactivo', 'role_id' => 1,
        ]);

        $alumno->refresh();
        $this->assertSame('40123456', $alumno->dni);
        $this->assertSame(AnioCursada::Primero, $alumno->anio_cursada);
        $this->assertSame(EstadoUsuario::Activo, $alumno->estado);
        $this->assertSame($rolOriginal, $alumno->role_id);
    }

    public function test_si_cambia_el_correo_debe_verificarlo_de_nuevo(): void
    {
        Notification::fake();
        $alumno = User::factory()->alumno()->create();

        $this->actingAs($alumno)->put('/mi-perfil', ['nombre' => $alumno->nombre, 'apellido' => $alumno->apellido, 'email' => 'nuevo@example.com']);

        $alumno->refresh();
        $this->assertSame('nuevo@example.com', $alumno->email);
        $this->assertNull($alumno->email_verified_at);
        Notification::assertSentTo($alumno, VerificarCorreoNotification::class);
    }

    public function test_no_puede_usar_el_correo_de_otra_cuenta(): void
    {
        User::factory()->create(['email' => 'ocupado@example.com']);
        $alumno = User::factory()->alumno()->create();

        $this->actingAs($alumno)->put('/mi-perfil', ['nombre' => 'Ana', 'apellido' => 'Perez', 'email' => 'ocupado@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_los_datos_son_obligatorios(): void
    {
        $alumno = User::factory()->alumno()->create();

        $this->actingAs($alumno)->put('/mi-perfil', ['nombre' => '', 'apellido' => '', 'email' => ''])
            ->assertSessionHasErrors(['nombre', 'apellido', 'email']);
    }
}
