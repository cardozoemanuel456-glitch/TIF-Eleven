<?php

namespace App\Http\Requests\Calendario;

use App\Enums\AnioCursada;
use App\Enums\TipoEvento;
use App\Models\Evento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Evento::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $fechaInicio = trim((string) $this->input('fecha_inicio', ''));
        $fechaFin = trim((string) $this->input('fecha_fin', ''));
        $hora = trim((string) $this->input('hora', ''));
        $anio = trim((string) $this->input('anio_cursada', ''));
        $descripcion = trim((string) $this->input('descripcion', ''));

        $this->merge([
            'titulo' => trim((string) $this->input('titulo', '')),
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'tipo' => trim((string) $this->input('tipo', '')),
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin !== '' ? $fechaFin : ($fechaInicio !== '' ? $fechaInicio : null),
            'hora' => $hora !== '' ? substr($hora, 0, 5) : null,
            'anio_cursada' => $anio !== '' ? $anio : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'tipo' => ['required', 'string', Rule::in(TipoEvento::valores())],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'hora' => ['nullable', 'date_format:H:i'],
            'anio_cursada' => ['nullable', 'integer', Rule::in(AnioCursada::valores())],
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
            'tipo.in' => 'Elegí un tipo de evento válido.',
            'fecha_inicio.date_format' => 'Ingresá una fecha de inicio válida.',
            'fecha_fin.date_format' => 'Ingresá una fecha de fin válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'hora.date_format' => 'Ingresá una hora válida (HH:MM).',
            'anio_cursada.in' => 'Elegí un destinatario válido.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'titulo' => 'título',
            'descripcion' => 'descripción',
            'tipo' => 'tipo',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin' => 'fecha de fin',
            'hora' => 'hora',
            'anio_cursada' => 'año de cursada',
        ];
    }
}

