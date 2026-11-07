<?php

namespace App\Http\Controllers\Calendario;

use App\Http\Controllers\Controller;
use App\Services\CalendarioAvisosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificacionController extends Controller
{
    public function __construct(private readonly CalendarioAvisosService $servicio) {}

    public function index(Request $request): View
    {
        $usuario = $request->user();
        $paginador = $usuario->notifications()->paginate(10);

        $items = $this->servicio->presentarNotificaciones($paginador->getCollection());

        return view('notificaciones.index', [
            'usuario' => $usuario,
            'paginador' => $paginador,
            'notificaciones' => $items,
            'noLeidasCount' => $usuario->unreadNotifications()->count(),
        ]);
    }

    /**
     * M2-09: marca una notificación puntual o todas como leídas.
     */
    public function leer(Request $request, ?string $id = null): RedirectResponse
    {
        $usuario = $request->user();
        $objetivoId = $id ?? ($request->filled('id') ? (string) $request->input('id') : null);

        if ($objetivoId !== null) {
            $notificacion = $usuario->notifications()->whereKey($objetivoId)->firstOrFail();

            if ($notificacion->read_at === null) {
                $notificacion->markAsRead();
            }

            $this->servicio->sincronizarLecturasDesdeNotificaciones(collect([$notificacion]), $usuario);

            if ($request->boolean('ir')) {
                $presentada = $this->servicio->presentarNotificaciones(collect([$notificacion]))->first();
                if (! empty($presentada['url'])) {
                    return redirect()->to($presentada['url']);
                }
            }

            return back()->with('estado', 'Notificación marcada como leída.');
        }

        $noLeidas = $usuario->unreadNotifications()->get();

        if ($noLeidas->isNotEmpty()) {
            $this->servicio->sincronizarLecturasDesdeNotificaciones($noLeidas, $usuario);
            $noLeidas->markAsRead();
        }

        return back()->with('estado', 'Todas las notificaciones se marcaron como leídas.');
    }

    /**
     * M2-11: cada usuario puede activar o desactivar el envío de correos desde su cuenta;
     * la campana en la aplicación se mantiene activa siempre.
     */
    public function actualizarPreferencias(Request $request): RedirectResponse
    {
        $request->validate([
            'notificar_email' => ['nullable', 'boolean'],
        ]);

        $activar = $request->boolean('notificar_email');

        $request->user()->forceFill([
            'notificar_email' => $activar,
        ])->save();

        $mensaje = $activar
            ? 'Activaste las notificaciones por correo electrónico.'
            : 'Desactivaste las notificaciones por correo. Seguirás viéndolas en la campana de Eleven.';

        return back()->with('estado', $mensaje);
    }
}

