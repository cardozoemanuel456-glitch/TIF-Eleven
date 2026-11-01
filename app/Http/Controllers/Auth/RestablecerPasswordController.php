<?php

namespace App\Http\Controllers\Auth;

use App\Enums\EstadoUsuario;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as ReglaPassword;
use Illuminate\View\View;

/** M0-04 · Pantalla a la que lleva el enlace del correo. El token es de un solo uso. */
class RestablecerPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.restablecer-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', ReglaPassword::min(8)->letters()->numbers()],
        ], [
            'email.required' => 'Ingresá tu correo.',
            'email.email' => 'Ingresá un correo válido.',
            'password.required' => 'Ingresá la nueva contraseña.',
            'password.confirmed' => 'La confirmación no coincide con la nueva contraseña.',
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.letters' => 'La nueva contraseña debe incluir al menos una letra.',
            'password.numbers' => 'La nueva contraseña debe incluir al menos un número.',
        ]);

        $resultado = Password::reset(
            [
                'email' => Str::lower(trim($datos['email'])),
                'estado' => EstadoUsuario::Activo->value,
                'password' => $datos['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $datos['token'],
            ],
            function (User $usuario, string $password): void {
                $usuario->forceFill([
                    'password' => $password,
                    'debe_cambiar_password' => false,
                    'remember_token' => Str::random(60),
                ])->save();

                $usuario->cerrarSesiones();
                event(new PasswordReset($usuario));
            },
        );

        if ($resultado !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'El enlace no es válido o ya venció. Pedí uno nuevo.']);
        }

        return redirect()->route('login')->with('estado', 'Tu contraseña se restableció. Ya podés ingresar.');
    }
}