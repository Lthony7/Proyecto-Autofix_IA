<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql' || DB::scalar('select current_schema()') === 'public') {
            throw new RuntimeException('La migración exige PostgreSQL y un DB_SCHEMA dedicado distinto de public.');
        }

        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->string('estado_anterior_pausa', 30)->nullable();
            $table->timestampTz('fecha_estimada_finalizacion')->nullable();
            $table->text('observaciones_entrega')->nullable();
            $table->date('proximo_mantenimiento_en')->nullable();
        });
        DB::statement('ALTER TABLE ordenes_trabajo DROP CONSTRAINT IF EXISTS ordenes_estado_check');
        DB::statement("ALTER TABLE ordenes_trabajo ADD CONSTRAINT ordenes_estado_check CHECK (estado IN ('pendiente','asignada','en_diagnostico','esperando_aprobacion','esperando_repuestos','en_reparacion','pausada','en_prueba','finalizada','lista_entrega','entregada','cancelada'))");

        Schema::table('diagnosticos_tecnicos', function (Blueprint $table) {
            $table->text('causa')->nullable();
            $table->text('componentes_afectados')->nullable();
            $table->string('severidad', 20)->default('media');
            $table->text('observaciones_tecnicas')->nullable();
            $table->text('indicaciones_seguridad')->nullable();
            $table->string('puede_circular', 20)->default('con_precaucion');
            $table->date('proximo_mantenimiento_en')->nullable();
            $table->string('estado', 20)->default('confirmado');
            $table->text('motivo_correccion')->nullable();
            $table->timestampTz('confirmado_en')->nullable();
        });
        DB::statement("UPDATE diagnosticos_tecnicos SET confirmado_en = created_at WHERE estado = 'confirmado' AND confirmado_en IS NULL");
        DB::statement("ALTER TABLE diagnosticos_tecnicos ADD CONSTRAINT diagnosticos_severidad_check CHECK (severidad IN ('baja','media','alta','critica'))");
        DB::statement("ALTER TABLE diagnosticos_tecnicos ADD CONSTRAINT diagnosticos_circulacion_check CHECK (puede_circular IN ('si','con_precaucion','no'))");
        DB::statement("ALTER TABLE diagnosticos_tecnicos ADD CONSTRAINT diagnosticos_estado_check CHECK (estado IN ('borrador','confirmado'))");
        Schema::table('diagnosticos_tecnicos', function (Blueprint $table) {
            $table->foreign('reemplaza_id')->references('id')->on('diagnosticos_tecnicos')->nullOnDelete();
        });
        DB::statement('CREATE UNIQUE INDEX diagnosticos_una_version_vigente_idx ON diagnosticos_tecnicos (orden_id) WHERE vigente = true');

        Schema::table('orden_avances', function (Blueprint $table) {
            $table->string('tipo', 25)->default('avance');
            $table->unsignedSmallInteger('porcentaje')->nullable();
            $table->timestampTz('fecha_estimada_finalizacion')->nullable();
            $table->text('nota_interna')->nullable();
        });
        DB::statement("ALTER TABLE orden_avances ADD CONSTRAINT orden_avances_tipo_check CHECK (tipo IN ('avance','inspeccion','hallazgo','sintoma','prueba','pausa','recomendacion'))");
        DB::statement('ALTER TABLE orden_avances ADD CONSTRAINT orden_avances_porcentaje_check CHECK (porcentaje IS NULL OR porcentaje BETWEEN 0 AND 100)');

        DB::statement('ALTER TABLE orden_repuestos_requeridos DROP CONSTRAINT IF EXISTS orden_repuestos_requeridos_estado_check');
        DB::statement("UPDATE orden_repuestos_requeridos SET estado = CASE WHEN estado = 'requerido' AND repuesto_id IS NOT NULL THEN 'aprobado' WHEN estado = 'retirado' THEN 'cancelado' ELSE 'pendiente_aprobacion' END");
        Schema::table('orden_repuestos_requeridos', function (Blueprint $table) {
            $table->string('prioridad', 15)->default('media');
            $table->boolean('obligatorio')->default(true);
            $table->string('fuente_suministro', 20)->default('inventario');
            $table->string('unidad_snapshot', 30)->nullable();
            $table->decimal('precio_unitario_aprobado', 14, 2)->default(0);
            $table->timestampTz('aprobado_en')->nullable();
            $table->foreignUuid('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
        });
        DB::statement("UPDATE orden_repuestos_requeridos AS req SET unidad_snapshot = rep.unidad FROM repuestos AS rep WHERE req.repuesto_id = rep.id AND req.unidad_snapshot IS NULL");
        DB::statement("ALTER TABLE orden_repuestos_requeridos ADD CONSTRAINT orden_repuestos_requeridos_estado_check CHECK (estado IN ('pendiente_aprobacion','aprobado','no_disponible','utilizado','no_utilizado','cancelado'))");
        DB::statement("ALTER TABLE orden_repuestos_requeridos ADD CONSTRAINT orden_repuestos_prioridad_check CHECK (prioridad IN ('baja','media','alta','critica'))");
        DB::statement("ALTER TABLE orden_repuestos_requeridos ADD CONSTRAINT orden_repuestos_fuente_check CHECK (fuente_suministro IN ('inventario','externo','cliente'))");
        DB::statement('ALTER TABLE orden_repuestos_requeridos ADD CONSTRAINT orden_repuestos_precio_check CHECK (precio_unitario_aprobado >= 0)');

        DB::statement('ALTER TABLE orden_repuestos ALTER COLUMN repuesto_id DROP NOT NULL');
        DB::statement('ALTER TABLE orden_repuestos ALTER COLUMN movimiento_salida_id DROP NOT NULL');
        Schema::table('orden_repuestos', function (Blueprint $table) {
            $table->string('fuente_suministro', 20)->default('inventario');
            $table->boolean('facturable')->default(true);
            $table->boolean('visible_cliente')->default(true);
            $table->foreignUuid('movimiento_reversion_id')->nullable()->unique()->constrained('movimientos_inventario')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE orden_repuestos ADD CONSTRAINT orden_repuestos_fuente_check CHECK (fuente_suministro IN ('inventario','externo','cliente'))");
        DB::statement("ALTER TABLE orden_repuestos ADD CONSTRAINT orden_repuestos_fuente_movimiento_check CHECK ((fuente_suministro = 'inventario' AND repuesto_id IS NOT NULL AND movimiento_salida_id IS NOT NULL) OR (fuente_suministro IN ('externo','cliente') AND movimiento_salida_id IS NULL))");
        DB::statement("ALTER TABLE orden_repuestos ADD CONSTRAINT orden_repuestos_cliente_precio_check CHECK (fuente_suministro <> 'cliente' OR (facturable = false AND precio_unitario = 0))");

        DB::statement('ALTER TABLE orden_servicios DROP CONSTRAINT IF EXISTS orden_servicios_estado_check');
        Schema::table('orden_servicios', function (Blueprint $table) {
            $table->string('tipo_trabajo', 20)->default('solicitado');
            $table->string('aprobacion_estado', 25)->default('aprobado');
            $table->timestampTz('aprobado_en')->nullable();
            $table->foreignUuid('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('iniciado_en')->nullable();
            $table->foreignUuid('iniciado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('tiempo_trabajado_minutos')->default(0);
            $table->text('resultado_prueba')->nullable();
            $table->text('observaciones_posteriores')->nullable();
            $table->text('recomendaciones_cliente')->nullable();
        });
        DB::statement("ALTER TABLE orden_servicios ADD CONSTRAINT orden_servicios_estado_check CHECK (estado IN ('pendiente','en_proceso','completado','cancelado'))");
        DB::statement("ALTER TABLE orden_servicios ADD CONSTRAINT orden_servicios_tipo_trabajo_check CHECK (tipo_trabajo IN ('solicitado','adicional'))");
        DB::statement("ALTER TABLE orden_servicios ADD CONSTRAINT orden_servicios_aprobacion_check CHECK (aprobacion_estado IN ('pendiente_aprobacion','aprobado','rechazado'))");

        Schema::create('orden_repuesto_requerido_historial', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('requerimiento_id')->constrained('orden_repuestos_requeridos')->restrictOnDelete();
            $table->string('estado_anterior', 25)->nullable();
            $table->string('estado_nuevo', 25);
            $table->decimal('cantidad', 14, 3);
            $table->text('motivo')->nullable();
            $table->foreignUuid('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['requerimiento_id', 'created_at']);
        });
        Schema::create('orden_servicio_historial', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('orden_servicio_id')->constrained('orden_servicios')->restrictOnDelete();
            $table->string('estado_anterior', 25)->nullable();
            $table->string('estado_nuevo', 25);
            $table->text('detalle')->nullable();
            $table->foreignUuid('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['orden_servicio_id', 'created_at']);
        });
        Schema::create('orden_cierre_tecnico_historial', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('orden_id')->constrained('ordenes_trabajo')->restrictOnDelete();
            $table->unsignedInteger('tiempo_trabajado_minutos');
            $table->text('bloqueos_tecnicos')->nullable();
            $table->string('control_calidad_estado', 20);
            $table->text('control_calidad_notas')->nullable();
            $table->string('prueba_ruta_estado', 20);
            $table->text('prueba_ruta_notas')->nullable();
            $table->foreignUuid('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['orden_id', 'created_at']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Migración de consolidación irreversible para preservar el historial operativo.');
    }
};
