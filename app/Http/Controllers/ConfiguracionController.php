<?php

namespace App\Http\Controllers;

use App\Enums\TipoEvento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ConfiguracionController extends Controller
{
    public function index(Request $request): View
    {
        return view('configuracion.index', [
            'usuario' => $request->user()->loadMissing('role'),
            'tiposEvento' => TipoEvento::cases(),
            'usaColoresPersonalizados' => TipoEvento::usaColoresPersonalizados(),
        ]);
    }

    public function actualizarColoresEventos(Request $request): RedirectResponse
    {
        $reglas = [
            'colores' => ['required', 'array'],
        ];

        foreach (TipoEvento::cases() as $tipo) {
            $reglas["colores.{$tipo->value}"] = ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        }

        $validados = $request->validate($reglas, [
            'colores.*.regex' => 'Cada color debe tener formato hexadecimal válido (#RRGGBB).',
        ]);

        $colores = [];
        foreach (TipoEvento::cases() as $tipo) {
            $colores[$tipo->value] = strtoupper((string) $validados['colores'][$tipo->value]);
        }

        Cache::forever(TipoEvento::CACHE_KEY_COLORES, $colores);

        return redirect()->route('configuracion.index')
            ->with('estado', 'Se guardó la personalización de colores para los eventos del calendario.');
    }

    public function restablecerColoresEventos(): RedirectResponse
    {
        Cache::forget(TipoEvento::CACHE_KEY_COLORES);

        return redirect()->route('configuracion.index')
            ->with('estado', 'Se restablecieron los colores de la leyenda original del calendario.');
    }
}
