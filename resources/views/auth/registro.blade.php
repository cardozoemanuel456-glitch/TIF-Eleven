<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registro · Eleven</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 p-6 text-slate-900 antialiased">
<main class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
    <h1 class="text-3xl font-semibold text-[#12263F]">Eleven</h1>
    <p class="mb-6 mt-1 text-sm text-slate-600">Registro de alumnos · Tecnicatura en Programación, subsede Formosa</p>

    <form method="POST" action="{{ route('registro.store') }}" class="space-y-4">
        @csrf

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="nombre" class="block text-sm font-medium">Nombre</label>
                <input id="nombre" name="nombre" type="text" value="{{ old('nombre') }}" required autofocus
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                @error('nombre') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="apellido" class="block text-sm font-medium">Apellido</label>
                <input id="apellido" name="apellido" type="text" value="{{ old('apellido') }}" required
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
                @error('apellido') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="dni" class="block text-sm font-medium">DNI</label>
            <input id="dni" name="dni" type="text" inputmode="numeric" value="{{ old('dni') }}" required
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            <p class="mt-1 text-xs text-slate-500">7 u 8 dígitos, sin puntos.</p>
            @error('dni') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            @error('email') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="anio_cursada" class="block text-sm font-medium">Aula</label>
            <select id="anio_cursada" name="anio_cursada" required
                    class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2">
                <option value="">Elegí tu aula</option>
                @foreach ($anios as $a)
                    <option value="{{ $a->value }}" @selected((string) old('anio_cursada') === (string) $a->value)>{{ $a->etiqueta() }}</option>
                @endforeach
            </select>
            @error('anio_cursada') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium">Contraseña</label>
            <input id="password" name="password" type="password" required
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
            <p class="mt-1 text-xs text-slate-500">Mínimo 8 caracteres, con letras y números.</p>
            @error('password') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium">Repetí la contraseña</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2">
        </div>

        <button type="submit" class="w-full rounded-md bg-[#1F4E8C] px-4 py-2.5 font-medium text-white hover:bg-[#12263F]">
            Registrarme
        </button>
    </form>

    <p class="mt-5 text-sm">
        ¿Ya tenés cuenta? <a href="{{ route('login') }}" class="text-[#1F4E8C] underline">Ingresar</a>
    </p>
    <p class="mt-2 text-xs text-slate-600">
        Tu cuenta quedará pendiente hasta que verifiques tu correo y Preceptoría la apruebe.
    </p>
</main>
</body>
</html>
