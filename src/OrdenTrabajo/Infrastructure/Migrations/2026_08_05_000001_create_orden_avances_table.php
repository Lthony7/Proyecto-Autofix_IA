<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orden_avances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('orden_id')->constrained('ordenes_trabajo')->restrictOnDelete();
            $table->foreignUuid('servicio_id')->nullable()->constrained('orden_servicios')->restrictOnDelete();
            $table->text('descripcion');
            $table->string('visibilidad', 20)->default('cliente');
            $table->string('estado_orden', 20);
            $table->foreignUuid('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['orden_id', 'created_at']);
        });

        DB::statement("ALTER TABLE orden_avances ADD CONSTRAINT orden_avances_visibilidad_check CHECK (visibilidad IN ('cliente','interno'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_avances');
    }
};
