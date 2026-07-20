<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultas_ia', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignUuid('vehiculo_id')->constrained('vehiculos')->restrictOnDelete();
            $table->foreignUuid('cita_id')->nullable()->unique()->constrained('citas')->restrictOnDelete();
            $table->foreignUuid('orden_id')->nullable()->unique()->constrained('ordenes_trabajo')->restrictOnDelete();
            $table->foreignUuid('solicitada_por')->constrained('users')->restrictOnDelete();
            $table->jsonb('entrada');
            $table->char('entrada_hash', 64);
            $table->jsonb('respuesta_original');
            $table->string('proveedor', 30);
            $table->string('modelo', 120)->nullable();
            $table->boolean('simulada')->default(false)->index();
            $table->string('estado', 20)->default('generada')->index();
            $table->string('prioridad', 20)->index();
            $table->foreignUuid('especialidad_sugerida_id')->nullable()->constrained('especialidades')->nullOnDelete();
            $table->uuid('reutilizada_de_id')->nullable()->index();
            $table->timestamps();
            $table->index(['solicitada_por', 'entrada_hash', 'created_at']);
            $table->index(['vehiculo_id', 'created_at']);
        });

        Schema::create('revisiones_sugerencia_ia', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('consulta_id')->constrained('consultas_ia')->restrictOnDelete();
            $table->string('estado_anterior', 20);
            $table->string('estado_nuevo', 20);
            $table->jsonb('respuesta_ajustada')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignUuid('mecanico_id')->nullable()->constrained('mecanicos')->restrictOnDelete();
            $table->foreignUuid('revisada_por')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['consulta_id', 'created_at']);
        });

        Schema::create('consumos_ia', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('consulta_id')->nullable()->constrained('consultas_ia')->nullOnDelete();
            $table->foreignUuid('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('proveedor', 30);
            $table->string('modelo', 120)->nullable();
            $table->string('resultado', 30);
            $table->unsignedInteger('latencia_ms')->nullable();
            $table->unsignedInteger('tokens_entrada')->nullable();
            $table->unsignedInteger('tokens_salida')->nullable();
            $table->string('codigo_error', 80)->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        DB::statement("ALTER TABLE consultas_ia ADD CONSTRAINT consultas_ia_estado_check CHECK (estado IN ('generada','en_revision','confirmada','modificada','descartada','cerrada'))");
        DB::statement("ALTER TABLE consultas_ia ADD CONSTRAINT consultas_ia_prioridad_check CHECK (prioridad IN ('baja','media','alta','critica'))");
        DB::statement("ALTER TABLE revisiones_sugerencia_ia ADD CONSTRAINT revisiones_ia_estado_check CHECK (estado_nuevo IN ('en_revision','confirmada','modificada','descartada','cerrada'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('consumos_ia');
        Schema::dropIfExists('revisiones_sugerencia_ia');
        Schema::dropIfExists('consultas_ia');
    }
};
