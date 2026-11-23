<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Acta;
use App\Models\User;

/**
 * Actas: solo preceptoría y administración (sección 2 del documento maestro).
 * Alumnos y profesores no ven nada de este módulo, ni siquiera el historial.
 */
class ActaPolicy
{
    private function autorizado(User $usuario): bool
    {
        return $usuario->tieneRol(Rol::Preceptor, Rol::Administrador);
    }

    /** Historial de actas. */
    public function viewAny(User $usuario): bool
    {
        return $this->autorizado($usuario);
    }

    /** Ver el detalle y descargar el PDF, las planillas y el informe. */
    public function view(User $usuario, Acta $acta): bool
    {
        return $this->autorizado($usuario);
    }

    /** Subir y analizar un acta. */
    public function create(User $usuario): bool
    {
        return $this->autorizado($usuario);
    }

    /** Confirmar la carga o reprocesar. */
    public function update(User $usuario, Acta $acta): bool
    {
        return $this->autorizado($usuario);
    }

    public function delete(User $usuario, Acta $acta): bool
    {
        return $this->autorizado($usuario);
    }
}
