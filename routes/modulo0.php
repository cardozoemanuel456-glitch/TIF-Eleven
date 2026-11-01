<?php

use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Auth\CambioPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OlvidePasswordController;
use App\Http\Controllers\Auth\RestablecerPasswordController;
use App\Http\Controllers\InicioController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas del módulo 0 · Base y seguridad
|--------------------------------------------------------------------------
| Los demás módulos agregan sus rutas dentro del grupo "auth + activo + cambio.password".
*/

Route::get('/', fn () => redirect()->route('inicio'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    // Restablecer contraseña por enlace (M0-04). El nombre "password.reset" lo usa el correo.
    Route::get('/olvide-password', [OlvidePasswordController::class, 'create'])->name('password.request');
    Route::post('/olvide-password', [OlvidePasswordController::class, 'store'])
        ->middleware('throttle:5,1')->name('password.email');
    Route::get('/restablecer-password/{token}', [RestablecerPasswordController::class, 'create'])->name('password.reset');
    Route::post('/restablecer-password', [RestablecerPasswordController::class, 'store'])
        ->middleware('throttle:5,1')->name('password.store');
});

Route::middleware('auth')->group(function () {
    // Fuera del grupo "activo" para que una cuenta desactivada también pueda salir.
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('activo')->group(function () {
        // Fuera de "cambio.password" para evitar un bucle de redirecciones.
        Route::get('/cuenta/password', [CambioPasswordController::class, 'edit'])->name('password.cambiar');
        Route::put('/cuenta/password', [CambioPasswordController::class, 'update'])->name('password.actualizar');

        Route::middleware('cambio.password')->group(function () {
            Route::get('/inicio', InicioController::class)->name('inicio');

            // M0-10 · Solo el administrador (Policy "viewAny" de User).
            Route::middleware('can:viewAny,'.User::class)
                ->prefix('admin/usuarios')->name('admin.usuarios.')->group(function () {
                    Route::get('/', [UsuarioController::class, 'index'])->name('index');
                    Route::get('/crear', [UsuarioController::class, 'create'])->name('create');
                    Route::post('/', [UsuarioController::class, 'store'])->name('store');
                    Route::get('/{user}/editar', [UsuarioController::class, 'edit'])->name('edit');
                    Route::put('/{user}', [UsuarioController::class, 'update'])->name('update');
                    Route::post('/{user}/desactivar', [UsuarioController::class, 'desactivar'])->name('desactivar');
                    Route::post('/{user}/reactivar', [UsuarioController::class, 'reactivar'])->name('reactivar');
                    Route::post('/{user}/password-temporal', [UsuarioController::class, 'passwordTemporal'])->name('password-temporal');
                    Route::post('/{user}/enlace-restablecer', [UsuarioController::class, 'enlaceRestablecer'])->name('enlace');
                });
        });
    });
});