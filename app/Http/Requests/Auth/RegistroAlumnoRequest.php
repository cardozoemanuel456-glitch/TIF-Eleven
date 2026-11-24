<?php

namespace App\Http\Requests\Auth;

use App\Enums\AnioCursada;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** M3-01 y M3-02 · Registro público de alumnos. DNI y correo únicos. */
class RegistroAlumnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // formulario público; el middleware "guest" lo limita a quien no inició sesión
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            'apellido' => trim((string) $this->input('apellido')),
            // Se aceptan puntos y espacios al tipear el DNI (46.153.970).
            'dni' => str_replace(['.', ' '], '', (string) $this->input('dni')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'dni' => ['required', 'regex:/^\d{7,8}$/', 'unique:users,dni'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'anio_cursada' => ['required', Rule::in(AnioCursada::valores())],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Ingresá tu nombre.',
            'apellido.required' => 'Ingresá tu apellido.',
            'dni.required' => 'Ingresá tu DNI.',
            'dni.regex' => 'El DNI debe tener 7 u 8 dígitos.',
            'dni.unique' => 'Ya existe una cuenta con ese DNI. Si es tuyo, consultá en preceptoría.',
            'email.required' => 'Ingresá tu correo electrónico.',
            'email.email' => 'Ingresá un correo electrónico válido.',
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'anio_cursada.required' => 'Elegí tu aula.',
            'anio_cursada.in' => 'Elegí un aula válida.',
            'password.required' => 'Ingresá una contraseña.',
            'password.confirmed' => 'La confirmación no coincide con la contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.letters' => 'La contraseña debe incluir al menos una letra.',
            'password.numbers' => 'La contraseña debe incluir al menos un número.',
        ];
    }

    public function attributes(): array
    {
        return ['anio_cursada' => 'aula', 'password' => 'contraseña'];
    }
}
