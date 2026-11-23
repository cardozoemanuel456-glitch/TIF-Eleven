<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · Eleven</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 p-6 text-slate-900 antialiased">
<main class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
    <h1 class="text-3xl font-semibold text-[#12263F]">Eleven</h1>
    <p class="mb-6 mt-1 text-sm text-slate-600">Portal de la subsede Formosa · Tecnicatura en Programación</p>

    @if (session('estado'))
        <div class="mb-4 rounded-md border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-900">{{ session('estado') }}</div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            @error('email')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium">Contraseña</label>
            <input id="password" name="password" type="password" required
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            @error('password')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="recordarme" value="1"> Mantener la sesión iniciada
        </label>

        <button type="submit" class="w-full rounded-md bg-[#1F4E8C] px-4 py-2.5 font-medium text-white hover:bg-[#12263F]">
            Ingresar
        </button>
    </form>

    <p class="mt-5 text-sm">
        <a href="{{ route('password.request') }}" class="text-[#1F4E8C] underline">¿Olvidaste tu contraseña?</a>
    </p>
    <p class="mt-2 text-xs text-slate-600">
        ¿Problemas para entrar? Consultá en preceptoría.
    </p>
</main>
</body>
</html>