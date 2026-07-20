<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('numero', 30)->unique();
            $table->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignUuid('vehiculo_id')->constrained('vehiculos')->restrictOnDelete();
            $table->foreignUuid('especialidad_id')->nullable()->constrained('especialidades')->restrictOnDelete();
            $table->foreignUuid('servicio_id')->nullable()->constrained('servicios_taller')->restrictOnDelete();
            $table->foreignUuid('mecanico_id')->nullable()->constrained('mecanicos')->restrictOnDelete();
            $table->text('motivo');
            $table->unsignedInteger('kilometraje')->nullable();
            $table->timestampTz('inicio');
            $table->timestampTz('fin');
            $table->string('estado', 20)->default('pendiente')->index();
            $table->text('motivo_cancelacion')->nullable();
            $table->timestampTz('cancelada_en')->nullable();
            $table->foreignUuid('cancelada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['mecanico_id', 'inicio', 'fin']);
            $table->index(['cliente_id', 'estado']);
            $table->index(['vehiculo_id', 'inicio']);
        });

        Schema::create('cita_estado_historial', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cita_id')->constrained('citas')->restrictOnDelete();
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20);
            $table->text('observaciones')->nullable();
            $table->jsonb('datos_anteriores')->nullable();
            $table->foreignUuid('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['cita_id', 'created_at']);
        });

        DB::statement("ALTER TABLE citas ADD CONSTRAINT citas_estado_check CHECK (estado IN ('pendiente', 'confirmada', 'reprogramada', 'atendida', 'cancelada'))");
        DB::statement('ALTER TABLE citas ADD CONSTRAINT citas_horario_check CHECK (fin > inicio)');
        DB::statement('ALTER TABLE citas ADD CONSTRAINT citas_cancelacion_check CHECK (estado <> \'cancelada\' OR (motivo_cancelacion IS NOT NULL AND cancelada_en IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('cita_estado_historial');
        Schema::dropIfExists('citas');
    }
};
