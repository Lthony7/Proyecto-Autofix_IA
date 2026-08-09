<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagnosticos_tecnicos', function (Blueprint $table) {
            $table->text('resumen_cliente')->nullable();
            $table->text('notas_internas')->nullable();
            $table->uuid('reemplaza_id')->nullable()->index();
        });
        DB::statement('UPDATE diagnosticos_tecnicos SET resumen_cliente = diagnostico WHERE resumen_cliente IS NULL');
        DB::statement('ALTER TABLE diagnosticos_tecnicos ALTER COLUMN resumen_cliente SET NOT NULL');

        Schema::table('orden_repuestos', function (Blueprint $table) {
            $table->string('codigo_snapshot', 50)->nullable();
            $table->string('nombre_snapshot', 180)->nullable();
            $table->string('unidad_snapshot', 30)->nullable();
        });
        DB::statement('UPDATE orden_repuestos AS uso SET codigo_snapshot = repuesto.codigo, nombre_snapshot = repuesto.nombre, unidad_snapshot = repuesto.unidad FROM repuestos AS repuesto WHERE repuesto.id = uso.repuesto_id');
    }

    public function down(): void
    {
        Schema::table('orden_repuestos', function (Blueprint $table) {
            $table->dropColumn(['codigo_snapshot', 'nombre_snapshot', 'unidad_snapshot']);
        });
        Schema::table('diagnosticos_tecnicos', function (Blueprint $table) {
            $table->dropColumn(['resumen_cliente', 'notas_internas', 'reemplaza_id']);
        });
    }
};
