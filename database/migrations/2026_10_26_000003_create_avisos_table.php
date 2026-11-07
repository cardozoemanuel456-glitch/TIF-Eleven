<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('autor_id')->constrained('users')->restrictOnDelete();
            // Nulo = aviso general para todos; 1 o 2 = año de cursada específico.
            $table->unsignedTinyInteger('anio_cursada')->nullable()->index();
            $table->foreignId('evento_id')->nullable()->constrained('eventos')->nullOnDelete();
            $table->string('titulo', 150);
            $table->text('contenido');
            $table->timestamp('publicado_en')->nullable()->index();
            $table->timestamp('programado_para')->nullable()->index();
            $table->timestamp('editado_en')->nullable();
            $table->timestamps();
        });

        Schema::create('aviso_lecturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aviso_id')->constrained('avisos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('leido_en');

            $table->unique(['aviso_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aviso_lecturas');
        Schema::dropIfExists('avisos');
    }
};

