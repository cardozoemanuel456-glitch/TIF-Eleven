<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\User;

/**
 * Permisos sobre cuentas de usuario (matriz de la sección 2 del documento maestro).
 * Laravel la descubre sola por el nombre (User -> UserPolicy).
 * Las acciones sobre alumnos (aprobar, desactivar, etc.) se agregan en el módulo 3.
 */
class UserPolicy
{
    /** Listado y gestión de cuentas del personal: solo el administrador. */
    public function viewAny(User $actor): bool
    {
        return $actor->tieneRol(Rol::Administrador);
    }

    /** DNI y correo: solo la propia persona, preceptoría y administración. */
    public function view(User $actor, User $objetivo): bool
    {
        if ($actor->is($objetivo) || $actor->tieneRol(Rol::Administrador)) {
            return true;
        }

        return $actor->tieneRol(Rol::Preceptor) && $objetivo->esAlumno();
    }

    public function create(User $actor): bool
    {
        return $actor->tieneRol(Rol::Administrador);
    }

    /** Editar datos y cambiar el rol de una cuenta del personal. */
    public function update(User $actor, User $objetivo): bool
    {
        return $actor->tieneRol(Rol::Administrador) && $objetivo->esPersonal();
    }

    /** El administrador no puede desactivarse a sí mismo. */
    public function desactivar(User $actor, User $objetivo): bool
    {
        return $actor->tieneRol(Rol::Administrador)
            && $objetivo->esPersonal()
            && ! $actor->is($objetivo);
    }

    public function reactivar(User $actor, User $objetivo): bool
    {
        return $actor->tieneRol(Rol::Administrador) && $objetivo->esPersonal();
    }

    /** Contraseña temporal: el administrador a cualquiera, preceptoría solo a alumnos. */
    public function restablecerPassword(User $actor, User $objetivo): bool
    {
        if ($actor->is($objetivo)) {
            return false; // para la propia cuenta existe el cambio de contraseña
        }

        return $actor->tieneRol(Rol::Administrador)
            || ($actor->tieneRol(Rol::Preceptor) && $objetivo->esAlumno());
    }
}