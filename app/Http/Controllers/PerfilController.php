<?php

namespace App\Http\Controllers;

use App\Http\Requests\Alumnos\ActualizarPerfilRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** M3-09 · Perfil propio del alumno (/mi-perfil). El personal no tiene esta pantalla. */
class PerfilController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->esAlumno(), 403);

        return view('perfil.editar', ['usuario' => $request->user()]);
    }

    public function update(ActualizarPerfilRequest $request): RedirectResponse
    {
        $usuario = $request->user();

        // fillable solo permite nombre, apellido, correo y contraseña; aquí llegan los tres primeros.
        $usuario->fill($request->validated());
        $correoCambio = $usuario->isDirty('email');

        if ($correoCambio) {
            $usuario->email_verified_at = null;
        }

        $usuario->save();

        if ($correoCambio) {
            $usuario->sendEmailVerificationNotification();

            return back()->with('estado', 'Guardamos tus datos. Te enviamos un correo para verificar la nueva dirección.');
        }

        return back()->with('estado', 'Guardamos tus datos.');
    }
}
