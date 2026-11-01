<?php

namespace Tests\Feature;

use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermisosBaseTest extends TestCase
{
    use RefreshDatabase;

    private int $n = 0;

    private function usuario(Rol $rol, EstadoUsuario $estado = EstadoUsuario::Activo, bool $verificado = true): User
    {
        $this->n++;
        $r = Role::firstOrCreate(['nombre' => $rol->value]);

        $u = new User;
        $u->forceFill([
            'role_id' => $r->id,
            'nombre' => 'Nombre'.$this->n,
            'apellido' => 'Apellido'.$this->n,
            'email' => "u{$this->n}@example.com",
            'password' => 'Clave-test1',
            'estado' => $estado,
            'debe_cambiar_password' => false,
            'email_verified_at' => $verificado ? now() : null,
        ])->save();

        return $u;
    }

    // ------------------------------------------------------------ UserPolicy

    public function test_solo_el_administrador_gestiona_el_personal(): void
    {
        $this->assertTrue($this->usuario(Rol::Administrador)->can('viewAny', User::class));
        $this->assertTrue($this->usuario(Rol::Administrador)->can('create', User::class));

        foreach ([Rol::Alumno, Rol::Profesor, Rol::Preceptor] as $rol) {
            $u = $this->usuario($rol);
            $this->assertFalse($u->can('viewAny', User::class), $rol->value);
            $this->assertFalse($u->can('create', User::class), $rol->value);
        }
    }

    public function test_el_administrador_edita_personal_pero_no_alumnos(): void
    {
        $admin = $this->usuario(Rol::Administrador);

        $this->assertTrue($admin->can('update', $this->usuario(Rol::Profesor)));
        $this->assertFalse($admin->can('update', $this->usuario(Rol::Alumno)));
    }

    public function test_el_administrador_no_puede_desactivarse_a_si_mismo(): void
    {
        $admin = $this->usuario(Rol::Administrador);

        $this->assertFalse($admin->can('desactivar', $admin));
        $this->assertTrue($admin->can('desactivar', $this->usuario(Rol::Preceptor)));
    }

    public function test_un_alumno_solo_ve_su_propio_perfil(): void
    {
        $alumno = $this->usuario(Rol::Alumno);
        $otro = $this->usuario(Rol::Alumno);

        $this->assertTrue($alumno->can('view', $alumno));
        $this->assertFalse($alumno->can('view', $otro));
    }

    public function test_preceptoria_ve_alumnos_pero_no_otro_personal(): void
    {
        $preceptor = $this->usuario(Rol::Preceptor);

        $this->assertTrue($preceptor->can('view', $this->usuario(Rol::Alumno)));
        $this->assertFalse($preceptor->can('view', $this->usuario(Rol::Profesor)));
        $this->assertFalse($this->usuario(Rol::Profesor)->can('view', $this->usuario(Rol::Alumno)));
    }

    public function test_restablecer_contrasena_segun_rol(): void
    {
        $admin = $this->usuario(Rol::Administrador);
        $preceptor = $this->usuario(Rol::Preceptor);
        $alumno = $this->usuario(Rol::Alumno);
        $profesor = $this->usuario(Rol::Profesor);

        $this->assertTrue($admin->can('restablecerPassword', $profesor));
        $this->assertTrue($admin->can('restablecerPassword', $alumno));
        $this->assertTrue($preceptor->can('restablecerPassword', $alumno));
        $this->assertFalse($preceptor->can('restablecerPassword', $profesor));
        $this->assertFalse($alumno->can('restablecerPassword', $alumno));
        $this->assertFalse($admin->can('restablecerPassword', $admin));
    }

    // ---------------------------------------------------------------- Inicio

    public function test_el_inicio_saluda_y_muestra_el_rol(): void
    {
        $this->actingAs($this->usuario(Rol::Profesor))
            ->get('/inicio')
            ->assertOk()
            ->assertSee('Hola, Nombre1')
            ->assertSee('Profesor');
    }

    public function test_preceptoria_ve_el_contador_de_pendientes_con_correo_verificado(): void
    {
        $this->usuario(Rol::Alumno, EstadoUsuario::Pendiente, true);
        $this->usuario(Rol::Alumno, EstadoUsuario::Pendiente, true);
        $this->usuario(Rol::Alumno, EstadoUsuario::Pendiente, false); // sin verificar: no cuenta
        $preceptor = $this->usuario(Rol::Preceptor);

        $this->actingAs($preceptor)
            ->get('/inicio')
            ->assertOk()
            ->assertSee('2 alumnos esperan aprobación');
    }

    public function test_el_alumno_no_ve_el_contador_de_pendientes(): void
    {
        $this->usuario(Rol::Alumno, EstadoUsuario::Pendiente, true);

        $this->actingAs($this->usuario(Rol::Alumno))
            ->get('/inicio')
            ->assertOk()
            ->assertDontSee('Registros pendientes');
    }

    // --------------------------------------------------------------- Errores

    public function test_una_ruta_inexistente_muestra_la_pagina_404_propia(): void
    {
        $this->get('/no-existe')
            ->assertNotFound()
            ->assertSee('Página no encontrada');
    }
}