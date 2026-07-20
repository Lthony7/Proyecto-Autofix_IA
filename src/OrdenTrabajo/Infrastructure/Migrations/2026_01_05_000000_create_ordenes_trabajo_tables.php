<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_trabajo', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('numero', 30)->unique();
            $table->foreignUuid('cita_id')->nullable()->unique()->constrained('citas')->restrictOnDelete();
            $table->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignUuid('vehiculo_id')->constrained('vehiculos')->restrictOnDelete();
            $table->text('falla_reportada');
            $table->unsignedInteger('kilometraje')->nullable();
            $table->string('estado', 30)->default('pendiente')->index();
            $table->timestampTz('recibida_en')->useCurrent();
            $table->timestampTz('finalizada_en')->nullable();
            $table->timestampTz('entregada_en')->nullable();
            $table->text('motivo_cancelacion')->nullable();
            $table->timestampTz('cancelada_en')->nullable();
            $table->foreignUuid('cancelada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['vehiculo_id', 'recibida_en']);
            $table->index(['cliente_id', 'estado']);
        });

        Schema::create('orden_servicios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('orden_id')->constrained('ordenes_trabajo')->restrictOnDelete();
            $table->foreignUuid('servicio_id')->constrained('servicios_taller')->restrictOnDelete();
            $table->string('nombre_servicio', 150);
            $table->decimal('precio_acordado', 12, 2);
            $table->string('estado', 20)->default('pendiente');
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->unique(['orden_id', 'servicio_id']);
        });

        Schema::create('orden_mecanicos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('orden_id')->constrained('ordenes_trabajo')->restrictOnDelete();
            $table->foreignUuid('mecanico_id')->constrained('mecanicos')->restrictOnDelete();
            $table->boolean('activo')->default(true)->index();
            $table->timestampTz('asignado_en')->useCurrent();
            $table->timestampTz('retirado_en')->nullable();
            $table->foreignUuid('asignado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observaciones')->nullable();
            $table->index(['orden_id', 'activo']);
            $table->index(['mecanico_id', 'activo']);
        });

        Schema::create('orden_estado_historial', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('orden_id')->constrained('ordenes_trabajo')->restrictOnDelete();
            $table->string('estado_anterior', 30)->nullable();
            $table->string('estado_nuevo', 30);
            $table->text('observaciones')->nullable();
            $table->foreignUuid('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['orden_id', 'created_at']);
        });

        Schema::create('diagnosticos_tecnicos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('orden_id')->constrained('ordenes_trabajo')->restrictOnDelete();
            $table->foreignUuid('mecanico_id')->nullable()->constrained('mecanicos')->restrictOnDelete();
            $table->unsignedSmallInteger('version');
            $table->text('diagnostico');
            $table->text('pruebas_realizadas')->nullable();
            $table->text('recomendaciones')->nullable();
            $table->boolean('vigente')->default(true)->index();
            $table->foreignUuid('registrado_por')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['orden_id', 'version']);
        });

        DB::statement("ALTER TABLE ordenes_trabajo ADD CONSTRAINT ordenes_estado_check CHECK (estado IN ('pendiente', 'en_diagnostico', 'en_reparacion', 'finalizada', 'entregada', 'cancelada'))");
        DB::statement("ALTER TABLE orden_servicios ADD CONSTRAINT orden_servicios_estado_check CHECK (estado IN ('pendiente', 'en_proceso', 'completado', 'cancelado'))");
        DB::statement('ALTER TABLE orden_servicios ADD CONSTRAINT orden_servicios_precio_check CHECK (precio_acordado >= 0)');
        DB::statement('ALTER TABLE diagnosticos_tecnicos ADD CONSTRAINT diagnosticos_version_check CHECK (version > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosticos_tecnicos');
        Schema::dropIfExists('orden_estado_historial');
        Schema::dropIfExists('orden_mecanicos');
        Schema::dropIfExists('orden_servicios');
        Schema::dropIfExists('ordenes_trabajo');
    }
};
