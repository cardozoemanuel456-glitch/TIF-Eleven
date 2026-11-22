<?php

namespace App\Http\Requests\Actas;

use Illuminate\Support\Arr;

/**
 * Reprocesar reutiliza el PDF guardado: solo se pide la planilla de seguimiento actual,
 * con las mismas validaciones por contenido que al subir un acta.
 */
class ReprocesarActaRequest extends SubirActaRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('acta')) ?? false;
    }

    public function rules(): array
    {
        return Arr::only(parent::rules(), ['planilla']);
    }
}
