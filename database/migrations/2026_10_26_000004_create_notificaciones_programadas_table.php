<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones_programadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('anio_cursada')->nullable()->index();
            $table->foreignId('evento_id')->nullable()->constrained('eventos')->nullOnDelete();
            $table->foreignId('aviso_id')->nullable()->constrained('avisos')->nullOnDelete();
            $table->string('titulo', 150);
            $table->text('contenido');
            $table->timestamp('programada_para')->index();
            $table->string('estado', 20)->default('pendiente')->index();
            $table->timestamp('enviada_en')->nullable();
            $table->string('mensaje_error', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones_programadas');
    }
};

