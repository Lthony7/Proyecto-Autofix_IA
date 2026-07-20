<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->string('placa', 20);
            $table->string('placa_normalizada', 20)->unique();
            $table->string('marca', 80);
            $table->string('modelo', 100);
            $table->smallInteger('anio');
            $table->string('color', 50)->nullable();
            $table->unsignedInteger('kilometraje')->default(0);
            $table->string('combustible', 20);
            $table->text('observaciones')->nullable();
            $table->string('estado', 20)->default('activo')->index();
            $table->foreignUuid('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['cliente_id', 'estado']);
        });

        DB::statement("ALTER TABLE vehiculos ADD CONSTRAINT vehiculos_estado_check CHECK (estado IN ('activo', 'inactivo', 'archivado'))");
        DB::statement("ALTER TABLE vehiculos ADD CONSTRAINT vehiculos_combustible_check CHECK (combustible IN ('gasolina', 'diesel', 'gas', 'hibrido', 'electrico'))");
        DB::statement('ALTER TABLE vehiculos ADD CONSTRAINT vehiculos_anio_check CHECK (anio BETWEEN 1900 AND 2200)');
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculos');
    }
};
