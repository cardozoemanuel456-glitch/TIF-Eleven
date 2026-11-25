<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Eleven')</title>
    {{-- Tailwind por CDN, igual que el prototipo. En producción conviene compilarlo con Vite. --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
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
        <nav class="flex flex-col justify-between bg-[#12263F] p-4 md:w-60 md:flex-none">
            <div>
                <div class="px-3 pb-6">
                    <a href="{{ route('inicio') }}" class="text-2xl font-semibold text-white">Eleven</a>
                </div>

                <div class="flex flex-wrap gap-1 md:flex-col">
                    <a href="{{ route('inicio') }}"
                        class="{{ request()->routeIs('inicio') ? $activo : $enlace }}">Inicio</a>

                    {{-- Cada acceso aparece solo si la Policy lo permite y la ruta ya existe. --}}
                    @can('viewAny', \App\Models\Acta::class)
                        @if (Route::has('actas.index'))
                            <a href="{{ route('actas.index') }}"
                                class="{{ request()->routeIs('actas.*') ? $activo : $enlace }}">Actas</a>
                        @endif
                    @endcan

                    @if (Route::has('avisos.index'))
                        <a href="{{ route('avisos.index') }}"
                            class="{{ request()->routeIs('avisos.*', 'gestion.avisos.*') ? $activo : $enlace }}">Avisos</a>
                    @endif

                    @if (Route::has('calendario.index'))
                        <a href="{{ route('calendario.index') }}"
                            class="{{ request()->routeIs('calendario.*', 'gestion.eventos.*') ? $activo : $enlace }}">Calendario</a>
                    @endif
                    {{-- Los demás accesos (alumnos, TIF) se agregan con cada módulo. --}}
                </div>
            </div>

            <div class="mt-6 border-t border-white/10 pt-4">
                <div class="px-3 text-xs text-slate-300">
                    <span class="font-medium text-white">{{ $usuarioActual->nombreCompleto() }}</span><br>
                    {{ $usuarioActual->role?->nombre?->etiqueta() }}
                    @if ($usuarioActual->anio_cursada)
                        · {{ $usuarioActual->anio_cursada->etiqueta() }}
                    @endif
                </div>
            </div>
        </nav>

        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Barra superior: campana de notificaciones + engranaje de configuración en la esquina --}}
            <header class="flex items-center justify-end gap-3 border-b border-slate-200 bg-white px-6 py-3 md:px-10">
                @if (Route::has('notificaciones.index'))
                    <div x-data="{ abierto: false }" class="relative">
                        <button type="button" @click="abierto = !abierto" @keydown.escape.window="abierto = false"
                            aria-label="Notificaciones"
                            class="relative inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-medium transition {{ $campanaNoLeidas > 0 ? 'border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-100' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">
                            <svg class="h-5 w-5 {{ $campanaNoLeidas > 0 ? 'text-amber-700' : 'text-slate-600' }}"
                                fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                            </svg>
                            @if ($campanaNoLeidas > 0)
                                <span class="text-xs font-semibold">
                                    Notificaciones · {{ $campanaNoLeidas }} {{ $campanaNoLeidas === 1 ? 'nueva' : 'nuevas' }}
                                </span>
                            @else
                                <span class="hidden sm:inline text-xs">Notificaciones</span>
                            @endif
                        </button>

                        <div x-cloak x-show="abierto" @click.outside="abierto = false"
                            class="absolute right-0 z-50 mt-2 w-80 sm:w-96 rounded-xl border border-slate-200 bg-white shadow-lg">
                            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                                <span class="text-sm font-semibold text-slate-900">
                                    Notificaciones
                                    @if ($campanaNoLeidas > 0)
                                        · {{ $campanaNoLeidas }} {{ $campanaNoLeidas === 1 ? 'nueva' : 'nuevas' }}
                                    @endif
                                </span>
                                @if ($campanaNoLeidas > 0)
                                    <form method="POST" action="{{ route('notificaciones.leer') }}">
                                        @csrf
                                        <button type="submit"
                                            class="rounded border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">
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
                                                <div class="flex items-center gap-2">
                                                    @if (!$notif['disponible'])
                                                        <p class="text-sm font-medium italic text-slate-500">Aviso no disponible</p>
                                                    @elseif ($notif['url'])
                                                        <a href="{{ $notif['url'] }}"
                                                            class="truncate text-sm font-medium text-slate-900 hover:text-[#1F4E8C] hover:underline">
                                                            {{ $notif['titulo'] }}
                                                        </a>
                                                    @else
                                                        <p class="truncate text-sm font-medium text-slate-900">
                                                            {{ $notif['titulo'] }}</p>
                                                    @endif
                                                    @if (!$notif['leida'])
                                                        <span
                                                            class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Nuevo</span>
                                                    @endif
                                                </div>
                                                @if ($notif['disponible'])
                                                    <p class="mt-0.5 line-clamp-2 text-xs text-slate-600">{{ $notif['mensaje'] }}
                                                    </p>
                                                @endif
                                            </div>
                                            @if (!$notif['leida'])
                                                <form method="POST" action="{{ route('notificaciones.leer.una', $notif['id']) }}">
                                                    @csrf
                                                    <button type="submit" title="Marcar como leída"
                                                        class="rounded px-1.5 py-0.5 text-xs font-medium text-[#1F4E8C] hover:bg-blue-100">
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

                            <div class="border-t border-slate-100 px-4 py-2 text-[11px] text-slate-500">
                                Se muestran las últimas 10. La campana aparece siempre, aunque desactives los correos.
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Apartado de Configuración con símbolo de engranaje en la esquina --}}
                <div x-data="{ abierto: false }" class="relative">
                    <button type="button" @click="abierto = !abierto" @keydown.escape.window="abierto = false"
                        aria-label="Configuración" title="Configuración"
                        class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition {{ request()->routeIs('configuracion.*', 'admin.usuarios.*', 'password.*') ? 'border-[#1F4E8C] bg-[#1F4E8C] text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                        <span class="hidden sm:inline text-xs">Configuración</span>
                    </button>

                    <div x-cloak x-show="abierto" @click.outside="abierto = false"
                        class="absolute right-0 z-50 mt-2 w-64 rounded-xl border border-slate-200 bg-white py-2 shadow-lg">
                        <div class="border-b border-slate-100 px-4 py-2.5">
                            <p class="truncate text-sm font-semibold text-slate-900">
                                {{ $usuarioActual->nombreCompleto() }}</p>
                            <p class="text-xs text-slate-500">{{ $usuarioActual->role?->nombre?->etiqueta() }}</p>
                        </div>

                        <div class="py-1">
                            @if (Route::has('configuracion.index'))
                                <a href="{{ route('configuracion.index') }}"
                                    class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                    Configuración y colores de eventos
                                </a>
                            @endif

                            @can('viewAny', \App\Models\User::class)
                                @if (Route::has('admin.usuarios.index'))
                                    <a href="{{ route('admin.usuarios.index') }}"
                                        class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                        Administración de usuarios
                                    </a>
                                @endif
                            @endcan

                            <a href="{{ route('password.cambiar') }}"
                                class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                Cambiar contraseña
                            </a>
                        </div>

                        <div class="border-t border-slate-100 pt-1">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                    class="block w-full px-4 py-2 text-left text-sm font-medium text-red-700 hover:bg-red-50">
                                    Cerrar sesión
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

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