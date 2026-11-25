<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') · Eleven</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 p-6 text-slate-900 antialiased">
<main class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
    <p class="text-sm font-medium text-slate-600">Error @yield('codigo')</p>
    <h1 class="mt-1 text-2xl font-semibold text-[#12263F]">@yield('titulo')</h1>
    <p class="mt-3 text-sm text-slate-700">@yield('mensaje')</p>
    <a href="{{ url('/') }}" class="mt-6 inline-block rounded-md bg-[#1F4E8C] px-4 py-2.5 text-sm font-medium text-white hover:bg-[#12263F]">
        Volver al inicio
    </a>
</main>
</body>
</html>