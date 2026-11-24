<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AnioCursada;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegistroAlumnoRequest;
use App\Services\RegistroAlumnoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** M3-01 · Registro público de alumnos (/registro). */
class RegistroController extends Controller
{
    public function create(): View
    {
        return view('auth.registro', ['anios' => AnioCursada::cases()]);
    }

    public function store(RegistroAlumnoRequest $request, RegistroAlumnoService $servicio): RedirectResponse
    {
        $servicio->registrar($request->validated());

        return redirect()->route('login')->with(
            'estado',
            'Te enviamos un correo para verificar tu dirección. Después, Preceptoría revisará tu registro y te avisará cuando tu cuenta esté activa.'
        );
    }
}
