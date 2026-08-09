<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->unsignedInteger('tiempo_trabajado_minutos')->default(0);
            $table->text('bloqueos_tecnicos')->nullable();
            $table->string('control_calidad_estado', 20)->default('pendiente');
            $table->text('control_calidad_notas')->nullable();
            $table->string('prueba_ruta_estado', 20)->default('pendiente');
            $table->text('prueba_ruta_notas')->nullable();
            $table->timestampTz('cierre_tecnico_actualizado_en')->nullable();
            $table->foreignUuid('cierre_tecnico_actualizado_por')->nullable()->constrained('users')->nullOnDelete();
        });
        DB::statement("ALTER TABLE ordenes_trabajo ADD CONSTRAINT ordenes_control_calidad_check CHECK (control_calidad_estado IN ('pendiente', 'aprobado', 'rechazado'))");
        DB::statement("ALTER TABLE ordenes_trabajo ADD CONSTRAINT ordenes_prueba_ruta_check CHECK (prueba_ruta_estado IN ('pendiente', 'aprobada', 'con_observaciones', 'no_aplica'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ordenes_trabajo DROP CONSTRAINT IF EXISTS ordenes_control_calidad_check');
        DB::statement('ALTER TABLE ordenes_trabajo DROP CONSTRAINT IF EXISTS ordenes_prueba_ruta_check');
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cierre_tecnico_actualizado_por');
            $table->dropColumn(['tiempo_trabajado_minutos', 'bloqueos_tecnicos', 'control_calidad_estado', 'control_calidad_notas', 'prueba_ruta_estado', 'prueba_ruta_notas', 'cierre_tecnico_actualizado_en']);
        });
    }
};
