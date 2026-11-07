<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\NotificacionProgramada;
use App\Models\User;

/**
 * Permisos sobre programación de notificaciones y recordatorios (HU 16 y M2).
 */
class NotificacionProgramadaPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->gestionar($actor);
    }

    public function gestionar(User $actor): bool
    {
        return $actor->tieneRol(Rol::Preceptor, Rol::Administrador);
    }

    public function create(User $actor): bool
    {
        return $this->gestionar($actor);
    }

    public function enviar(User $actor, NotificacionProgramada $notificacion): bool
    {
        return $this->gestionar($actor);
    }

    public function delete(User $actor, NotificacionProgramada $notificacion): bool
    {
        return $this->gestionar($actor);
    }
}

