<?php

namespace App\Http\Requests\Actas;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmarActaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('acta')) ?? false;
    }

    /**
     * decision[legajo] = conservar | sobrescribir. Lo que no se indica se conserva (M1-06).
     */
    public function rules(): array
    {
        return [
            'decision' => ['nullable', 'array'],
            'decision.*' => ['in:conservar,sobrescribir'],
        ];
    }

    public function messages(): array
    {
        return ['decision.*.in' => 'Elegí conservar o sobrescribir para cada conflicto.'];
    }
}
