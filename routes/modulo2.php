<?php

use App\Http\Controllers\Calendario\AvisoController;
use App\Http\Controllers\Calendario\CalendarioController;
use App\Http\Controllers\Calendario\GestionAvisoController;
use App\Http\Controllers\Calendario\GestionEventoController;
use App\Http\Controllers\Calendario\GestionNotificacionController;
use App\Http\Controllers\Calendario\NotificacionController;
use App\Models\Aviso;
use App\Models\Evento;
use App\Models\NotificacionProgramada;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas del módulo 2 · Calendario, avisos y notificaciones
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'activo', 'cambio.password'])->group(function () {
    // Calendario académico (M2-01 a M2-05, HU 12)
    Route::get('/calendario', [CalendarioController::class, 'index'])->name('calendario.index');
    Route::get('/calendario/eventos', [CalendarioController::class, 'eventos'])->name('calendario.eventos');

    // Avisos institucionales publicados (M2-06 a M2-08, HU 15)
    Route::get('/avisos', [AvisoController::class, 'index'])->name('avisos.index');
    Route::get('/avisos/{aviso}', [AvisoController::class, 'show'])->name('avisos.show');

    // Notificaciones en la aplicación y preferencias de correo (M2-09 a M2-11)
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::post('/notificaciones/leer', [NotificacionController::class, 'leer'])->name('notificaciones.leer');
    Route::post('/notificaciones/{id}/leer', [NotificacionController::class, 'leer'])->name('notificaciones.leer.una');
    Route::put('/cuenta/notificaciones', [NotificacionController::class, 'actualizarPreferencias'])->name('cuenta.notificaciones');

    // Gestión de eventos (solo preceptoría y administración · HU 13 y HU 14)
    Route::middleware('can:gestionar,'.Evento::class)
        ->prefix('gestion/eventos')->name('gestion.eventos.')->group(function () {
            Route::get('/', [GestionEventoController::class, 'index'])->name('index');
            Route::get('/crear', [GestionEventoController::class, 'create'])->name('create');
            Route::post('/', [GestionEventoController::class, 'store'])->name('store');
            Route::get('/{evento}/editar', [GestionEventoController::class, 'edit'])->name('edit');
            Route::put('/{evento}', [GestionEventoController::class, 'update'])->name('update');
            Route::delete('/{evento}', [GestionEventoController::class, 'destroy'])->name('destroy');
        });

    // Gestión de avisos (solo preceptoría y administración · HU 15)
    Route::middleware('can:gestionar,'.Aviso::class)
        ->prefix('gestion/avisos')->name('gestion.avisos.')->group(function () {
            Route::get('/', [GestionAvisoController::class, 'index'])->name('index');
            Route::get('/crear', [GestionAvisoController::class, 'create'])->name('create');
            Route::post('/', [GestionAvisoController::class, 'store'])->name('store');
            Route::get('/{aviso}/editar', [GestionAvisoController::class, 'edit'])->name('edit');
            Route::put('/{aviso}', [GestionAvisoController::class, 'update'])->name('update');
            Route::post('/{aviso}/publicar', [GestionAvisoController::class, 'publicar'])->name('publicar');
            Route::delete('/{aviso}', [GestionAvisoController::class, 'destroy'])->name('destroy');
        });

    // Programación de notificaciones y recordatorios (solo preceptoría y administración · HU 16)
    Route::middleware('can:gestionar,'.NotificacionProgramada::class)
        ->prefix('gestion/notificaciones')->name('gestion.notificaciones.')->group(function () {
            Route::get('/', [GestionNotificacionController::class, 'index'])->name('index');
            Route::get('/crear', [GestionNotificacionController::class, 'create'])->name('create');
            Route::post('/', [GestionNotificacionController::class, 'store'])->name('store');
            Route::post('/{notificacionProgramada}/enviar', [GestionNotificacionController::class, 'enviarAhora'])->name('enviar');
            Route::delete('/{notificacionProgramada}', [GestionNotificacionController::class, 'destroy'])->name('destroy');
        });
});

