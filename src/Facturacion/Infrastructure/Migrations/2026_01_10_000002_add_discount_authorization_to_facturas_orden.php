<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas_orden', function (Blueprint $table) {
            $table->string('motivo_descuento', 500)->nullable();
            $table->foreignUuid('descuento_autorizado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('descuento_autorizado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('facturas_orden', function (Blueprint $table) {
            $table->dropConstrainedForeignId('descuento_autorizado_por');
            $table->dropColumn(['motivo_descuento', 'descuento_autorizado_en']);
        });
    }
};
