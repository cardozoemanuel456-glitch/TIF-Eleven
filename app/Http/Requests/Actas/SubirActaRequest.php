<?php

namespace App\Http\Requests\Actas;

use App\Models\Acta;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * M1-01: el tipo se valida por el contenido del archivo (firma inicial), no por lo que
 * dice su extensión. Que el PDF tenga texto y que la planilla tenga la estructura
 * esperada se comprueba al analizar, con un mensaje claro.
 */
class SubirActaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Acta::class) ?? false;
    }

    public function rules(): array
    {
        $maxKb = (int) config('actas.max_kb');

        return [
            'acta_pdf' => [
                'bail', 'required', 'file', 'max:'.$maxKb,
                function (string $atributo, mixed $valor, Closure $fallar): void {
                    if (! $this->tieneFirma($valor, '%PDF-')) {
                        $fallar('El acta debe ser un archivo PDF.');
                    }
                },
            ],
            'planilla' => [
                'bail', 'required', 'file', 'max:'.$maxKb,
                function (string $atributo, mixed $valor, Closure $fallar): void {
                    $esXlsx = $valor instanceof UploadedFile
                        && strtolower($valor->getClientOriginalExtension()) === 'xlsx'
                        && $this->tieneFirma($valor, "PK\x03\x04");

                    if (! $esXlsx) {
                        $fallar('La planilla debe ser un archivo Excel .xlsx.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        $mb = intdiv((int) config('actas.max_kb'), 1024);

        return [
            'acta_pdf.required' => 'Elegí el acta en PDF.',
            'acta_pdf.file' => 'El acta debe ser un archivo.',
            'acta_pdf.max' => "El acta no puede superar los {$mb} MB.",
            'acta_pdf.uploaded' => "No se pudo subir el acta. Verificá que no supere los {$mb} MB.",
            'planilla.required' => 'Elegí la planilla de seguimiento (.xlsx).',
            'planilla.file' => 'La planilla debe ser un archivo.',
            'planilla.max' => "La planilla no puede superar los {$mb} MB.",
            'planilla.uploaded' => "No se pudo subir la planilla. Verificá que no supere los {$mb} MB.",
        ];
    }

    private function tieneFirma(mixed $archivo, string $firma): bool
    {
        if (! $archivo instanceof UploadedFile || ! $archivo->isValid()) {
            return false;
        }

        $ruta = $archivo->getRealPath();

        return $ruta !== false && file_get_contents($ruta, false, null, 0, strlen($firma)) === $firma;
    }
}
