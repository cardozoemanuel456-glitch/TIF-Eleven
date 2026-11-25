<?php

namespace App\Http\Controllers\Actas;

use App\Enums\EstadoActa;
use App\Exceptions\ActaIlegibleException;
use App\Exceptions\ActaNoDisponibleException;
use App\Exceptions\PlanillaInvalidaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Actas\ConfirmarActaRequest;
use App\Http\Requests\Actas\ReprocesarActaRequest;
use App\Http\Requests\Actas\SubirActaRequest;
use App\Models\Acta;
use App\Models\PlanillaExcel;
use App\Services\ActaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * M1 · Actas. Las rutas exigen la Policy "viewAny" (preceptoría y administración) y cada
 * acción vuelve a autorizar sobre el acta concreta.
 */
class ActaController extends Controller
{
    public function __construct(private readonly ActaService $servicio) {}

    /** M1-13: historial, del más reciente al más antiguo. */
    public function index(): View
    {
        $actas = Acta::with('user')->latest()->paginate(15);

        return view('actas.index', ['actas' => $actas]);
    }

    public function create(): View
    {
        Gate::authorize('create', Acta::class);

        return view('actas.create', ['maxMb' => intdiv((int) config('actas.max_kb'), 1024)]);
    }

    /** M1-01 a M1-05: sube los archivos y muestra la vista previa. No escribe nada en la planilla. */
    public function analizar(SubirActaRequest $request): RedirectResponse
    {
        try {
            $acta = $this->servicio->registrar($request->file('acta_pdf'), $request->file('planilla'), $request->user());
        } catch (ActaIlegibleException|PlanillaInvalidaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('actas.show', $acta);
    }

    /**
     * Con una planilla pendiente (carga nueva o reproceso) muestra la vista previa; si no,
     * el informe del acta procesada o el motivo por el que no se puede continuar.
     */
    public function show(Acta $acta): View
    {
        Gate::authorize('view', $acta);

        if ($acta->planilla_origen !== null) {
            return $this->vistaPrevia($acta);
        }

        return match ($acta->estado) {
            EstadoActa::Analizada => $this->conError($acta, 'Los archivos de esta acta ya no están disponibles: las planillas subidas que no se confirman se descartan a las '.config('actas.horas_limpieza').' horas.'),
            EstadoActa::Procesada => view('actas.informe', ['acta' => $acta->load('planillas', 'user'), 'informe' => $acta->informe ?? []]),
            EstadoActa::Error => $this->conError($acta, $acta->mensaje_error),
        };
    }

    public function confirmar(ConfirmarActaRequest $request, Acta $acta): RedirectResponse
    {
        try {
            $acta = $this->servicio->confirmar($acta, $request->validated('decision') ?? []);
        } catch (ActaNoDisponibleException $e) {
            return redirect()->route('actas.show', $acta)->with('error', $e->getMessage());
        }

        if ($acta->mensaje_error !== null) {
            return redirect()->route('actas.show', $acta)->with('error', $acta->mensaje_error);
        }

        $cargadas = $acta->informe['notas_cargadas'] ?? 0;

        return redirect()->route('actas.show', $acta)->with(
            'estado',
            "Se cargaron {$cargadas} notas en la planilla. Descargala y usala como tu planilla de seguimiento vigente."
        );
    }

    /** M1-13: formulario para reprocesar con la planilla de seguimiento actual. */
    public function reprocesar(Acta $acta): View|RedirectResponse
    {
        Gate::authorize('update', $acta);

        if ($acta->planilla_origen !== null) {
            return redirect()->route('actas.show', $acta);
        }

        if (! $this->servicio->tienePdf($acta)) {
            return $this->conError($acta, 'El PDF de esta acta ya no está disponible. Subila de nuevo desde «Subir acta».');
        }

        return view('actas.reprocesar', ['acta' => $acta]);
    }

    public function reprocesarGuardar(ReprocesarActaRequest $request, Acta $acta): RedirectResponse
    {
        try {
            $this->servicio->reprocesar($acta, $request->file('planilla'));
        } catch (ActaNoDisponibleException|ActaIlegibleException|PlanillaInvalidaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('actas.show', $acta);
    }

    /** M1-13: elimina el acta con su PDF, sus planillas y su informe. */
    public function destroy(Request $request, Acta $acta): RedirectResponse
    {
        Gate::authorize('delete', $acta);

        $this->servicio->eliminar($acta, $request->user());

        return redirect()->route('actas.index')->with('estado', 'Se eliminó el acta con su PDF, sus planillas y su informe.');
    }

    public function pdf(Acta $acta): StreamedResponse
    {
        Gate::authorize('view', $acta);

        $disco = $this->servicio->disco();
        abort_unless($acta->archivo_pdf && $disco->exists($acta->archivo_pdf), 404);

        return $disco->download($acta->archivo_pdf, $acta->nombre_original);
    }

    public function planilla(Acta $acta, PlanillaExcel $planilla): StreamedResponse
    {
        Gate::authorize('view', $acta);
        abort_unless($planilla->acta_id === $acta->id, 404);

        $disco = $this->servicio->disco();
        abort_unless($disco->exists($planilla->archivo_xlsx), 404);

        $nombre = Str::beforeLast($planilla->nombre_original, '.').'_actualizada.xlsx';

        return $disco->download($planilla->archivo_xlsx, $nombre);
    }

    private function vistaPrevia(Acta $acta): View
    {
        try {
            $previa = $this->servicio->vistaPrevia($acta);
        } catch (ActaNoDisponibleException|ActaIlegibleException|PlanillaInvalidaException $e) {
            return $this->conError($acta, $e->getMessage());
        }

        return view('actas.vista-previa', ['acta' => $acta, 'esReproceso' => $acta->planillas()->exists()] + $previa);
    }

    private function conError(Acta $acta, ?string $mensaje): View
    {
        return view('actas.error', [
            'acta' => $acta,
            'mensaje' => $mensaje,
            'puedeReprocesar' => $this->servicio->tienePdf($acta),
        ]);
    }
}
