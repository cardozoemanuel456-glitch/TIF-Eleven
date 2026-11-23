<?php

namespace Tests\Feature;

use App\Enums\AnioCursada;
use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BaseRolesUsuariosTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_seeder_crea_los_cuatro_roles(): void
    {
        $this->seed(RolSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['alumno', 'profesor', 'preceptor', 'administrador'],
            Role::all()->map(fn (Role $rol) => $rol->nombre->value)->all()
        );
    }

    public function test_el_seeder_se_puede_ejecutar_dos_veces_sin_duplicar(): void
    {
        $this->seed(RolSeeder::class);
        $this->seed(RolSeeder::class);

        $this->assertSame(4, Role::count());
    }

    public function test_los_anios_de_cursada_son_dos(): void
    {
        $this->assertSame([1, 2], AnioCursada::valores());
        $this->assertSame('Primer año', AnioCursada::Primero->etiqueta());
        $this->assertSame('Segundo año', AnioCursada::Segundo->etiqueta());
    }

    public function test_el_seeder_crea_un_administrador_activo_que_debe_cambiar_la_contrasena(): void
    {
        config(['eleven.admin.email' => 'admin@eleven.test', 'eleven.admin.password' => 'ClaveSegura123']);

        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@eleven.test')->firstOrFail();

        $this->assertTrue($admin->tieneRol(Rol::Administrador));
        $this->assertTrue($admin->estaActivo());
        $this->assertTrue($admin->debe_cambiar_password);
        $this->assertTrue(Hash::check('ClaveSegura123', $admin->password));
    }

    public function test_el_seeder_no_pisa_un_administrador_existente(): void
    {
        config(['eleven.admin.email' => 'admin@eleven.test', 'eleven.admin.password' => 'ClaveSegura123']);
        $this->seed(DatabaseSeeder::class);

        config(['eleven.admin.password' => 'OtraClave456']);
        $this->seed(AdminSeeder::class);

        $this->assertSame(1, User::where('email', 'admin@eleven.test')->count());
        $this->assertTrue(Hash::check('ClaveSegura123', User::where('email', 'admin@eleven.test')->first()->password));
    }

    public function test_un_alumno_tiene_rol_y_anio_de_cursada(): void
    {
        $alumno = User::factory()->alumno()->create();
        $segundo = User::factory()->alumno(AnioCursada::Segundo)->create();

        $this->assertSame(Rol::Alumno, $alumno->role->nombre);
        $this->assertSame(AnioCursada::Primero, $alumno->anio_cursada);
        $this->assertSame(AnioCursada::Segundo, $segundo->fresh()->anio_cursada);
        $this->assertTrue($alumno->esAlumno());
        $this->assertFalse($alumno->esPersonal());
    }

    public function test_el_personal_se_distingue_de_los_alumnos(): void
    {
        $profesor = User::factory()->conRol(Rol::Profesor)->create();
        $admin = User::factory()->conRol(Rol::Administrador)->create();
        User::factory()->alumno()->create();

        $this->assertTrue($profesor->esPersonal());
        $this->assertTrue($admin->tieneRol(Rol::Preceptor, Rol::Administrador));
        $this->assertFalse($profesor->tieneRol(Rol::Preceptor, Rol::Administrador));
        $this->assertSame(2, User::personal()->count());
        $this->assertSame(1, User::alumnos()->count());
    }

    public function test_solo_una_cuenta_en_estado_activo_esta_activa(): void
    {
        $this->assertTrue(User::factory()->create()->estaActivo());
        $this->assertFalse(User::factory()->pendiente()->create()->estaActivo());
        $this->assertFalse(User::factory()->inactivo()->create()->estaActivo());
        $this->assertSame(EstadoUsuario::Pendiente, User::factory()->pendiente()->create()->estado);
    }

    public function test_el_dni_es_unico(): void
    {
        User::factory()->alumno()->create(['dni' => '40123456', 'email' => 'uno@eleven.test']);

        $this->expectException(QueryException::class);
        User::factory()->alumno()->create(['dni' => '40123456', 'email' => 'otro@eleven.test']);
    }

    public function test_el_correo_es_unico(): void
    {
        User::factory()->create(['email' => 'repetido@eleven.test']);

        $this->expectException(QueryException::class);
        User::factory()->create(['email' => 'repetido@eleven.test']);
    }

    public function test_el_nombre_completo_une_apellido_y_nombre(): void
    {
        $usuario = User::factory()->create(['nombre' => 'Lucía', 'apellido' => 'Gómez']);

        $this->assertSame('Gómez, Lucía', $usuario->nombreCompleto());
    }

    public function test_un_usuario_nuevo_sin_datos_de_control_queda_pendiente(): void
    {
        $usuario = new User;
        $usuario->forceFill([
            'nombre' => 'Nuevo',
            'email' => 'nuevo@eleven.test',
            'password' => 'ClaveSegura123',
        ])->save();

        $this->assertSame(EstadoUsuario::Pendiente, $usuario->fresh()->estado);
        $this->assertFalse($usuario->fresh()->debe_cambiar_password);
        $this->assertTrue($usuario->fresh()->notificar_email);
    }
}
