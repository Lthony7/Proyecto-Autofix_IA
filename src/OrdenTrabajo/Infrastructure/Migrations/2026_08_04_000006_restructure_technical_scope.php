<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('facturas_cita')) {
            if (DB::table('facturas_cita')->where('estado', 'pagada')->exists()) throw new RuntimeException('Existen facturas de cita pagadas que requieren conciliación antes de retirar este flujo.');
            Schema::rename('facturas_cita', 'facturas_cita_legacy');
        }

        Schema::create('cita_repuestos_solicitados', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cita_id')->constrained('citas')->restrictOnDelete();
            $table->foreignUuid('repuesto_id')->nullable()->constrained('repuestos')->restrictOnDelete();
            $table->string('descripcion', 200);
            $table->decimal('cantidad', 14, 3);
            $table->text('observaciones')->nullable();
            $table->foreignUuid('solicitado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index('cita_id');
        });

        Schema::create('orden_repuestos_requeridos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('orden_id')->constrained('ordenes_trabajo')->restrictOnDelete();
            $table->foreignUuid('repuesto_id')->nullable()->constrained('repuestos')->restrictOnDelete();
            $table->foreignUuid('solicitud_cita_id')->nullable()->constrained('cita_repuestos_solicitados')->restrictOnDelete();
            $table->string('origen', 20);
            $table->string('descripcion', 200);
            $table->decimal('cantidad', 14, 3);
            $table->text('motivo')->nullable();
            $table->string('estado', 20)->default('sugerido');
            $table->foreignUuid('agregado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('retirado_en')->nullable();
            $table->foreignUuid('retirado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo_retiro')->nullable();
            $table->timestamps();
            $table->index(['orden_id', 'estado']);
        });

        Schema::table('orden_servicios', function (Blueprint $table) {
            $table->string('origen', 20)->default('manual');
            $table->text('trabajo_realizado')->nullable();
            $table->foreignUuid('agregado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('completado_en')->nullable();
            $table->foreignUuid('completado_por')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('orden_repuestos', function (Blueprint $table) {
            $table->foreignUuid('requerimiento_id')->nullable()->constrained('orden_repuestos_requeridos')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE cita_repuestos_solicitados ADD CONSTRAINT cita_repuestos_cantidad_check CHECK (cantidad > 0)");
        DB::statement("ALTER TABLE orden_repuestos_requeridos ADD CONSTRAINT orden_repuestos_requeridos_origen_check CHECK (origen IN ('cita','ia','mecanico'))");
        DB::statement("ALTER TABLE orden_repuestos_requeridos ADD CONSTRAINT orden_repuestos_requeridos_estado_check CHECK (estado IN ('solicitado','sugerido','requerido','retirado'))");
        DB::statement('ALTER TABLE orden_repuestos_requeridos ADD CONSTRAINT orden_repuestos_requeridos_cantidad_check CHECK (cantidad > 0)');
        DB::statement("ALTER TABLE orden_servicios ADD CONSTRAINT orden_servicios_origen_check CHECK (origen IN ('cita','ia','mecanico','manual'))");
        DB::statement('UPDATE consultas_ia AS consulta SET orden_id = orden.id FROM ordenes_trabajo AS orden WHERE consulta.cita_id = orden.cita_id AND consulta.orden_id IS NULL');
    }

    public function down(): void {}
};
