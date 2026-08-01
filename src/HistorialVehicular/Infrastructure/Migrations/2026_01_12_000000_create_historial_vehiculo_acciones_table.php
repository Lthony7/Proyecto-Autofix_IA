<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_vehiculo_acciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vehiculo_id')->constrained('vehiculos')->restrictOnDelete();
            $table->foreignUuid('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rol', 120)->nullable();
            $table->string('accion', 80);
            $table->text('descripcion');
            $table->jsonb('cambios')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['vehiculo_id', 'created_at']);
            $table->index(['usuario_id', 'created_at']);
            $table->index(['accion', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_vehiculo_acciones');
    }
};
