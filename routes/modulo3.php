<?php

use App\Http\Controllers\AlumnoController;
use App\Http\Controllers\Auth\RegistroController;
use App\Http\Controllers\Auth\VerificarEmailController;
use App\Http\Controllers\PerfilController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas del módulo 3 · Gestión de alumnos (Jira E4, Sprint 4)
|--------------------------------------------------------------------------
*/

// M3-01 / M3-05 · Registro público, con límite de intentos.
Route::middleware('guest')->group(function () {
    Route::get('/registro', [RegistroController::class, 'create'])->name('registro.create');
    Route::post('/registro', [RegistroController::class, 'store'])->middleware('throttle:5,1')->name('registro.store');
});

// M3-03 · Verificación de correo. El nombre "verification.verify" lo usa el enlace del correo.
Route::get('/verificar-email/{id}/{hash}', VerificarEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware(['auth', 'activo', 'cambio.password'])->group(function () {
    // M3-09 · Perfil propio.
    Route::get('/mi-perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::put('/mi-perfil', [PerfilController::class, 'update'])->name('perfil.update');

    // M3-06 y M3-07 · Preceptoría y administración (la autorización fina está en UserPolicy).
    Route::prefix('alumnos')->name('alumnos.')->group(function () {
        Route::get('/', [AlumnoController::class, 'index'])->name('index');
        Route::get('/{alumno}', [AlumnoController::class, 'show'])->name('show');
        Route::post('/{alumno}/aprobar', [AlumnoController::class, 'aprobar'])->name('aprobar');
        Route::post('/{alumno}/rechazar', [AlumnoController::class, 'rechazar'])->name('rechazar');
        Route::post('/{alumno}/desactivar', [AlumnoController::class, 'desactivar'])->name('desactivar');
        Route::post('/{alumno}/reactivar', [AlumnoController::class, 'reactivar'])->name('reactivar');
        Route::put('/{alumno}/aula', [AlumnoController::class, 'cambiarAnio'])->name('aula');
        Route::delete('/{alumno}', [AlumnoController::class, 'destroy'])->name('destroy');
    });
});
