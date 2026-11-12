<?php

namespace App\Services;

use App\Enums\EstadoActa;
use App\Exceptions\ActaIlegibleException;
use App\Exceptions\ActaNoDisponibleException;
use App\Exceptions\PlanillaInvalidaException;
use App\Models\Acta;
use App\Models\PlanillaExcel;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Orquesta el flujo de una acta: guardar los archivos en el disco privado, registrar el
 * acta, armar la vista previa y confirmar la carga. La lectura del PDF y de la planilla
 * vive en ActaProcessorService.
 *
 * Reglas: los archivos se guardan con nombre aleatorio; el contenido del acta nunca se
 * escribe en logs; las filas de alumnos no se guardan en la base (la vista previa se
 * recalcula desde los archivos); la planilla original subida se descarta al terminar.
 */
class ActaService
{
    public function __construct(private readonly ActaProcessorService $procesador) {}

    /**
     * M1-01 a M1-05: guarda los archivos, comprueba que se pueden leer y registra el acta
     * como «analizada». No escribe nada en la planilla.
     *
     * @throws ActaIlegibleException|PlanillaInvalidaException
     */
    public function registrar(UploadedFile $pdf, UploadedFile $planilla, User $usuario): Acta
    {
        $disco = $this->disco();
        $carpeta = $this->carpeta();

        $rutaPdf = $disco->putFileAs("{$carpeta}/pdf", $pdf, Str::uuid().'.pdf');
        $rutaOrigen = $disco->putFileAs("{$carpeta}/origen", $planilla, Str::uuid().'.xlsx');

        if ($rutaPdf === false || $rutaOrigen === false) {
            $disco->delete(array_filter([$rutaPdf, $rutaOrigen]));

            throw new RuntimeException('No se pudieron guardar los archivos subidos.');
        }

        try {
            $datos = $this->procesador->parsePdfActa($disco->path($rutaPdf));
            // Valida la estructura de la planilla y que la materia tenga columna (M1-11).
            $this->procesador->analizar($disco->path($rutaOrigen), $datos);
        } catch (Throwable $e) {
            $disco->delete([$rutaPdf, $rutaOrigen]);

            throw $e;
        }

        return Acta::create([
            'user_id' => $usuario->id,
            'nombre_original' => Str::limit($pdf->getClientOriginalName(), 250, ''),
            'archivo_pdf' => $rutaPdf,
            'hash_sha256' => hash_file('sha256', $disco->path($rutaPdf)),
            'planilla_origen' => $rutaOrigen,
            'planilla_nombre' => Str::limit($planilla->getClientOriginalName(), 250, ''),
            'materia' => $datos['materia'],
            'tomo' => $datos['tomo'],
            'folio' => $datos['folio'],
            'estado' => EstadoActa::Analizada,
        ]);
    }

    /**
     * M1-05: lo que se cargaría, recalculado desde los archivos guardados.
     *
     * @return array{datos: array<string, mixed>, plan: array<string, mixed>, duplicada: ?Acta}
     *
     * @throws ActaNoDisponibleException|ActaIlegibleException|PlanillaInvalidaException
     */
    public function vistaPrevia(Acta $acta): array
    {
        [$rutaPdf, $rutaOrigen] = $this->rutasDeTrabajo($acta);

        $datos = $this->procesador->parsePdfActa($rutaPdf);
        $plan = $this->procesador->analizar($rutaOrigen, $datos);

        // M1-02: advertencia si el mismo PDF ya fue procesado.
        $duplicada = Acta::with('user')
            ->where('hash_sha256', $acta->hash_sha256)
            ->where('id', '!=', $acta->id)
            ->where('estado', EstadoActa::Procesada->value)
            ->latest('procesada_en')
            ->first();

        return ['datos' => $datos, 'plan' => $plan, 'duplicada' => $duplicada];
    }

    /**
     * M1-06, M1-07, M1-10, M1-12: genera la planilla con las notas cargadas y registra el
     * informe. Si la carga falla, el acta queda en estado «error» con un mensaje claro y
     * no se entrega ninguna planilla.
     *
     * @param  array<int|string, string>  $decisiones legajo => 'conservar' | 'sobrescribir'
     *
     * @throws ActaNoDisponibleException si el acta ya no se puede confirmar o no hay nada que cargar
     */
    public function confirmar(Acta $acta, array $decisiones): Acta
    {
        if ($acta->estado !== EstadoActa::Analizada) {
            throw new ActaNoDisponibleException(
                'Esta acta ya fue procesada o terminó con error. Subila de nuevo si necesitás repetir la carga.'
            );
        }

        [$rutaPdf, $rutaOrigen] = $this->rutasDeTrabajo($acta);

        $disco = $this->disco();
        $salida = $this->carpeta().'/planillas/'.Str::uuid().'.xlsx';
        $disco->makeDirectory(dirname($salida));

        try {
            $datos = $this->procesador->parsePdfActa($rutaPdf);
            $resultado = $this->procesador->cargar($rutaOrigen, $disco->path($salida), $datos, $decisiones);
        } catch (ActaIlegibleException|PlanillaInvalidaException $e) {
            return $this->fallar($acta, $e->getMessage(), $salida);
        } catch (Throwable $e) {
            // Solo el tipo de error: nunca el contenido del acta.
            Log::error('actas.carga_fallida', ['acta' => $acta->id, 'error' => $e::class]);

            return $this->fallar($acta, 'No se pudo generar la planilla. Probá de nuevo; si el problema sigue, avisá al administrador.', $salida);
        }

        if ($resultado['escritas'] === 0) {
            $disco->delete($salida);

            throw new ActaNoDisponibleException(
                'No hay notas para cargar: todas las celdas ya tenían la nota o se eligió conservar las existentes.'
            );
        }

        $origen = $acta->planilla_origen;
        $informe = $this->armarInforme($datos, $resultado);

        try {
            DB::transaction(function () use ($acta, $salida, $informe): void {
                PlanillaExcel::create([
                    'acta_id' => $acta->id,
                    'nombre_original' => $acta->planilla_nombre ?: 'planilla.xlsx',
                    'archivo_xlsx' => $salida,
                    'generada_en' => now(),
                ]);

                $acta->update([
                    'estado' => EstadoActa::Procesada,
                    'procesada_en' => now(),
                    'informe' => $informe,
                    'mensaje_error' => null,
                    'planilla_origen' => null,
                ]);
            });
        } catch (Throwable $e) {
            Log::error('actas.registro_fallido', ['acta' => $acta->id, 'error' => $e::class]);

            return $this->fallar($acta, 'La planilla se generó pero no se pudo registrar el resultado. Probá de nuevo.', $salida);
        }

        // M1-12: la planilla original subida se descarta al terminar.
        $disco->delete($origen);

        return $acta->refresh();
    }

    public function disco(): FilesystemAdapter
    {
        return Storage::disk((string) config('actas.disco'));
    }

    // ------------------------------------------------------------------ Privados

    private function carpeta(): string
    {
        return trim((string) config('actas.directorio'), '/');
    }

    /**
     * @return array{0: string, 1: string} rutas absolutas del PDF y de la planilla subida
     *
     * @throws ActaNoDisponibleException
     */
    private function rutasDeTrabajo(Acta $acta): array
    {
        $disco = $this->disco();

        if (
            ! $acta->archivo_pdf || ! $acta->planilla_origen
            || ! $disco->exists($acta->archivo_pdf) || ! $disco->exists($acta->planilla_origen)
        ) {
            throw new ActaNoDisponibleException('Los archivos de esta acta ya no están disponibles. Subila de nuevo.');
        }

        return [$disco->path($acta->archivo_pdf), $disco->path($acta->planilla_origen)];
    }

    private function fallar(Acta $acta, string $mensaje, string $salida): Acta
    {
        $this->disco()->delete(array_filter([$salida, $acta->planilla_origen]));

        $acta->update([
            'estado' => EstadoActa::Error,
            'mensaje_error' => $mensaje,
            'planilla_origen' => null,
        ]);

        return $acta;
    }

    /**
     * M1-10: lo que se conserva del acta. Incluye lo que quedó sin cargar (con legajo,
     * nombre y nota), pero no las filas de los alumnos cargados.
     *
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    private function armarInforme(array $datos, array $resultado): array
    {
        $plan = $resultado['plan'];
        $sobrescritas = count(array_filter(
            $resultado['conflictos_resueltos'],
            fn (array $c) => $c['resolucion'] === 'sobrescribir',
        ));

        return [
            'materia' => $datos['materia'],
            'tomo' => $datos['tomo'],
            'folio' => $datos['folio'],
            'alumnos_leidos' => $datos['total_alumnos'],
            'control' => $datos['control'],
            'columnas' => $plan['columnas'],
            'notas_cargadas' => count($plan['cargas']) + $sobrescritas,
            'sin_cambios' => count($plan['sin_cambios']),
            'conflictos' => array_map(fn (array $c) => [
                'legajo' => $c['legajo'],
                'nombre_acta' => $c['nombre_acta'],
                'hoja' => $c['hoja'],
                'celda' => $c['celda'],
                'actual' => $c['actual'],
                'nota' => $c['nota'],
                'resolucion' => $c['resolucion'],
            ], $resultado['conflictos_resueltos']),
            'no_encontrados' => $plan['no_encontrados'],
            'sin_columna' => $plan['sin_columna'],
            'lineas_no_leidas' => $datos['lineas_no_leidas'],
            'legajos_repetidos_en_planilla' => $plan['legajos_repetidos'],
            'legajos_repetidos_en_acta' => $datos['legajos_repetidos'],
            'diferencias_nombre' => $plan['diferencias_nombre'],
            'advertencias' => array_merge($datos['advertencias'], $plan['advertencias']),
        ];
    }
}
