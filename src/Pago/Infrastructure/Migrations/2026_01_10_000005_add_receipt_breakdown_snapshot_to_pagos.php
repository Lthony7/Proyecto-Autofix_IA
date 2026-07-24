<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->decimal('servicios_snapshot', 14, 2)->nullable();
            $table->decimal('repuestos_snapshot', 14, 2)->nullable();
            $table->decimal('descuento_snapshot', 14, 2)->nullable();
            $table->decimal('impuesto_snapshot', 14, 2)->nullable();
            $table->jsonb('detalle_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn(['servicios_snapshot', 'repuestos_snapshot', 'descuento_snapshot', 'impuesto_snapshot', 'detalle_snapshot']);
        });
    }
};
