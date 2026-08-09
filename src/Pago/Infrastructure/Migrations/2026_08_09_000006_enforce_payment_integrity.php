<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE pagos ADD CONSTRAINT pagos_snapshots_valores_check CHECK (idempotencia_clave IS NULL OR (servicios_snapshot >= 0 AND repuestos_snapshot >= 0 AND descuento_snapshot >= 0 AND impuesto_snapshot >= 0 AND total_orden_snapshot > 0 AND pagado_acumulado_snapshot >= monto AND saldo_resultante_snapshot >= 0))');
        DB::statement("CREATE UNIQUE INDEX pagos_referencia_electronica_unique ON pagos (metodo, referencia) WHERE metodo IN ('tarjeta','transferencia') AND referencia IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS pagos_referencia_electronica_unique');
        DB::statement('ALTER TABLE pagos DROP CONSTRAINT IF EXISTS pagos_snapshots_valores_check');
    }
};
