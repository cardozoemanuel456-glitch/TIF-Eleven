<?php

namespace App\Http\Requests\Alumnos;

use Illuminate\Foundation\Http\FormRequest;

/** M3-11 · La eliminación es irreversible: pide una segunda confirmación escrita. */
class EliminarAlumnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('eliminarAlumno', $this->route('alumno')) ?? false;
    }

    public function rules(): array
    {
        return ['confirmacion' => ['required', 'in:ELIMINAR']];
    }

    public function messages(): array
    {
        return ['confirmacion.required' => 'Escribí ELIMINAR para confirmar.', 'confirmacion.in' => 'Escribí ELIMINAR para confirmar.'];
    }
}
