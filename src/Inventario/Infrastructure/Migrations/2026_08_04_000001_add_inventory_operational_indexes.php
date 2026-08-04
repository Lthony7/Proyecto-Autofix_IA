<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS movimientos_repuesto_tipo_fecha_idx ON movimientos_inventario (repuesto_id, tipo, created_at DESC)');
        DB::statement("CREATE INDEX IF NOT EXISTS repuestos_stock_bajo_activo_idx ON repuestos (id) WHERE estado = 'activo' AND stock_actual <= stock_minimo");
        DB::statement('CREATE INDEX IF NOT EXISTS repuestos_proveedor_estado_idx ON repuestos (proveedor_id, estado)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS movimientos_repuesto_tipo_fecha_idx');
        DB::statement('DROP INDEX IF EXISTS repuestos_stock_bajo_activo_idx');
        DB::statement('DROP INDEX IF EXISTS repuestos_proveedor_estado_idx');
    }
};
