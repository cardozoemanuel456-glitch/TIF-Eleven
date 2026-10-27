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
@endphp
<div class="flex min-h-screen flex-col md:flex-row">
    <nav class="bg-[#12263F] p-4 md:w-56 md:flex-none">
        <div class="px-3 pb-6 text-2xl font-semibold text-white">Eleven</div>

        <div class="flex flex-wrap gap-1 md:flex-col">
            <a href="{{ route('inicio') }}" class="{{ request()->routeIs('inicio') ? $activo : $enlace }}">Inicio</a>
            {{-- Los demás accesos se agregan en las próximas partes y módulos. --}}
        </div>

        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <button type="submit" class="{{ $enlace }} w-full text-left">Cerrar sesión</button>
        </form>
    </nav>

    <main class="min-w-0 flex-1 p-6 md:p-10">
        @if (session('estado'))
            <div class="mb-4 rounded-md border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-900">
                {{ session('estado') }}
            </div>
        @endif

        @yield('contenido')
    </main>
</div>
</body>
</html>
