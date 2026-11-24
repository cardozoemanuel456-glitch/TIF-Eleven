<?php

namespace App\Http\Requests\Alumnos;

use App\Enums\AnioCursada;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarAnioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cambiarAnio', $this->route('alumno')) ?? false;
    }

    public function rules(): array
    {
        return ['anio_cursada' => ['required', Rule::in(AnioCursada::valores())]];
    }

    public function messages(): array
    {
        return ['anio_cursada.in' => 'Elegí un aula válida.', 'anio_cursada.required' => 'Elegí un aula.'];
    }
}
