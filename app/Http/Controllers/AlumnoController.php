<?php

namespace App\Http\Controllers;

use App\Enums\AnioCursada;
use App\Enums\EstadoUsuario;
use App\Http\Requests\Alumnos\CambiarAnioRequest;
use App\Http\Requests\Alumnos\EliminarAlumnoRequest;
use App\Models\User;
use App\Services\GestionAlumnosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * M3 · Gestión de alumnos para Preceptoría y Administración.
 * Cada acción pasa por una Policy (UserPolicy): aunque se escriba la URL, sin permiso hay 403.
 */
class AlumnoController extends Controller
{
    /** M3-06 · Listado con búsqueda y filtros; solo cuentas con correo verificado. */
    public function index(Request $request): View
    {
        Gate::authorize('listarAlumnos', User::class);

        $q = trim((string) $request->query('q', ''));
        $anio = (string) $request->query('anio', '');
        $estado = (string) $request->query('estado', '');

        $alumnos = User::alumnos()
            ->whereNotNull('email_verified_at')
            ->when($q !== '', function ($consulta) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $consulta->where(function ($w) use ($like) {
                    $w->where('nombre', 'like', $like)
                        ->orWhere('apellido', 'like', $like)
                        ->orWhere('dni', 'like', $like);
                });
            })
            ->when(in_array($anio, ['1', '2'], true), fn ($c) => $c->where('anio_cursada', (int) $anio))
            ->when(EstadoUsuario::tryFrom($estado), fn ($c, $e) => $c->where('estado', $e->value))
            // Las cuentas pendientes primero, para que no pasen inadvertidas.
            ->orderByRaw("case estado when 'pendiente' then 0 else 1 end")
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('alumnos.index', [
            'alumnos' => $alumnos,
            'q' => $q,
            'anio' => $anio,
            'estado' => $estado,
            'anios' => AnioCursada::cases(),
            'estados' => EstadoUsuario::cases(),
        ]);
    }

    public function show(Request $request, User $alumno): View
    {
        Gate::authorize('gestionarAlumno', $alumno);

        // Trazabilidad: acceso del personal a un perfil ajeno, sin datos personales.
        Log::info('alumno.consultado', ['actor' => $request->user()->id, 'usuario' => $alumno->id]);

        return view('alumnos.show', ['alumno' => $alumno->loadMissing('aprobadoPor'), 'anios' => AnioCursada::cases()]);
    }

    public function aprobar(Request $request, User $alumno, GestionAlumnosService $servicio): RedirectResponse
    {
        Gate::authorize('aprobarAlumno', $alumno);
        $servicio->aprobar($alumno, $request->user());

        return back()->with('estado', 'La cuenta fue aprobada. Le avisamos al alumno por correo.');
    }

    public function rechazar(Request $request, User $alumno, GestionAlumnosService $servicio): RedirectResponse
    {
        Gate::authorize('rechazarAlumno', $alumno);
        $servicio->rechazar($alumno, $request->user());

        return redirect()->route('alumnos.index')->with('estado', 'El registro fue rechazado y la cuenta se eliminó.');
    }

    public function desactivar(Request $request, User $alumno, GestionAlumnosService $servicio): RedirectResponse
    {
        Gate::authorize('desactivarAlumno', $alumno);
        $servicio->desactivar($alumno, $request->user());

        return back()->with('estado', 'La cuenta fue desactivada y se cerraron sus sesiones.');
    }

    public function reactivar(Request $request, User $alumno, GestionAlumnosService $servicio): RedirectResponse
    {
        Gate::authorize('reactivarAlumno', $alumno);
        $servicio->reactivar($alumno, $request->user());

        return back()->with('estado', 'La cuenta fue reactivada.');
    }

    /** M3-07 · Solo Preceptoría y Administración cambian el aula. */
    public function cambiarAnio(CambiarAnioRequest $request, User $alumno, GestionAlumnosService $servicio): RedirectResponse
    {
        $servicio->cambiarAnio($alumno, AnioCursada::from((int) $request->validated('anio_cursada')), $request->user());

        return back()->with('estado', 'Se actualizó el aula del alumno.');
    }

    /** M3-11 · Eliminación definitiva (anonimización) de una cuenta ya inactiva. */
    public function destroy(EliminarAlumnoRequest $request, User $alumno, GestionAlumnosService $servicio): RedirectResponse
    {
        $servicio->anonimizar($alumno, $request->user());

        return redirect()->route('alumnos.index')->with('estado', 'El perfil fue eliminado: los datos personales se borraron de forma definitiva.');
    }
}
