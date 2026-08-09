<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE facturas_orden ADD CONSTRAINT facturas_impuesto_calculo_check CHECK (impuesto = ROUND(base_impuesto * tasa_impuesto / 100, 2))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE facturas_orden DROP CONSTRAINT IF EXISTS facturas_impuesto_calculo_check');
    }
};
