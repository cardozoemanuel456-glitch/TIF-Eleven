<?php

namespace Database\Factories;

use App\Enums\AnioCursada;
use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'role_id' => $this->idDeRol(Rol::Alumno),
            'nombre' => fake()->firstName(),
            'apellido' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'estado' => EstadoUsuario::Activo,
            'debe_cambiar_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function conRol(Rol $rol): static
    {
        return $this->state(fn () => ['role_id' => $this->idDeRol($rol)]);
    }

    /** Alumno con DNI y año de cursada (por defecto, primero). */
    public function alumno(AnioCursada $anio = AnioCursada::Primero): static
    {
        return $this->state(fn () => [
            'role_id' => $this->idDeRol(Rol::Alumno),
            'anio_cursada' => $anio,
            'dni' => fake()->unique()->numerify('########'),
        ]);
    }

    public function pendiente(): static
    {
        return $this->state(fn () => ['estado' => EstadoUsuario::Pendiente]);
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['estado' => EstadoUsuario::Inactivo]);
    }

    public function debeCambiarPassword(): static
    {
        return $this->state(fn () => ['debe_cambiar_password' => true]);
    }

    private function idDeRol(Rol $rol): int
    {
        return Role::firstOrCreate(['nombre' => $rol->value])->id;
    }
}
