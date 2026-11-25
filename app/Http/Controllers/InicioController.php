<?php

namespace App\Http\Controllers;

use App\Enums\EstadoUsuario;
use App\Enums\Rol;
use App\Models\Aviso;
use App\Models\AvisoLectura;
use App\Models\Evento;
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

        // M0-05 y HU 17: avisos publicados vigentes y próximos eventos según permisos del usuario.
        $avisos = Aviso::query()
            ->publicados()
            ->visiblesPara($usuario)
            ->with('evento')
            ->orderByDesc('publicado_en')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $avisosLeidosIds = AvisoLectura::query()
            ->where('user_id', $usuario->id)
            ->whereIn('aviso_id', $avisos->pluck('id'))
            ->pluck('aviso_id')
            ->flip();

        $proximosEventos = Evento::query()
            ->visiblesPara($usuario)
            ->proximos()
            ->limit(5)
            ->get();

        return view('inicio', [
            'usuario' => $usuario,
            'pendientes' => $pendientes,
            'avisos' => $avisos,
            'avisosLeidosIds' => $avisosLeidosIds,
            'proximosEventos' => $proximosEventos,
            'puedeGestionarComunicaciones' => $usuario->can('gestionar', Aviso::class),
        ]);
    }
}
