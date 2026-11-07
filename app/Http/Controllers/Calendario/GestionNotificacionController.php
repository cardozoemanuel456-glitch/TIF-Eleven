<?php

namespace App\Http\Controllers\Calendario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calendario\StoreNotificacionProgramadaRequest;
use App\Models\Aviso;
use App\Models\Evento;
use App\Models\NotificacionProgramada;
use App\Services\CalendarioAvisosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

class GestionNotificacionController extends Controller
{
    public function __construct(private readonly CalendarioAvisosService $servicio) {}

    public function index(): View
    {
        Gate::authorize('viewAny', NotificacionProgramada::class);

        $programadas = NotificacionProgramada::query()
            ->with(['creador', 'evento', 'aviso'])
            ->orderByDesc('programada_para')
            ->orderByDesc('id')
            ->paginate(10);

        return view('gestion.notificaciones.index', [
            'programadas' => $programadas,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', NotificacionProgramada::class);

        $eventos = Evento::query()
            ->whereDate('fecha_fin', '>=', today()->toDateString())
            ->orderBy('fecha_inicio')
            ->get();

        $avisos = Aviso::query()
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        return view('gestion.notificaciones.create', [
            'eventos' => $eventos,
            'avisos' => $avisos,
        ]);
    }

    public function store(StoreNotificacionProgramadaRequest $request): RedirectResponse
    {
        try {
            $programada = $this->servicio->programarNotificacion($request->validated(), $request->user());
        } catch (Throwable) {
            return back()
                ->withInput()
                ->with('error', 'No se pudo programar la notificación. Intentá nuevamente.');
        }

        return redirect()->route('gestion.notificaciones.index')
            ->with('estado', 'La notificación se programó correctamente para el '.$programada->programada_para->format('d/m/Y \a \l\a\s H:i').' hs.');
    }

    public function enviarAhora(Request $request, NotificacionProgramada $notificacionProgramada): RedirectResponse
    {
        Gate::authorize('enviar', $notificacionProgramada);

        $ok = $this->servicio->despacharNotificacionProgramada($notificacionProgramada);

        return $ok
            ? redirect()->route('gestion.notificaciones.index')->with('estado', 'La notificación se envió correctamente.')
            : redirect()->route('gestion.notificaciones.index')->with('error', 'Ocurrió un error y la notificación no pudo enviarse.');
    }

    public function destroy(Request $request, NotificacionProgramada $notificacionProgramada): RedirectResponse
    {
        Gate::authorize('delete', $notificacionProgramada);

        $notificacionProgramada->delete();

        return redirect()->route('gestion.notificaciones.index')
            ->with('estado', 'La notificación programada fue eliminada.');
    }
}

