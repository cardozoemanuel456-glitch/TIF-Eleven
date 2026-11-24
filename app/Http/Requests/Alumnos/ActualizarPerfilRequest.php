<?php

namespace App\Http\Requests\Alumnos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** M3-09 · El alumno edita nombre, apellido y correo. El DNI y el aula no se tocan. */
class ActualizarPerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esAlumno() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            'apellido' => trim((string) $this->input('apellido')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Ingresá tu nombre.',
            'apellido.required' => 'Ingresá tu apellido.',
            'email.required' => 'Ingresá tu correo electrónico.',
            'email.email' => 'Ingresá un correo electrónico válido.',
            'email.unique' => 'Ya existe una cuenta con ese correo.',
        ];
    }
}
