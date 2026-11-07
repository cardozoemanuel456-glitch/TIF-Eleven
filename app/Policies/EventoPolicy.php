<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Evento;
use App\Models\User;

/**
 * Permisos sobre eventos del calendario académico (sección 2 y M2 del documento maestro).
 */
class EventoPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->estaActivo();
    }

    public function view(User $actor, Evento $evento): bool
    {
        if ($actor->esPersonal()) {
            return true;
        }

        return $evento->anio_cursada === null
            || $evento->anio_cursada === $actor->anio_cursada;
    }

    public function gestionar(User $actor): bool
    {
        return $actor->tieneRol(Rol::Preceptor, Rol::Administrador);
    }

    public function create(User $actor): bool
    {
        return $this->gestionar($actor);
    }

    public function update(User $actor, Evento $evento): bool
    {
        return $this->gestionar($actor);
    }

    public function delete(User $actor, Evento $evento): bool
    {
        return $this->gestionar($actor);
    }
}

