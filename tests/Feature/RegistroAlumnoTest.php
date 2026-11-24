<?php

namespace Tests\Feature;

use App\Enums\AnioCursada;
use App\Enums\EstadoUsuario;
use App\Models\User;
use App\Notifications\VerificarCorreoNotification;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** M3-01, M3-02 y M3-05 · Registro público (Jira HU-19 y HU-20). */
class RegistroAlumnoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Santiago',
            'apellido' => 'Benítez',
            'dni' => '40123456',
            'email' => 'santiago@example.com',
            'anio_cursada' => 2,
            'password' => 'Clave-test1',
            'password_confirmation' => 'Clave-test1',
        ], $extra);
    }

    public function test_se_puede_ver_el_formulario_de_registro(): void
    {
        $this->get('/registro')->assertOk()->assertSee('Registro de alumnos');
    }

    public function test_un_usuario_con_sesion_no_ve_el_registro(): void
    {
        $this->actingAs(User::factory()->create())->get('/registro')->assertStatus(302);
    }

    public function test_un_registro_valido_crea_un_alumno_pendiente_sin_verificar(): void
    {
        Notification::fake();

        $this->post('/registro', $this->datos())
            ->assertRedirect(route('login'))
            ->assertSessionHas('estado');

        $alumno = User::where('dni', '40123456')->first();

        $this->assertNotNull($alumno);
        $this->assertTrue($alumno->esAlumno());
        $this->assertSame(EstadoUsuario::Pendiente, $alumno->estado);
        $this->assertSame(AnioCursada::Segundo, $alumno->anio_cursada);
        $this->assertNull($alumno->email_verified_at);
        $this->assertTrue(Hash::check('Clave-test1', $alumno->password));
        Notification::assertSentTo($alumno, VerificarCorreoNotification::class);
    }

    public function test_el_dni_se_acepta_con_puntos_y_el_correo_se_guarda_en_minusculas(): void
    {
        Notification::fake();

        $this->post('/registro', $this->datos(['dni' => '40.123.456', 'email' => 'Santiago@Example.COM']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['dni' => '40123456', 'email' => 'santiago@example.com']);
    }

    public function test_el_dni_debe_tener_7_u_8_digitos(): void
    {
        foreach (['123456', '123456789', 'abcdefgh', ''] as $dni) {
            $this->post('/registro', $this->datos(['dni' => $dni]))->assertSessionHasErrors('dni');
        }
    }

    public function test_el_dni_y_el_correo_no_se_pueden_repetir(): void
    {
        User::factory()->alumno()->create(['dni' => '40123456', 'email' => 'santiago@example.com']);

        $this->post('/registro', $this->datos())->assertSessionHasErrors(['dni', 'email']);
        $this->assertSame(1, User::where('dni', '40123456')->count());
    }

    public function test_el_correo_debe_ser_valido(): void
    {
        $this->post('/registro', $this->datos(['email' => 'no-es-un-correo']))->assertSessionHasErrors('email');
    }

    public function test_la_contrasena_debe_ser_segura_y_estar_confirmada(): void
    {
        $this->post('/registro', $this->datos(['password' => 'corta1', 'password_confirmation' => 'corta1']))
            ->assertSessionHasErrors('password');
        $this->post('/registro', $this->datos(['password' => 'soloLetrasAqui', 'password_confirmation' => 'soloLetrasAqui']))
            ->assertSessionHasErrors('password');
        $this->post('/registro', $this->datos(['password_confirmation' => 'Otra-clave2']))
            ->assertSessionHasErrors('password');
    }

    public function test_el_aula_debe_ser_valida(): void
    {
        $this->post('/registro', $this->datos(['anio_cursada' => 3]))->assertSessionHasErrors('anio_cursada');
        $this->post('/registro', $this->datos(['anio_cursada' => '']))->assertSessionHasErrors('anio_cursada');
    }

    public function test_el_formulario_no_puede_asignar_rol_ni_estado(): void
    {
        Notification::fake();

        $this->post('/registro', $this->datos(['role_id' => 1, 'estado' => 'activo', 'aprobado_por' => 1, 'debe_cambiar_password' => 0]));

        $alumno = User::where('dni', '40123456')->first();

        $this->assertTrue($alumno->esAlumno());
        $this->assertSame(EstadoUsuario::Pendiente, $alumno->estado);
        $this->assertNull($alumno->aprobado_por);
    }

    public function test_el_alumno_no_puede_ingresar_hasta_ser_aprobado(): void
    {
        Notification::fake();
        $this->post('/registro', $this->datos());

        $this->post('/login', ['email' => 'santiago@example.com', 'password' => 'Clave-test1'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_el_registro_tiene_limite_de_intentos(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/registro', []);
        }

        $this->post('/registro', [])->assertStatus(429);
    }
}
