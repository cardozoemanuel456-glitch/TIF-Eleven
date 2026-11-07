<?php

namespace App\Http\Requests\Calendario;

use App\Enums\AnioCursada;
use App\Models\NotificacionProgramada;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificacionProgramadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', NotificacionProgramada::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $anio = trim((string) $this->input('anio_cursada', ''));
        $evento = trim((string) $this->input('evento_id', ''));
        $aviso = trim((string) $this->input('aviso_id', ''));

        $this->merge([
            'titulo' => trim((string) $this->input('titulo', '')),
            'contenido' => trim((string) $this->input('contenido', '')),
            'anio_cursada' => $anio !== '' ? $anio : null,
            'evento_id' => $evento !== '' ? $evento : null,
            'aviso_id' => $aviso !== '' ? $aviso : null,
            'programada_para' => trim((string) $this->input('programada_para', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:150'],
            'contenido' => ['required', 'string', 'max:1000'],
            'anio_cursada' => ['nullable', 'integer', Rule::in(AnioCursada::valores())],
            'evento_id' => ['nullable', 'integer', Rule::exists('eventos', 'id')],
            'aviso_id' => ['nullable', 'integer', Rule::exists('avisos', 'id')],
            'programada_para' => ['required', 'date', 'after_or_equal:now'],
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
            'aviso_id.exists' => 'El aviso seleccionado no existe.',
            'programada_para.date' => 'Ingresá una fecha y hora válidas para la programación.',
            'programada_para.after_or_equal' => 'La fecha y hora programadas deben ser iguales o posteriores al momento actual.',
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
            'evento_id' => 'evento relacionado',
            'aviso_id' => 'aviso relacionado',
            'programada_para' => 'fecha y hora de envío',
        ];
    }
}

