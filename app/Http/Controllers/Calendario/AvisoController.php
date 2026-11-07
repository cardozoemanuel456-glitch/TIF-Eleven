<?php

namespace App\Http\Controllers\Calendario;

use App\Http\Controllers\Controller;
use App\Models\Aviso;
use App\Models\AvisoLectura;
use App\Services\CalendarioAvisosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AvisoController extends Controller
{
    public function __construct(private readonly CalendarioAvisosService $servicio) {}

    /**
     * M2-08: listado paginado de 10 en 10, del más reciente al más antiguo,
     * con visibilidad por año y marcando los avisos aún no leídos.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Aviso::class);

        $usuario = $request->user()->loadMissing('role');
        $filtroAnio = $usuario->esPersonal() ? (string) $request->query('anio', '') : '';

        $avisos = Aviso::query()
            ->publicados()
            ->visiblesPara($usuario)
            ->when($usuario->esPersonal(), fn ($q) => $q->filtrarAnio($filtroAnio))
            ->with(['autor', 'evento'])
            ->orderByDesc('publicado_en')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $leidosIds = AvisoLectura::query()
            ->where('user_id', $usuario->id)
            ->whereIn('aviso_id', $avisos->pluck('id'))
            ->pluck('aviso_id')
            ->flip();

        $puedeGestionar = $usuario->can('gestionar', Aviso::class);
        $noPublicadosCount = $puedeGestionar
            ? Aviso::whereNull('publicado_en')->count()
            : 0;

        return view('avisos.index', [
            'usuario' => $usuario,
            'avisos' => $avisos,
            'leidosIds' => $leidosIds,
            'filtroAnio' => $filtroAnio,
            'puedeGestionar' => $puedeGestionar,
            'noPublicadosCount' => $noPublicadosCount,
        ]);
    }

    public function show(Request $request, Aviso $aviso): View
    {
        Gate::authorize('view', $aviso);

        $usuario = $request->user()->loadMissing('role');

        if ($aviso->estaPublicado()) {
            $this->servicio->marcarAvisoComoLeido($aviso, $usuario);
        }

        return view('avisos.show', [
            'usuario' => $usuario,
            'aviso' => $aviso->loadMissing(['autor', 'evento']),
            'puedeGestionar' => $usuario->can('gestionar', Aviso::class),
        ]);
    }
}

