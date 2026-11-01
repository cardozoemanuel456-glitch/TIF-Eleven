<?php

namespace App\Http\Requests\Admin;

use App\Enums\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            'apellido' => trim((string) $this->input('apellido')),
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'rol' => ['required', Rule::in(Rol::valoresDelPersonal())],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Completá el campo :attribute.',
            'max' => 'El campo :attribute no puede superar los :max caracteres.',
            'email.email' => 'Ingresá un correo válido.',
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'rol.in' => 'Elegí un rol válido.',
        ];
    }

    public function attributes(): array
    {
        return ['nombre' => 'nombre', 'apellido' => 'apellido', 'email' => 'correo', 'rol' => 'rol'];
    }
}