<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\InicioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas del módulo 0 · Base y seguridad
|--------------------------------------------------------------------------
| Se completa parte por parte. Los demás módulos agregan sus rutas dentro del
| grupo "auth + activo".
*/

Route::get('/', fn () => redirect()->route('inicio'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    // Fuera del grupo "activo" para que una cuenta desactivada también pueda salir.
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('activo')->group(function () {
        Route::get('/inicio', InicioController::class)->name('inicio');
    });
});
