<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Eleven')</title>
    {{-- Tailwind por CDN, igual que el prototipo. En producción conviene compilarlo con Vite. --}}
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-900 antialiased">
@php
    $enlace = 'block rounded-md px-3 py-2 text-sm text-slate-200 hover:bg-white/10';
    $activo = 'block rounded-md px-3 py-2 text-sm bg-[#1F4E8C] text-white';
    $usuarioActual = auth()->user();
@endphp
<div class="flex min-h-screen flex-col md:flex-row">
    <nav class="bg-[#12263F] p-4 md:w-60 md:flex-none">
        <div class="px-3 pb-6 text-2xl font-semibold text-white">Eleven</div>

        <div class="flex flex-wrap gap-1 md:flex-col">
            <a href="{{ route('inicio') }}" class="{{ request()->routeIs('inicio') ? $activo : $enlace }}">Inicio</a>

            {{-- Cada acceso aparece solo si la Policy lo permite y la ruta ya existe. --}}
            @can('viewAny', \App\Models\User::class)
                @if (Route::has('admin.usuarios.index'))
                    <a href="{{ route('admin.usuarios.index') }}"
                       class="{{ request()->routeIs('admin.usuarios.*') ? $activo : $enlace }}">Usuarios</a>
                @endif
            @endcan
            {{-- Los demás accesos (alumnos, actas, avisos, TIF) se agregan con cada módulo. --}}
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
</body>
</html>