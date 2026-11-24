<?php

namespace App\Http\Controllers\Auth;

use App\Enums\EstadoUsuario;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * M3-03 y M3-04 · Verificación del correo. La ruta exige un enlace firmado y con
 * vencimiento ("signed"); no requiere sesión, porque el alumno todavía no puede ingresar.
 */
class VerificarEmailController extends Controller
{
    public function __invoke(Request $request, int $id, string $hash): RedirectResponse
    {
        $usuario = User::findOrFail($id);

        // El hash ata el enlace a la dirección actual: si el correo cambió, deja de servir.
        abort_unless(hash_equals(sha1((string) $usuario->getEmailForVerification()), $hash), 403);

        if (! $usuario->hasVerifiedEmail()) {
            $usuario->markEmailAsVerified();
        }

        $mensaje = $usuario->estado === EstadoUsuario::Pendiente
            ? 'Correo verificado. Preceptoría revisará tu registro y te avisará por email cuando tu cuenta esté activa.'
            : 'Correo verificado.';

        return redirect()->route('login')->with('estado', $mensaje);
    }
}
