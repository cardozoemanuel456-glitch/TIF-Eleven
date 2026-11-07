<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Eleven')</title>
    {{-- Tailwind por CDN, igual que el prototipo. En producción conviene compilarlo con Vite. --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
    @stack('estilos')
</head>
<body class="bg-slate-100 text-slate-900 antialiased">
@php
    $enlace = 'block rounded-md px-3 py-2 text-sm text-slate-200 hover:bg-white/10';
    $activo = 'block rounded-md px-3 py-2 text-sm bg-[#1F4E8C] text-white font-medium';
    $usuarioActual = auth()->user();
    $campanaNoLeidas = $campanaNoLeidas ?? 0;
    $campanaUltimas = $campanaUltimas ?? collect();
@endphp
<div class="flex min-h-screen flex-col md:flex-row">
    <nav class="bg-[#12263F] p-4 md:w-60 md:flex-none">
        <div class="flex items-center justify-between px-3 pb-6">
            <a href="{{ route('inicio') }}" class="text-2xl font-semibold text-white">Eleven</a>
        </div>

        <div class="flex flex-wrap gap-1 md:flex-col">
            <a href="{{ route('inicio') }}" class="{{ request()->routeIs('inicio') ? $activo : $enlace }}">Inicio</a>

            @if (Route::has('calendario.index'))
                <a href="{{ route('calendario.index') }}"
                   class="{{ request()->routeIs('calendario.*') ? $activo : $enlace }}">Calendario</a>
            @endif

            @if (Route::has('avisos.index'))
                <a href="{{ route('avisos.index') }}"
                   class="{{ request()->routeIs('avisos.*') ? $activo : $enlace }}">Avisos</a>
            @endif

            @if (Route::has('notificaciones.index'))
                <a href="{{ route('notificaciones.index') }}"
                   class="flex items-center justify-between {{ request()->routeIs('notificaciones.*') ? $activo : $enlace }}">
                    <span>Notificaciones</span>
                    @if ($campanaNoLeidas > 0)
                        <span class="rounded-full bg-amber-400 px-2 py-0.5 text-xs font-semibold text-slate-900">
                            {{ $campanaNoLeidas > 99 ? '99+' : $campanaNoLeidas }}
                        </span>
                    @endif
                </a>
            @endif

            @can('gestionar', \App\Models\Evento::class)
                <div class="mt-2 hidden border-t border-white/10 pt-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400 md:block md:px-3">
                    Comunicaciones
                </div>
                @if (Route::has('gestion.eventos.index'))
                    <a href="{{ route('gestion.eventos.index') }}"
                       class="{{ request()->routeIs('gestion.eventos.*') ? $activo : $enlace }}">Gestión de eventos</a>
                @endif
                @if (Route::has('gestion.avisos.index'))
                    <a href="{{ route('gestion.avisos.index') }}"
                       class="{{ request()->routeIs('gestion.avisos.*') ? $activo : $enlace }}">Gestión de avisos</a>
                @endif
                @if (Route::has('gestion.notificaciones.index'))
                    <a href="{{ route('gestion.notificaciones.index') }}"
                       class="{{ request()->routeIs('gestion.notificaciones.*') ? $activo : $enlace }}">Programar recordatorios</a>
                @endif
            @endcan

            {{-- Cada acceso aparece solo si la Policy lo permite y la ruta ya existe. --}}
            @can('viewAny', \App\Models\User::class)
                @if (Route::has('admin.usuarios.index'))
                    <div class="mt-2 hidden border-t border-white/10 pt-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400 md:block md:px-3">
                        Administración
                    </div>
                    <a href="{{ route('admin.usuarios.index') }}"
                       class="{{ request()->routeIs('admin.usuarios.*') ? $activo : $enlace }}">Usuarios</a>
                @endif
            @endcan
        </div>

        <div class="mt-6 border-t border-white/10 pt-4">
            <div class="px-3 pb-2 text-xs text-slate-300">
                {{ $usuarioActual->nombreCompleto() }}<br>
                {{ $usuarioActual->role?->nombre?->etiqueta() }}
            </div>
            <a href="{{ route('password.cambiar') }}"
               class="{{ request()->routeIs('password.*') ? $activo : $enlace }}">Cambiar contraseña</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="{{ $enlace }} w-full text-left">Cerrar sesión</button>
            </form>
        </div>
    </nav>

    <div class="flex min-w-0 flex-1 flex-col">
        {{-- Barra superior con campana de notificaciones (M2-09) --}}
        @if (Route::has('notificaciones.index'))
            <header class="flex items-center justify-end border-b border-slate-200 bg-white px-6 py-3 md:px-10">
                <div x-data="{ abierto: false }" class="relative">
                    <button type="button"
                            @click="abierto = !abierto"
                            @keydown.escape.window="abierto = false"
                            aria-label="Notificaciones"
                            class="relative inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        <svg class="h-5 w-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                        </svg>
                        <span class="hidden sm:inline">Campana</span>
                        @if ($campanaNoLeidas > 0)
                            <span class="rounded-full bg-[#1F4E8C] px-2 py-0.5 text-xs font-semibold text-white">
                                {{ $campanaNoLeidas > 99 ? '99+' : $campanaNoLeidas }}
                            </span>
                        @endif
                    </button>

                    <div x-cloak
                         x-show="abierto"
                         @click.outside="abierto = false"
                         class="absolute right-0 z-50 mt-2 w-80 sm:w-96 rounded-xl border border-slate-200 bg-white shadow-lg">
                        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                            <span class="text-sm font-semibold text-slate-900">Últimas notificaciones</span>
                            @if ($campanaNoLeidas > 0)
                                <form method="POST" action="{{ route('notificaciones.leer') }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-medium text-[#1F4E8C] underline hover:text-[#12263F]">
                                        Marcar todas
                                    </button>
                                </form>
                            @endif
                        </div>

                        <div class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                            @forelse ($campanaUltimas as $notif)
                                <div class="px-4 py-3 {{ $notif['leida'] ? 'bg-white' : 'bg-blue-50/50' }}">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            @if (! $notif['disponible'])
                                                <p class="text-sm font-medium italic text-slate-500">Aviso no disponible</p>
                                            @elseif ($notif['url'])
                                                <a href="{{ $notif['url'] }}" class="block truncate text-sm font-medium text-slate-900 hover:text-[#1F4E8C] hover:underline">
                                                    {{ $notif['titulo'] }}
                                                </a>
                                                <p class="mt-0.5 line-clamp-2 text-xs text-slate-600">{{ $notif['mensaje'] }}</p>
                                            @else
                                                <p class="text-sm font-medium text-slate-900">{{ $notif['titulo'] }}</p>
                                                <p class="mt-0.5 line-clamp-2 text-xs text-slate-600">{{ $notif['mensaje'] }}</p>
                                            @endif
                                            <p class="mt-1 text-[11px] text-slate-400">
                                                {{ $notif['creada_en']?->diffForHumans() }}
                                            </p>
                                        </div>
                                        @if (! $notif['leida'])
                                            <form method="POST" action="{{ route('notificaciones.leer.una', $notif['id']) }}">
                                                @csrf
                                                <button type="submit" title="Marcar como leída" class="rounded px-1.5 py-0.5 text-xs font-medium text-[#1F4E8C] hover:bg-blue-100">
                                                    Leída
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <p class="px-4 py-6 text-center text-sm text-slate-500">No tenés notificaciones todavía.</p>
                            @endforelse
                        </div>

                        <div class="border-t border-slate-100 px-4 py-2.5 text-center">
                            <a href="{{ route('notificaciones.index') }}" class="text-xs font-medium text-[#1F4E8C] underline">
                                Ver todas y preferencias de correo
                            </a>
                        </div>
                    </div>
                </div>
            </header>
        @endif

        <main class="min-w-0 flex-1 p-6 md:p-10">
            @if (session('estado'))
                <div class="mb-4 rounded-md border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-900">
                    {{ session('estado') }}
                </div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900">
                    {{ session('error') }}
                </div>
            @endif

            @yield('contenido')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
