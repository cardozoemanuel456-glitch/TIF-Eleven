<?php

namespace Tests\Feature;

use App\Enums\EstadoUsuario;
use App\Models\User;
use App\Notifications\VerificarCorreoNotification;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** M3-03 y M3-04 · Verificación de correo y estado pendiente (Jira HU-21). */
class VerificacionEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
    }

    private function alumnoSinVerificar(): User
    {
        return User::factory()->alumno()->pendiente()->create(['email_verified_at' => null]);
    }

    private function enlace(User $usuario, ?string $hash = null, int $minutos = 1440): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutos), [
            'id' => $usuario->id,
            'hash' => $hash ?? sha1($usuario->email),
        ]);
    }

    public function test_el_enlace_verifica_el_correo_y_la_cuenta_sigue_pendiente(): void
    {
        $alumno = $this->alumnoSinVerificar();

        $this->get($this->enlace($alumno))
            ->assertRedirect(route('login'))
            ->assertSessionHas('estado');

        $alumno->refresh();
        $this->assertNotNull($alumno->email_verified_at);
        $this->assertSame(EstadoUsuario::Pendiente, $alumno->estado);
    }

    public function test_una_cuenta_pendiente_con_correo_verificado_no_ingresa(): void
    {
        $alumno = $this->alumnoSinVerificar();
        $this->get($this->enlace($alumno));

        $this->post('/login', ['email' => $alumno->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_un_enlace_sin_firma_es_rechazado(): void
    {
        $alumno = $this->alumnoSinVerificar();

        $this->get('/verificar-email/'.$alumno->id.'/'.sha1($alumno->email))->assertForbidden();
        $this->assertNull($alumno->fresh()->email_verified_at);
    }

    public function test_un_enlace_vencido_es_rechazado(): void
    {
        $alumno = $this->alumnoSinVerificar();
        $url = $this->enlace($alumno);

        $this->travel(25)->hours();

        $this->get($url)->assertForbidden();
        $this->assertNull($alumno->fresh()->email_verified_at);
    }

    public function test_un_hash_que_no_coincide_con_el_correo_es_rechazado(): void
    {
        $alumno = $this->alumnoSinVerificar();

        $this->get($this->enlace($alumno, hash: sha1('otro@example.com')))->assertForbidden();
        $this->assertNull($alumno->fresh()->email_verified_at);
    }

    public function test_un_usuario_inexistente_da_404(): void
    {
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => 999999, 'hash' => sha1('x')]);

        $this->get($url)->assertNotFound();
    }

    public function test_el_enlace_del_correo_vence_a_las_24_horas(): void
    {
        $alumno = $this->alumnoSinVerificar();

        $url = (new VerificarCorreoNotification)->urlDeVerificacion($alumno);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $consulta);

        $this->assertEqualsWithDelta(now()->addDay()->timestamp, (int) $consulta['expires'], 5);
    }

    public function test_el_correo_lleva_el_enlace_de_verificacion(): void
    {
        $alumno = $this->alumnoSinVerificar();

        $mensaje = (new VerificarCorreoNotification)->toMail($alumno);

        $this->assertStringContainsString('/verificar-email/'.$alumno->id.'/', $mensaje->actionUrl);
        $this->assertStringContainsString('[Eleven]', $mensaje->subject);
    }
}
