<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas_cita', function (Blueprint $table) {
            $table->jsonb('detalle')->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('facturas_cita', fn (Blueprint $table) => $table->dropColumn('detalle'));
    }
};
