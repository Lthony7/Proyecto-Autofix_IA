<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cita_repuestos_solicitados', function (Blueprint $table) {
            $table->text('descripcion')->change();
        });

        Schema::table('orden_repuestos_requeridos', function (Blueprint $table) {
            $table->text('descripcion')->change();
        });
    }

    public function down(): void
    {
        Schema::table('cita_repuestos_solicitados', function (Blueprint $table) {
            $table->string('descripcion', 200)->change();
        });

        Schema::table('orden_repuestos_requeridos', function (Blueprint $table) {
            $table->string('descripcion', 200)->change();
        });
    }
};
