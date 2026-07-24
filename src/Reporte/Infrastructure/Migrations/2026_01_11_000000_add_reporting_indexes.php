<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS ordenes_estado_recibida_idx ON ordenes_trabajo (estado, recibida_en)');
        DB::statement('CREATE INDEX IF NOT EXISTS ordenes_cliente_recibida_idx ON ordenes_trabajo (cliente_id, recibida_en)');
        DB::statement('CREATE INDEX IF NOT EXISTS orden_servicios_servicio_created_idx ON orden_servicios (servicio_id, created_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS orden_repuestos_repuesto_created_activo_idx ON orden_repuestos (repuesto_id, created_at) WHERE revertido_en IS NULL');
        DB::statement("CREATE INDEX IF NOT EXISTS pagos_registrados_fecha_idx ON pagos (pagado_en) WHERE estado = 'registrado'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS pagos_registrados_fecha_idx');
        DB::statement('DROP INDEX IF EXISTS orden_repuestos_repuesto_created_activo_idx');
        DB::statement('DROP INDEX IF EXISTS orden_servicios_servicio_created_idx');
        DB::statement('DROP INDEX IF EXISTS ordenes_cliente_recibida_idx');
        DB::statement('DROP INDEX IF EXISTS ordenes_estado_recibida_idx');
    }
};
