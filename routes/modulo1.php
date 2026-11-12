<?php

use App\Http\Controllers\Actas\ActaController;
use App\Models\Acta;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas del módulo 1 · Actas
|--------------------------------------------------------------------------
| Solo preceptoría y administración (Policy "viewAny" de Acta). Cada acción vuelve a
| autorizar sobre el acta concreta en el controlador. "/actas/subir" va antes de
| "/actas/{acta}" para que no se tome "subir" por un identificador.
*/

Route::middleware(['auth', 'activo', 'cambio.password', 'can:viewAny,'.Acta::class])->group(function () {
    Route::get('/actas', [ActaController::class, 'index'])->name('actas.index');
    Route::get('/actas/subir', [ActaController::class, 'create'])->name('actas.create');
    Route::post('/actas/analizar', [ActaController::class, 'analizar'])->name('actas.analizar');
    Route::get('/actas/{acta}', [ActaController::class, 'show'])->name('actas.show');
    Route::post('/actas/{acta}/confirmar', [ActaController::class, 'confirmar'])->name('actas.confirmar');
    Route::get('/actas/{acta}/pdf', [ActaController::class, 'pdf'])->name('actas.pdf');
    Route::get('/actas/{acta}/planillas/{planilla}', [ActaController::class, 'planilla'])->name('actas.planilla');
});
