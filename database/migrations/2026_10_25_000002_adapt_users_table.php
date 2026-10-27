<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) "name" pasa a llamarse "nombre" (el apellido va en su propia columna).
        if (Schema::hasColumn('users', 'name') && ! Schema::hasColumn('users', 'nombre')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('name', 'nombre');
            });
        }

        // 2) El correo pasa a ser opcional: un perfil anonimizado lo deja vacío.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        // 3) Columnas propias de Eleven.
        Schema::table('users', function (Blueprint $table) {
            // role_id es nullable solo a nivel de base (SQLite no permite agregar una
            // columna NOT NULL sin valor por defecto). La aplicación siempre lo asigna
            // y un usuario sin rol no tiene ningún permiso.
            $table->foreignId('role_id')->nullable()->after('id')->constrained('roles')->restrictOnDelete();
            // Año de cursada (1 o 2). Solo alumnos; lo valida la aplicación (enum AnioCursada).
            $table->unsignedTinyInteger('anio_cursada')->nullable()->index()->after('role_id');
            $table->string('apellido')->default('')->after('nombre');
            $table->string('dni', 8)->nullable()->unique()->after('apellido');
            $table->string('estado', 20)->default('pendiente')->index()->after('password');
            $table->boolean('debe_cambiar_password')->default(false)->after('estado');
            $table->boolean('notificar_email')->default(true)->after('debe_cambiar_password');
            $table->foreignId('aprobado_por')->nullable()->after('notificar_email')->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_en')->nullable()->after('aprobado_por');
            $table->timestamp('anonimizado_en')->nullable()->after('aprobado_en');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aprobado_por');
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn([
                'anio_cursada', 'apellido', 'dni', 'estado', 'debe_cambiar_password',
                'notificar_email', 'aprobado_en', 'anonimizado_en',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('nombre', 'name');
        });
    }
};
