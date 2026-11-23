<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actas', function (Blueprint $table) {
            // Nombre con que la preceptora subió su planilla: se usa para nombrar la planilla generada.
            $table->string('planilla_nombre')->nullable()->after('planilla_origen');
        });
    }

    public function down(): void
    {
        Schema::table('actas', function (Blueprint $table) {
            $table->dropColumn('planilla_nombre');
        });
    }
};
