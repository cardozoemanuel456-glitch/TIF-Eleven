<?php

namespace App\Models;

use App\Enums\Rol;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['nombre'];

    protected function casts(): array
    {
        return [
            'nombre' => Rol::class,
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
