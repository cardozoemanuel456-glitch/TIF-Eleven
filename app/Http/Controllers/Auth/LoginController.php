<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const MAX_INTENTOS = 5;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $clave = Str::lower($datos['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($clave, self::MAX_INTENTOS)) {
            $segundos = RateLimiter::availableIn($clave);

            throw ValidationException::withMessages([
                'email' => "Demasiados intentos. Probá de nuevo en {$segundos} segundos.",
            ]);
        }

        $usuario = User::where('email', $datos['email'])->first();

        if (! $usuario || ! Hash::check($datos['password'], (string) $usuario->password)) {
            RateLimiter::hit($clave);

            throw ValidationException::withMessages([
                'email' => 'Las credenciales no son correctas.',
            ]);
        }

        // Solo se informa el estado de la cuenta a quien ya demostró conocer la contraseña.
        if (! $usuario->estaActivo()) {
            throw ValidationException::withMessages([
                'email' => $usuario->estado->mensajeDeAcceso(),
            ]);
        }

        RateLimiter::clear($clave);

        Auth::login($usuario, $request->boolean('recordarme'));
        $request->session()->regenerate();

        return redirect()->intended(route('inicio'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
