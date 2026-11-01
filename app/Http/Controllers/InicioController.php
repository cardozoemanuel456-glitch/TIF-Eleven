<?php

namespace App\Http\Controllers;

use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InicioController extends Controller
{
    public function __invoke(Request $request): View
    {
        $usuario = $request->user()->loadMissing('role');

        // Solo preceptoría y administración ven el contador (M0-05). Se cuentan los
        // alumnos con correo verificado que esperan aprobación, igual que el listado de M3.
        $pendientes = $usuario->tieneRol(Rol::Preceptor, Rol::Administrador)
            ? User::alumnos()
                ->where('estado', EstadoUsuario::Pendiente->value)
                ->whereNotNull('email_verified_at')
                ->count()
            : null;

        return view('inicio', [
            'usuario' => $usuario,
            'pendientes' => $pendientes,
        ]);
    }
}