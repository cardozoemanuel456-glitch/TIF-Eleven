<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planillas_excel', function (Blueprint $table) {
            $table->id();
            // Al borrar un acta se eliminan sus planillas generadas.
            $table->foreignId('acta_id')->constrained('actas')->cascadeOnDelete();
            $table->string('nombre_original');
            $table->string('archivo_xlsx');
            $table->timestamp('generada_en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planillas_excel');
    }
};
