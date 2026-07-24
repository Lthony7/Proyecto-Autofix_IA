<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->decimal('total_orden_snapshot', 14, 2)->nullable();
            $table->decimal('pagado_acumulado_snapshot', 14, 2)->nullable();
            $table->decimal('saldo_resultante_snapshot', 14, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn(['total_orden_snapshot', 'pagado_acumulado_snapshot', 'saldo_resultante_snapshot']);
        });
    }
};
