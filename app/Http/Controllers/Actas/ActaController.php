<?php

namespace App\Http\Controllers\Actas;

use App\Http\Controllers\Controller;
use App\Models\Acta;
use Illuminate\View\View;

/**
 * M1 · Actas. Las rutas exigen la Policy "viewAny" (preceptoría y administración).
 * Subir, analizar, confirmar, descargar y eliminar se agregan en los próximos bloques.
 */
class ActaController extends Controller
{
    /** M1-13: historial, del más reciente al más antiguo. */
    public function index(): View
    {
        $actas = Acta::with('user')->latest()->paginate(15);

        return view('actas.index', ['actas' => $actas]);
    }
}
