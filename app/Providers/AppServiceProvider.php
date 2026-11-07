<?php

namespace App\Providers;

use App\Services\CalendarioAvisosService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('layouts.app', function ($vista): void {
            $usuario = Auth::user();

            if ($usuario === null || !Schema::hasTable('notifications')) {
                $vista->with([
                    'campanaNoLeidas' => 0,
                    'campanaUltimas' => collect(),
                ]);

                return;
            }

            $servicio = app(CalendarioAvisosService::class);
            $ultimas = $usuario->notifications()->limit(10)->get();

            $vista->with([
                'campanaNoLeidas' => $usuario->unreadNotifications()->count(),
                'campanaUltimas' => $servicio->presentarNotificaciones($ultimas),
            ]);
        });
    }
}
