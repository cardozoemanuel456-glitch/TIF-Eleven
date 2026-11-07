<?php

namespace App\Http\Controllers\Calendario;

use App\Enums\TipoEvento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Calendario\StoreEventoRequest;
use App\Http\Requests\Calendario\UpdateEventoRequest;
use App\Models\Evento;
use App\Services\CalendarioAvisosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GestionEventoController extends Controller
{
    public function __construct(private readonly CalendarioAvisosService $servicio) {}

    public function index(Request $request): View
    {
        Gate::authorize('gestionar', Evento::class);

        $q = trim((string) $request->query('q', ''));
        $anio = (string) $request->query('anio', '');
        $tipo = (string) $request->query('tipo', '');

        $eventos = Evento::query()
            ->with('creador')
            ->filtrarAnio($anio)
            ->when(in_array($tipo, TipoEvento::valores(), true), fn ($query) => $query->where('tipo', $tipo))
            ->when($q !== '', function ($query) use ($q): void {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w
                    ->where('titulo', 'like', $like)
                    ->orWhere('descripcion', 'like', $like));
            })
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('gestion.eventos.index', [
            'eventos' => $eventos,
            'tipos' => TipoEvento::cases(),
            'q' => $q,
            'anio' => $anio,
            'tipo' => $tipo,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Evento::class);

        $fechaSugerida = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('fecha', ''))
            ? (string) $request->query('fecha')
            : null;

        return view('gestion.eventos.create', [
            'tipos' => TipoEvento::cases(),
            'fechaSugerida' => $fechaSugerida,
        ]);
    }

    public function store(StoreEventoRequest $request): RedirectResponse
    {
        $this->servicio->crearEvento($request->validated(), $request->user());

        return redirect()->route('gestion.eventos.index')
            ->with('estado', 'El evento se registró en el calendario académico.');
    }

    public function edit(Evento $evento): View
    {
        Gate::authorize('update', $evento);

        return view('gestion.eventos.edit', [
            'evento' => $evento,
            'tipos' => TipoEvento::cases(),
        ]);
    }

    public function update(UpdateEventoRequest $request, Evento $evento): RedirectResponse
    {
        $reprogramado = $this->servicio->actualizarEvento($evento, $request->validated(), $request->user());

        $mensaje = $reprogramado
            ? 'El evento se actualizó y se notificó la reprogramación a los alumnos.'
            : 'Los cambios del evento se guardaron.';

        return redirect()->route('gestion.eventos.index')->with('estado', $mensaje);
    }

    public function destroy(Request $request, Evento $evento): RedirectResponse
    {
        Gate::authorize('delete', $evento);

        $this->servicio->eliminarEvento($evento, $request->user());

        return redirect()->route('gestion.eventos.index')
            ->with('estado', 'El evento fue eliminado del calendario.');
    }
}

