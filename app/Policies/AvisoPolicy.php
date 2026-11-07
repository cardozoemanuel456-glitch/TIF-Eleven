<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Aviso;
use App\Models\User;

/**
 * Permisos sobre avisos institucionales (sección 2 y M2 del documento maestro).
 */
class AvisoPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->estaActivo();
    }

    public function view(User $actor, Aviso $aviso): bool
    {
        if (! $aviso->estaPublicado()) {
            return $this->gestionar($actor);
        }

        if ($actor->esPersonal()) {
            return true;
        }

        return $aviso->anio_cursada === null
            || $aviso->anio_cursada === $actor->anio_cursada;
    }

    public function gestionar(User $actor): bool
    {
        return $actor->tieneRol(Rol::Preceptor, Rol::Administrador);
    }

    public function create(User $actor): bool
    {
        return $this->gestionar($actor);
    }

    public function update(User $actor, Aviso $aviso): bool
    {
        return $this->gestionar($actor);
    }

    public function publicar(User $actor, Aviso $aviso): bool
    {
        return $this->gestionar($actor);
    }

    public function delete(User $actor, Aviso $aviso): bool
    {
        return $this->gestionar($actor);
    }
}

