<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * M0-03: una cuenta creada por el administrador (o con contraseña temporal)
 * no puede usar el sistema hasta cambiar su contraseña.
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && $usuario->debe_cambiar_password && ! $request->routeIs('password.cambiar', 'password.actualizar')) {
            if ($request->expectsJson()) {
                abort(403, 'Debés cambiar tu contraseña antes de continuar.');
            }

            return redirect()->route('password.cambiar');
        }

        return $next($request);
    }
}