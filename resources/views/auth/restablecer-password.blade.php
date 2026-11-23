<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nueva contraseña · Eleven</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 p-6 text-slate-900 antialiased">
<main class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
    <h1 class="text-2xl font-semibold text-[#12263F]">Elegí una contraseña nueva</h1>
    <p class="mb-6 mt-1 text-sm text-slate-600">Mínimo 8 caracteres, con letras y números.</p>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="block text-sm font-medium">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            @error('email') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium">Nueva contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" autofocus
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            @error('password') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium">Repetir contraseña</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
        </div>

        <button type="submit" class="w-full rounded-md bg-[#1F4E8C] px-4 py-2.5 font-medium text-white hover:bg-[#12263F]">Guardar contraseña</button>
    </form>
</main>
</body>
</html>