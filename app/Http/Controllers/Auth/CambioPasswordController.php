<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CambiarPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CambioPasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.cambiar-password', [
            'obligatorio' => (bool) $request->user()->debe_cambiar_password,
        ]);
    }

    public function update(CambiarPasswordRequest $request): RedirectResponse
    {
        $usuario = $request->user();

        // forceFill: debe_cambiar_password no es asignable en masa.
        $usuario->forceFill([
            'password' => $request->validated('password'), // el cast "hashed" la encripta
            'debe_cambiar_password' => false,
        ])->save();

        $request->session()->regenerate();

        return redirect()->route('inicio')->with('estado', 'Tu contraseña se cambió correctamente.');
    }
}