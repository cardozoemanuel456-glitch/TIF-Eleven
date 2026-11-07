<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            // Nulo = evento general para toda la sede; 1 o 2 = año de cursada específico.
            $table->unsignedTinyInteger('anio_cursada')->nullable()->index();
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->string('tipo', 30)->index();
            $table->date('fecha_inicio')->index();
            $table->date('fecha_fin')->index();
            $table->time('hora')->nullable();
            $table->timestamp('recordado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};

