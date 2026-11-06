<?php

use App\Http\Controllers\Actas\ActaController;
use App\Models\Acta;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas del módulo 1 · Actas
|--------------------------------------------------------------------------
| Solo preceptoría y administración (Policy "viewAny" de Acta). Cada acción vuelve a
| autorizar sobre el acta concreta en el controlador.
*/

Route::middleware(['auth', 'activo', 'cambio.password', 'can:viewAny,'.Acta::class])->group(function () {
    Route::get('/actas', [ActaController::class, 'index'])->name('actas.index');
});
