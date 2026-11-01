<?php

namespace App\Http\Controllers\Auth;

use App\Enums\EstadoUsuario;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** M0-04 · Pedido de enlace de restablecimiento por correo. */
class OlvidePasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.olvide-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate(
            ['email' => ['required', 'email']],
            ['email.required' => 'Ingresá tu correo.', 'email.email' => 'Ingresá un correo válido.'],
        );

        // Solo las cuentas activas pueden restablecer por enlace.
        Password::sendResetLink([
            'email' => Str::lower(trim($datos['email'])),
            'estado' => EstadoUsuario::Activo->value,
        ]);

        // La respuesta es siempre la misma: no revela qué correos existen.
        return back()->with('estado', 'Si el correo corresponde a una cuenta activa, te enviamos un enlace para restablecer la contraseña. Revisá también la carpeta de spam.');
    }
}