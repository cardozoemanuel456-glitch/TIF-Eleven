<?php

namespace App\Http\Requests\Calendario;

use App\Enums\AnioCursada;
use App\Models\Aviso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAvisoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Aviso::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $anio = trim((string) $this->input('anio_cursada', ''));
        $evento = trim((string) $this->input('evento_id', ''));
        $programado = trim((string) $this->input('programado_para', ''));
        $modo = trim((string) $this->input('modo_publicacion', 'publicar'));

        $this->merge([
            'titulo' => trim((string) $this->input('titulo', '')),
            'contenido' => trim((string) $this->input('contenido', '')),
            'anio_cursada' => $anio !== '' ? $anio : null,
            'evento_id' => $evento !== '' ? $evento : null,
            'modo_publicacion' => $modo !== '' ? $modo : 'publicar',
            'programado_para' => $programado !== '' ? $programado : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:150'],
            'contenido' => ['required', 'string', 'max:5000'],
            'anio_cursada' => ['nullable', 'integer', Rule::in(AnioCursada::valores())],
            'evento_id' => ['nullable', 'integer', Rule::exists('eventos', 'id')],
            'modo_publicacion' => ['required', 'string', Rule::in(['publicar', 'borrador', 'programar'])],
            'programado_para' => [
                Rule::requiredIf(fn (): bool => $this->input('modo_publicacion') === 'programar'),
                'nullable',
                'date',
                'after_or_equal:now',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Completá el campo :attribute.',
            'max' => 'El campo :attribute no puede superar los :max caracteres.',
            'anio_cursada.in' => 'Elegí un destinatario válido.',
            'evento_id.exists' => 'El evento seleccionado no existe.',
            'modo_publicacion.in' => 'Elegí una opción de publicación válida.',
            'programado_para.required' => 'Indicá la fecha y hora para programar la publicación.',
            'programado_para.date' => 'Ingresá una fecha y hora válidas.',
            'programado_para.after_or_equal' => 'La fecha programada debe ser igual o posterior al momento actual.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'titulo' => 'título',
            'contenido' => 'contenido',
            'anio_cursada' => 'destinatario',
            'evento_id' => 'evento vinculado',
            'programado_para' => 'fecha programada',
        ];
    }
}

