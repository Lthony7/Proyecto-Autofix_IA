<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturas_cita', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('numero', 30)->unique();
            $table->foreignUuid('cita_id')->unique()->constrained('citas')->restrictOnDelete();
            $table->foreignUuid('servicio_id')->constrained('servicios_taller')->restrictOnDelete();
            $table->string('cliente_tipo_documento', 30);
            $table->string('cliente_documento', 50);
            $table->string('cliente_nombre', 180);
            $table->string('cliente_direccion');
            $table->string('cliente_email');
            $table->string('vehiculo_placa', 20);
            $table->string('servicio_nombre', 200);
            $table->decimal('subtotal', 14, 2);
            $table->decimal('impuesto', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->char('moneda', 3)->default('USD');
            $table->string('estado', 20)->default('pendiente');
            $table->timestampTz('creada_en');
            $table->foreignUuid('creada_por')->constrained('users')->restrictOnDelete();
            $table->timestampTz('pagada_en')->nullable();
            $table->foreignUuid('pagada_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('metodo_pago', 20)->nullable();
            $table->string('referencia_pago', 120)->nullable();
            $table->timestampTz('anulada_en')->nullable();
            $table->foreignUuid('anulada_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps();
            $table->index(['estado', 'creada_en']);
        });

        DB::statement("ALTER TABLE facturas_cita ADD CONSTRAINT facturas_cita_estado_check CHECK (estado IN ('pendiente','pagada','anulada'))");
        DB::statement("ALTER TABLE facturas_cita ADD CONSTRAINT facturas_cita_moneda_check CHECK (moneda = 'USD')");
        DB::statement('ALTER TABLE facturas_cita ADD CONSTRAINT facturas_cita_valores_check CHECK (subtotal > 0 AND impuesto >= 0 AND total = subtotal + impuesto)');
        DB::statement("ALTER TABLE facturas_cita ADD CONSTRAINT facturas_cita_pago_check CHECK ((estado = 'pendiente' AND pagada_en IS NULL AND pagada_por IS NULL AND metodo_pago IS NULL AND anulada_en IS NULL AND anulada_por IS NULL AND motivo_anulacion IS NULL) OR (estado = 'pagada' AND pagada_en IS NOT NULL AND pagada_por IS NOT NULL AND metodo_pago IS NOT NULL AND anulada_en IS NULL AND anulada_por IS NULL AND motivo_anulacion IS NULL) OR (estado = 'anulada' AND pagada_en IS NULL AND pagada_por IS NULL AND metodo_pago IS NULL AND anulada_en IS NOT NULL AND anulada_por IS NOT NULL AND motivo_anulacion IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas_cita');
    }
};
