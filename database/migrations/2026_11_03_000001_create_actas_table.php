<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actas', function (Blueprint $table) {
            $table->id();
            // No se borra un usuario con actas asociadas: se desactiva (sección 10 del documento maestro).
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('nombre_original');
            $table->string('archivo_pdf');
            // Sin unique: un acta repetida solo genera una advertencia (M1-02), no se rechaza.
            $table->char('hash_sha256', 64)->index();
            // Planilla subida, temporal: se descarta al confirmar o a las 24 horas (M1-12).
            $table->string('planilla_origen')->nullable();
            $table->string('materia')->nullable();
            $table->string('tomo', 20)->nullable();
            // Un acta de varios folios guarda el rango: "404-406".
            $table->string('folio', 30)->nullable();
            $table->string('estado', 20)->default('analizada')->index();
            $table->json('informe')->nullable();
            $table->text('mensaje_error')->nullable();
            $table->timestamp('procesada_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actas');
    }
};
