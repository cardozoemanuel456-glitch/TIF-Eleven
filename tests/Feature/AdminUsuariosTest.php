<?php

namespace Tests\Feature;

use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use App\Models\User;
use App\Notifications\RestablecerPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreaUsuarios;
use Tests\TestCase;
use Database\Seeders\RolSeeder;

class AdminUsuariosTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

        protected function setUp(): void
    {
        parent::setUp();

        // Los cuatro roles existen siempre, como en producción.
        $this->seed(RolSeeder::class);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Luis',
            'apellido' => 'Gomez',
            'email' => 'luis@example.com',
            'rol' => 'profesor',
        ], $extra);
    }

    // ---------------------------------------------------------------- Acceso

    public function test_un_invitado_es_enviado_al_login(): void
    {
        $this->get('/admin/usuarios')->assertRedirect(route('login'));
    }

    public function test_el_administrador_ve_el_listado(): void
    {
        $admin = $this->crearUsuario(Rol::Administrador);
        $profesor = $this->crearUsuario(Rol::Profesor);

        $this->actingAs($admin)
            ->get('/admin/usuarios')
            ->assertOk()
            ->assertSee($profesor->email);
    }

    public function test_el_listado_no_incluye_alumnos(): void
    {
        $admin = $this->crearUsuario(Rol::Administrador);
        $alumno = $this->crearUsuario(Rol::Alumno);

        $this->actingAs($admin)->get('/admin/usuarios')->assertOk()->assertDontSee($alumno->email);
    }

    public function test_la_busqueda_filtra_por_nombre(): void
    {
        $admin = $this->crearUsuario(Rol::Administrador);
        $uno = $this->crearUsuario(Rol::Profesor);
        $dos = $this->crearUsuario(Rol::Preceptor);

        $this->actingAs($admin)
            ->get('/admin/usuarios?q='.$uno->nombre)
            ->assertOk()
            ->assertSee($uno->email)
            ->assertDontSee($dos->email);
    }

    public function test_los_demas_roles_reciben_403_en_todas_las_rutas(): void
    {
        $objetivo = $this->crearUsuario(Rol::Profesor);

        foreach ([Rol::Alumno, Rol::Profesor, Rol::Preceptor] as $rol) {
            $u = $this->crearUsuario($rol);

            $this->actingAs($u)->get('/admin/usuarios')->assertForbidden();
            $this->actingAs($u)->get('/admin/usuarios/crear')->assertForbidden();
            $this->actingAs($u)->post('/admin/usuarios', $this->datos())->assertForbidden();
            $this->actingAs($u)->get("/admin/usuarios/{$objetivo->id}/editar")->assertForbidden();
            $this->actingAs($u)->post("/admin/usuarios/{$objetivo->id}/desactivar")->assertForbidden();
            $this->actingAs($u)->post("/admin/usuarios/{$objetivo->id}/password-temporal")->assertForbidden();
        }
    }

    public function test_la_pagina_403_es_la_propia(): void
    {
        $this->actingAs($this->crearUsuario(Rol::Preceptor))
            ->get('/admin/usuarios')
            ->assertForbidden()
            ->assertSee('No tenés permiso');
    }

    // ----------------------------------------------------------------- Alta

    public function test_el_administrador_crea_personal_con_clave_temporal(): void
    {
        $admin = $this->crearUsuario(Rol::Administrador);

        $this->actingAs($admin)
            ->post('/admin/usuarios', $this->datos(['email' => '  LUIS@Example.com ']))
            ->assertRedirect(route('admin.usuarios.index'))
            ->assertSessionHas('password_temporal');

        $nuevo = User::where('email', 'luis@example.com')->first();

        $this->assertNotNull($nuevo);
        $this->assertTrue($nuevo->tieneRol(Rol::Profesor));
        $this->assertTrue($nuevo->estaActivo());
        $this->assertTrue($nuevo->debe_cambiar_password);
        $this->assertNull($nuevo->dni);
        $this->assertSame($admin->id, $nuevo->aprobado_por);
        $this->assertTrue(Hash::check(session('password_temporal')['password'], $nuevo->password));
    }

    public function test_la_cuenta_nueva_ingresa_y_debe_cambiar_la_clave(): void
    {
        $admin = $this->crearUsuario(Rol::Administrador);
        $this->actingAs($admin)->post('/admin/usuarios', $this->datos());
        $clave = session('password_temporal')['password'];
        auth()->logout();

        $this->post('/login', ['email' => 'luis@example.com', 'password' => $clave])
            ->assertRedirect(route('inicio'));

        $this->get('/inicio')->assertRedirect(route('password.cambiar'));
    }

    public function test_no_se_crea_personal_con_correo_repetido_ni_con_rol_alumno(): void
    {
        $admin = $this->crearUsuario(Rol::Administrador);

        $this->actingAs($admin)
            ->post('/admin/usuarios', $this->datos(['email' => $admin->email]))
            ->assertSessionHasErrors('email');

        $this->actingAs($admin)
            ->post('/admin/usuarios', $this->datos(['rol' => 'alumno']))
            ->assertSessionHasErrors('rol');

        $this->assertSame(1, User::count());
    }

    // ------------------------------------------------------------ Edición

    public function test_el_administrador_cambia_datos_y_rol_de_otra_cuenta(): void
    {
        $admin = $this->crearUsuario(Rol::Administrador);
        $objetivo = $this->crearUsuario(Rol::Preceptor);

        $this->actingAs($admin)
            ->put("/admin/usuarios/{$objetivo->id}", $this->datos(['nombre' => 'Marta', 'email' => $objetivo->email, 'rol' => 'profesor']))
            ->assertRedirect(route('admin.usuarios.index'));

        $objetivo = $objetivo->fresh();
        $this->assertSame('Marta', $objetivo->nombre);
        $this->assertTrue($objetivo->tieneRol(Rol::Profesor));
    }

    public function test_nadie_puede_cambiar_su_propio_rol(): void
    {
        $admin = $this->crearUsuario(Rol::Administrador);

        $this->actingAs($admin)
            ->put("/admin/usuarios/{$admin->id}", $this->datos(['email' => $admin->email, 'rol' => 'profesor']))
            ->assertRedirect(route('admin.usuarios.index'));

        $this->assertTrue($admin->fresh()->tieneRol(Rol::Administrador));
    }

    public function test_el_administrador_no_edita_alumnos_desde_aca(): void
    {
        $admin = $this->crearUsuario(Rol::Administrador);
        $alumno = $this->crearUsuario(Rol::Alumno);

        $this->actingAs($admin)->get("/admin/usuarios/{$alumno->id}/editar")->assertForbidden();
        $this->actingAs($admin)->put("/admin/usuarios/{$alumno->id}", $this->datos())->assertForbidden();
    }

    // ------------------------------------------------- Desactivar y reactivar

    public function test_desactivar_corta_el_acceso(): void
    {
        $admin = $this->crearUsuario(Rol::Administrador);
        $objetivo = $this->crearUsuario(Rol::Preceptor);

        $this->actingAs($admin)
            ->post("/admin/usuarios/{$objetivo->id}/desactivar")
            ->assertRedirect();

        $this->assertSame(EstadoUsuario::Inactivo, $objetivo->fresh()->estado);

        $this->actingAs($objetivo->fresh())->get('/inicio')->assertRedirect(route('login'));
    }

    public function test_el_administrador_no_puede_desactivarse_ni_desactivar_alumnos(): void
    {
        $admin = $this->crearUsuario(Rol::Administrador);
        $alumno = $this->crearUsuario(Rol::Alumno);

        $this->actingAs($admin)->post("/admin/usuarios/{$admin->id}/desactivar")->assertForbidden();
        $this->actingAs($admin)->post("/admin/usuarios/{$alumno->id}/desactivar")->assertForbidden();

        $this->assertTrue($admin->fresh()->estaActivo());
        $this->assertTrue($alumno->fresh()->estaActivo());
    }

    public function test_reactivar_devuelve_el_acceso(): void
    {
        $admin = $this->crearUsuario(Rol::Administrador);
        $objetivo = $this->crearUsuario(Rol::Profesor, EstadoUsuario::Inactivo);

        $this->actingAs($admin)->post("/admin/usuarios/{$objetivo->id}/reactivar")->assertRedirect();

        $this->assertTrue($objetivo->fresh()->estaActivo());
    }

    // ------------------------------------------------- Contraseña temporal

    public function test_la_clave_temporal_reemplaza_la_anterior_y_se_muestra_una_vez(): void
    {
        $admin = $this->crearUsuario(Rol::Administrador);
        $objetivo = $this->crearUsuario(Rol::Profesor);

        $this->actingAs($admin)
            ->post("/admin/usuarios/{$objetivo->id}/password-temporal")
            ->assertRedirect()
            ->assertSessionHas('password_temporal');

        $objetivo = $objetivo->fresh();
        $this->assertTrue($objetivo->debe_cambiar_password);
        $this->assertFalse(Hash::check('Clave-test1', $objetivo->password));
        $this->assertTrue(Hash::check(session('password_temporal')['password'], $objetivo->password));
    }

    // ------------------------------------------------- Enlace por correo

    public function test_el_administrador_envia_el_enlace_de_restablecimiento(): void
    {
        Notification::fake();
        $admin = $this->crearUsuario(Rol::Administrador);
        $objetivo = $this->crearUsuario(Rol::Profesor);

        $this->actingAs($admin)
            ->post("/admin/usuarios/{$objetivo->id}/enlace-restablecer")
            ->assertSessionHas('estado');

        Notification::assertSentTo($objetivo, RestablecerPasswordNotification::class);
    }
}