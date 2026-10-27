<?php

namespace Database\Seeders;

use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('eleven.admin');

        $generada = empty($config['password']);
        $password = $generada ? Str::password(14, symbols: false) : $config['password'];

        $admin = User::where('email', $config['email'])->first();

        // Si el administrador ya existe no se le pisa la contraseña.
        if ($admin) {
            $this->command?->info("El administrador {$config['email']} ya existe; no se modificó.");

            return;
        }

        $admin = new User;
        $admin->forceFill([
            'role_id' => Role::where('nombre', Rol::Administrador->value)->value('id'),
            'nombre' => $config['nombre'],
            'apellido' => $config['apellido'],
            'email' => $config['email'],
            'password' => $password,
            'estado' => EstadoUsuario::Activo,
            'debe_cambiar_password' => true,
            'email_verified_at' => now(),
        ])->save();

        $this->command?->info("Administrador creado: {$config['email']}");

        if ($generada) {
            $this->command?->warn("Contraseña temporal (se muestra una sola vez): {$password}");
        }
    }
}
