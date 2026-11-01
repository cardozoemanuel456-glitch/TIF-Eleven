<?php

namespace Tests\Concerns;

use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use App\Models\Role;
use App\Models\User;

trait CreaUsuarios
{
    private int $contadorUsuarios = 0;

    protected function crearUsuario(
        Rol $rol,
        EstadoUsuario $estado = EstadoUsuario::Activo,
        bool $debeCambiar = false,
    ): User {
        $this->contadorUsuarios++;
        $r = Role::firstOrCreate(['nombre' => $rol->value]);

        $u = new User;
        $u->forceFill([
            'role_id' => $r->id,
            'nombre' => 'Nombre'.$this->contadorUsuarios,
            'apellido' => 'Apellido'.$this->contadorUsuarios,
            'email' => "u{$this->contadorUsuarios}@example.com",
            'password' => 'Clave-test1',
            'estado' => $estado,
            'debe_cambiar_password' => $debeCambiar,
            'email_verified_at' => now(),
        ])->save();

        return $u;
    }
}