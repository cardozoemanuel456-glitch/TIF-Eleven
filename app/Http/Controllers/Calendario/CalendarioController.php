<?php

namespace App\Http\Controllers\Calendario;

use App\Enums\TipoEvento;
use App\Http\Controllers\Controller;
use App\Models\Aviso;
use App\Models\Evento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

class CalendarioController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Evento::class);

        $usuario = $request->user()->loadMissing('role');
        $puedeGestionar = $usuario->can('gestionar', Evento::class);

        $filtroAnio = $usuario->esPersonal() ? (string) $request->query('anio', '') : '';
        $filtroTipo = (string) $request->query('tipo', '');

        $proximosEventos = Evento::query()
            ->visiblesPara($usuario)
            ->when($usuario->esPersonal(), fn ($q) => $q->filtrarAnio($filtroAnio))
            ->when(in_array($filtroTipo, TipoEvento::valores(), true), fn ($q) => $q->where('tipo', $filtroTipo))
            ->proximos()
            ->limit(8)
            ->get();

        $avisosRecientes = Aviso::query()
            ->publicados()
            ->visiblesPara($usuario)
            ->when($usuario->esPersonal(), fn ($q) => $q->filtrarAnio($filtroAnio))
            ->orderByDesc('publicado_en')
            ->orderByDesc('id')
            ->limit(4)
            ->get();

        return view('calendario.index', [
            'usuario' => $usuario,
            'puedeGestionar' => $puedeGestionar,
            'tipos' => TipoEvento::cases(),
            'filtroAnio' => $filtroAnio,
            'filtroTipo' => $filtroTipo,
            'proximosEventos' => $proximosEventos,
            'avisosRecientes' => $avisosRecientes,
        ]);
    }

    /**
     * M2-03: endpoint JSON para FullCalendar con filtrado y permisos en servidor.
     */
    public function eventos(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Evento::class);

        $usuario = $request->user()->loadMissing('role');
        $puedeGestionar = $usuario->can('gestionar', Evento::class);

        try {
            $inicio = $request->filled('start')
                ? Carbon::parse((string) $request->query('start'))->toDateString()
                : now()->startOfMonth()->subDays(7)->toDateString();

            $fin = $request->filled('end')
                ? Carbon::parse((string) $request->query('end'))->toDateString()
                : now()->endOfMonth()->addDays(7)->toDateString();
        } catch (Throwable) {
            return response()->json(['message' => 'Rango de fechas inválido.'], 422);
        }

        $filtroAnio = $usuario->esPersonal() ? (string) $request->query('anio', '') : '';
        $filtroTipo = (string) $request->query('tipo', '');

        $eventos = Evento::query()
            ->visiblesPara($usuario)
            ->when($usuario->esPersonal(), fn ($q) => $q->filtrarAnio($filtroAnio))
            ->when(in_array($filtroTipo, TipoEvento::valores(), true), fn ($q) => $q->where('tipo', $filtroTipo))
            ->enRango($inicio, $fin)
            ->orderBy('fecha_inicio')
            ->orderBy('hora')
            ->orderBy('id')
            ->get()
            ->map(fn (Evento $evento): array => $evento->aEventoFullCalendar($puedeGestionar))
            ->values();

        return response()->json($eventos);
    }
}

