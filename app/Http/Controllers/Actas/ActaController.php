<?php

namespace App\Http\Controllers\Actas;

use App\Enums\EstadoActa;
use App\Exceptions\ActaIlegibleException;
use App\Exceptions\ActaNoDisponibleException;
use App\Exceptions\PlanillaInvalidaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Actas\ConfirmarActaRequest;
use App\Http\Requests\Actas\SubirActaRequest;
use App\Models\Acta;
use App\Models\PlanillaExcel;
use App\Services\ActaService;
use Illuminate\Http\RedirectResponse;
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

    /** Vista previa si está analizada; informe si está procesada; mensaje si hubo un error. */
    public function show(Acta $acta): View
    {
        Gate::authorize('view', $acta);

        return match ($acta->estado) {
            EstadoActa::Analizada => $this->vistaPrevia($acta),
            EstadoActa::Procesada => view('actas.informe', ['acta' => $acta->load('planillas', 'user'), 'informe' => $acta->informe ?? []]),
            EstadoActa::Error => view('actas.error', ['acta' => $acta, 'mensaje' => $acta->mensaje_error]),
        };
    }

    public function confirmar(ConfirmarActaRequest $request, Acta $acta): RedirectResponse
    {
        try {
            $acta = $this->servicio->confirmar($acta, $request->validated('decision') ?? []);
        } catch (ActaNoDisponibleException $e) {
            return redirect()->route('actas.show', $acta)->with('error', $e->getMessage());
        }

        if ($acta->estado === EstadoActa::Error) {
            return redirect()->route('actas.show', $acta)->with('error', $acta->mensaje_error);
        }

        $cargadas = $acta->informe['notas_cargadas'] ?? 0;

        return redirect()->route('actas.show', $acta)->with(
            'estado',
            "Se cargaron {$cargadas} notas en la planilla. Descargala y usala como tu planilla de seguimiento vigente."
        );
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
            return view('actas.error', ['acta' => $acta, 'mensaje' => $e->getMessage()]);
        }

        return view('actas.vista-previa', ['acta' => $acta] + $previa);
    }
}
