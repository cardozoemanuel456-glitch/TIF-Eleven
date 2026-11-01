<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUsuarioRequest;
use App\Http\Requests\Admin\UpdateUsuarioRequest;
use App\Models\User;
use App\Services\GestionUsuariosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * M0-10 · Gestión de usuarios del personal. Todas las rutas exigen la Policy
 * "viewAny" (solo administrador) y cada acción vuelve a autorizar sobre la cuenta.
 */
class UsuarioController extends Controller
{
    public function __construct(private readonly GestionUsuariosService $servicio) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $usuarios = User::personal()
            ->with('role')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w
                    ->where('nombre', 'like', $like)
                    ->orWhere('apellido', 'like', $like)
                    ->orWhere('email', 'like', $like));
            })
            ->orderBy('apellido')->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('admin.usuarios.index', ['usuarios' => $usuarios, 'q' => $q]);
    }

    public function create(): View
    {
        return view('admin.usuarios.create');
    }

    public function store(StoreUsuarioRequest $request): RedirectResponse
    {
        [$usuario, $password] = $this->servicio->crearPersonal($request->validated(), $request->user());

        return redirect()->route('admin.usuarios.index')
            ->with('estado', "Se creó la cuenta de {$usuario->nombreCompleto()}.")
            ->with('password_temporal', ['nombre' => $usuario->nombreCompleto(), 'password' => $password]);
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.usuarios.edit', ['usuario' => $user->loadMissing('role')]);
    }

    public function update(UpdateUsuarioRequest $request, User $user): RedirectResponse
    {
        $this->servicio->actualizar($user, $request->validated(), $request->user());

        return redirect()->route('admin.usuarios.index')->with('estado', 'Los datos se guardaron.');
    }

    public function desactivar(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('desactivar', $user);
        $this->servicio->desactivar($user, $request->user());

        return back()->with('estado', "Se desactivó la cuenta de {$user->nombreCompleto()}. Sus sesiones se cerraron.");
    }

    public function reactivar(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('reactivar', $user);
        $this->servicio->reactivar($user, $request->user());

        return back()->with('estado', "Se reactivó la cuenta de {$user->nombreCompleto()}.");
    }

    public function passwordTemporal(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('restablecerPassword', $user);
        $password = $this->servicio->restablecerConTemporal($user, $request->user());

        return back()
            ->with('estado', 'Se generó una contraseña temporal.')
            ->with('password_temporal', ['nombre' => $user->nombreCompleto(), 'password' => $password]);
    }

    public function enlaceRestablecer(User $user): RedirectResponse
    {
        Gate::authorize('restablecerPassword', $user);

        if (! $user->email) {
            return back()->with('error', 'La cuenta no tiene correo. Generá una contraseña temporal.');
        }

        $resultado = Password::sendResetLink(['email' => $user->email, 'estado' => 'activo']);

        return $resultado === Password::RESET_LINK_SENT
            ? back()->with('estado', 'Se envió el enlace de restablecimiento al correo de la cuenta.')
            : back()->with('error', 'No se pudo enviar el enlace. La cuenta debe estar activa y no haber pedido otro hace menos de un minuto.');
    }
}