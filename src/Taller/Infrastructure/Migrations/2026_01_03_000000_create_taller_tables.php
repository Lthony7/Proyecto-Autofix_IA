<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('especialidades', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 120)->unique();
            $table->text('descripcion')->nullable();
            $table->string('estado', 20)->default('activo')->index();
            $table->foreignUuid('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mecanicos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('usuario_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('tipo_documento', 20);
            $table->string('numero_documento', 30)->unique();
            $table->string('nombres', 120);
            $table->string('apellidos', 120);
            $table->string('telefono', 30);
            $table->string('email')->unique();
            $table->date('fecha_ingreso')->nullable();
            $table->string('estado', 20)->default('activo')->index();
            $table->foreignUuid('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mecanico_especialidad', function (Blueprint $table) {
            $table->foreignUuid('mecanico_id')->constrained('mecanicos')->restrictOnDelete();
            $table->foreignUuid('especialidad_id')->constrained('especialidades')->restrictOnDelete();
            $table->boolean('activo')->default(true)->index();
            $table->timestampTz('asignado_en')->useCurrent();
            $table->foreignUuid('asignado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->primary(['mecanico_id', 'especialidad_id']);
        });

        Schema::create('disponibilidades_mecanico', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mecanico_id')->constrained('mecanicos')->restrictOnDelete();
            $table->unsignedSmallInteger('dia_semana');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->boolean('activo')->default(true)->index();
            $table->date('vigente_desde')->nullable();
            $table->date('vigente_hasta')->nullable();
            $table->foreignUuid('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['mecanico_id', 'dia_semana', 'hora_inicio', 'hora_fin'], 'disponibilidad_mecanico_horario_unique');
            $table->index(['mecanico_id', 'dia_semana', 'activo']);
        });

        Schema::create('servicios_taller', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('especialidad_id')->nullable()->constrained('especialidades')->restrictOnDelete();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 150)->unique();
            $table->text('descripcion')->nullable();
            $table->unsignedSmallInteger('duracion_minutos');
            $table->decimal('precio_base', 12, 2);
            $table->string('estado', 20)->default('activo')->index();
            $table->foreignUuid('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['especialidad_id', 'estado']);
        });

        foreach (['especialidades', 'mecanicos', 'servicios_taller'] as $tabla) {
            DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$tabla}_estado_check CHECK (estado IN ('activo', 'inactivo', 'archivado'))");
        }
        DB::statement('ALTER TABLE disponibilidades_mecanico ADD CONSTRAINT disponibilidades_dia_check CHECK (dia_semana BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE disponibilidades_mecanico ADD CONSTRAINT disponibilidades_horas_check CHECK (hora_fin > hora_inicio)');
        DB::statement('ALTER TABLE disponibilidades_mecanico ADD CONSTRAINT disponibilidades_vigencia_check CHECK (vigente_hasta IS NULL OR vigente_desde IS NULL OR vigente_hasta >= vigente_desde)');
        DB::statement('ALTER TABLE servicios_taller ADD CONSTRAINT servicios_duracion_check CHECK (duracion_minutos > 0)');
        DB::statement('ALTER TABLE servicios_taller ADD CONSTRAINT servicios_precio_check CHECK (precio_base >= 0)');
        DB::statement('CREATE UNIQUE INDEX mecanicos_email_normalizado_unique ON mecanicos (LOWER(email))');
    }

    public function down(): void
    {
        Schema::dropIfExists('servicios_taller');
        Schema::dropIfExists('disponibilidades_mecanico');
        Schema::dropIfExists('mecanico_especialidad');
        Schema::dropIfExists('mecanicos');
        Schema::dropIfExists('especialidades');
    }
};
