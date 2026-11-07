<?php

namespace App\Http\Controllers\Calendario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calendario\StoreAvisoRequest;
use App\Http\Requests\Calendario\UpdateAvisoRequest;
use App\Models\Aviso;
use App\Models\Evento;
use App\Services\CalendarioAvisosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GestionAvisoController extends Controller
{
    public function __construct(private readonly CalendarioAvisosService $servicio) {}

    public function index(Request $request): View
    {
        Gate::authorize('gestionar', Aviso::class);

        $q = trim((string) $request->query('q', ''));
        $anio = (string) $request->query('anio', '');
        $estado = (string) $request->query('estado', '');

        $avisos = Aviso::query()
            ->with(['autor', 'evento'])
            ->filtrarAnio($anio)
            ->when($estado === 'publicado', fn ($query) => $query->whereNotNull('publicado_en'))
            ->when($estado === 'programado', fn ($query) => $query->whereNull('publicado_en')->whereNotNull('programado_para'))
            ->when($estado === 'borrador', fn ($query) => $query->whereNull('publicado_en')->whereNull('programado_para'))
            ->when($q !== '', function ($query) use ($q): void {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w
                    ->where('titulo', 'like', $like)
                    ->orWhere('contenido', 'like', $like));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('gestion.avisos.index', [
            'avisos' => $avisos,
            'q' => $q,
            'anio' => $anio,
            'estado' => $estado,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Aviso::class);

        $eventos = Evento::query()
            ->whereDate('fecha_fin', '>=', today()->subDays(30)->toDateString())
            ->orderBy('fecha_inicio')
            ->get();

        return view('gestion.avisos.create', [
            'eventos' => $eventos,
        ]);
    }

    public function store(StoreAvisoRequest $request): RedirectResponse
    {
        $aviso = $this->servicio->crearAviso($request->validated(), $request->user());

        $mensaje = match (true) {
            $aviso->estaPublicado() => 'El aviso fue publicado y se enviaron las notificaciones correspondientes.',
            $aviso->estaProgramado() => 'El aviso quedó programado para publicarse el '.$aviso->programado_para->format('d/m/Y \a \l\a\s H:i').' hs.',
            default => 'El aviso se guardó como borrador.',
        };

        return redirect()->route('gestion.avisos.index')->with('estado', $mensaje);
    }

    public function edit(Aviso $aviso): View
    {
        Gate::authorize('update', $aviso);

        $eventos = Evento::query()
            ->where(function ($q) use ($aviso): void {
                $q->whereDate('fecha_fin', '>=', today()->subDays(30)->toDateString());
                if ($aviso->evento_id !== null) {
                    $q->orWhere('id', $aviso->evento_id);
                }
            })
            ->orderBy('fecha_inicio')
            ->get();

        return view('gestion.avisos.edit', [
            'aviso' => $aviso,
            'eventos' => $eventos,
        ]);
    }

    public function update(UpdateAvisoRequest $request, Aviso $aviso): RedirectResponse
    {
        $eraPublicado = $aviso->estaPublicado();
        $aviso = $this->servicio->actualizarAviso($aviso, $request->validated(), $request->user());

        $mensaje = match (true) {
            $eraPublicado => 'El aviso publicado se actualizó sin reenviar notificaciones.',
            $aviso->estaPublicado() => 'El aviso fue publicado y se notificó a los destinatarios.',
            $aviso->estaProgramado() => 'La programación del aviso se actualizó para el '.$aviso->programado_para->format('d/m/Y H:i').' hs.',
            default => 'El borrador del aviso se actualizó.',
        };

        return redirect()->route('gestion.avisos.index')->with('estado', $mensaje);
    }

    public function publicar(Request $request, Aviso $aviso): RedirectResponse
    {
        Gate::authorize('publicar', $aviso);

        $this->servicio->publicarAviso($aviso, $request->user());

        return redirect()->route('gestion.avisos.index')
            ->with('estado', 'El aviso se publicó y se notificó a los destinatarios.');
    }

    public function destroy(Request $request, Aviso $aviso): RedirectResponse
    {
        Gate::authorize('delete', $aviso);

        $this->servicio->eliminarAviso($aviso, $request->user());

        return redirect()->route('gestion.avisos.index')
            ->with('estado', 'El aviso fue eliminado.');
    }
}

