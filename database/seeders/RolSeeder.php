<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Rol::cases() as $rol) {
            Role::firstOrCreate(['nombre' => $rol->value]);
        }
    }
}
