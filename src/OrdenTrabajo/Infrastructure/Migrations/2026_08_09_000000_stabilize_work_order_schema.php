<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertDedicatedSchema();

        DB::statement(
            "ALTER TABLE orden_repuestos_requeridos ALTER COLUMN estado SET DEFAULT 'pendiente_aprobacion'",
        );

        DB::statement(<<<'SQL'
            UPDATE orden_servicios
            SET aprobado_en = COALESCE(created_at, CURRENT_TIMESTAMP)
            WHERE aprobacion_estado = 'aprobado' AND aprobado_en IS NULL
        SQL);
        DB::statement(<<<'SQL'
            UPDATE orden_repuestos_requeridos
            SET aprobado_en = COALESCE(created_at, CURRENT_TIMESTAMP)
            WHERE estado IN ('aprobado', 'utilizado', 'no_utilizado') AND aprobado_en IS NULL
        SQL);

        $this->backfillServiceHistory();
        $this->backfillRequiredPartHistory();
        $this->backfillTechnicalClosureHistory();

        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS movimientos_inventario_orden_fecha_idx
            ON movimientos_inventario (orden_id, created_at DESC)
            WHERE orden_id IS NOT NULL
        SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS orden_repuestos_requerimiento_idx
            ON orden_repuestos (requerimiento_id)
            WHERE requerimiento_id IS NOT NULL
        SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS orden_avances_servicio_fecha_idx
            ON orden_avances (servicio_id, created_at DESC)
            WHERE servicio_id IS NOT NULL
        SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS orden_servicios_aprobacion_pendiente_idx
            ON orden_servicios (orden_id, created_at)
            WHERE aprobacion_estado = 'pendiente_aprobacion'
        SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS orden_repuestos_aprobacion_pendiente_idx
            ON orden_repuestos_requeridos (orden_id, prioridad, created_at)
            WHERE estado = 'pendiente_aprobacion'
        SQL);
    }

    public function down(): void
    {
        throw new RuntimeException(
            'La estabilización contiene correcciones y líneas base históricas que no deben revertirse.',
        );
    }

    private function assertDedicatedSchema(): void
    {
        $expected = (string) config('database.connections.pgsql.search_path');
        $current = DB::scalar('SELECT current_schema()');
        $isolated = DB::scalar('SELECT current_schemas(false) = ARRAY[?]::name[]', [$expected]);

        if ($expected === 'public' || $current !== $expected || ! $isolated) {
            throw new RuntimeException('La migración exige el DB_SCHEMA dedicado configurado y aislado.');
        }
    }

    private function backfillServiceHistory(): void
    {
        $services = DB::table('orden_servicios as os')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('orden_servicio_historial as osh')
                ->whereColumn('osh.orden_servicio_id', 'os.id'))
            ->get(['os.id', 'os.estado', 'os.aprobacion_estado', 'os.created_at']);

        foreach ($services as $service) {
            DB::table('orden_servicio_historial')->insert([
                'id' => (string) Str::uuid(),
                'orden_servicio_id' => $service->id,
                'estado_anterior' => null,
                'estado_nuevo' => $service->estado,
                'detalle' => "Línea base migrada; aprobación: {$service->aprobacion_estado}.",
                'usuario_id' => null,
                'created_at' => $service->created_at ?? now(),
            ]);
        }
    }

    private function backfillRequiredPartHistory(): void
    {
        $requirements = DB::table('orden_repuestos_requeridos as req')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('orden_repuesto_requerido_historial as rrh')
                ->whereColumn('rrh.requerimiento_id', 'req.id'))
            ->get(['req.id', 'req.estado', 'req.cantidad', 'req.motivo', 'req.created_at']);

        foreach ($requirements as $requirement) {
            DB::table('orden_repuesto_requerido_historial')->insert([
                'id' => (string) Str::uuid(),
                'requerimiento_id' => $requirement->id,
                'estado_anterior' => null,
                'estado_nuevo' => $requirement->estado,
                'cantidad' => $requirement->cantidad,
                'motivo' => $requirement->motivo ?: 'Línea base migrada.',
                'usuario_id' => null,
                'created_at' => $requirement->created_at ?? now(),
            ]);
        }
    }

    private function backfillTechnicalClosureHistory(): void
    {
        $orders = DB::table('ordenes_trabajo as ot')
            ->whereNotNull('ot.cierre_tecnico_actualizado_en')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('orden_cierre_tecnico_historial as octh')
                ->whereColumn('octh.orden_id', 'ot.id'))
            ->get([
                'ot.id',
                'ot.tiempo_trabajado_minutos',
                'ot.bloqueos_tecnicos',
                'ot.control_calidad_estado',
                'ot.control_calidad_notas',
                'ot.prueba_ruta_estado',
                'ot.prueba_ruta_notas',
                'ot.cierre_tecnico_actualizado_por',
                'ot.cierre_tecnico_actualizado_en',
            ]);

        foreach ($orders as $order) {
            DB::table('orden_cierre_tecnico_historial')->insert([
                'id' => (string) Str::uuid(),
                'orden_id' => $order->id,
                'tiempo_trabajado_minutos' => $order->tiempo_trabajado_minutos,
                'bloqueos_tecnicos' => $order->bloqueos_tecnicos,
                'control_calidad_estado' => $order->control_calidad_estado,
                'control_calidad_notas' => $order->control_calidad_notas,
                'prueba_ruta_estado' => $order->prueba_ruta_estado,
                'prueba_ruta_notas' => $order->prueba_ruta_notas,
                'registrado_por' => $order->cierre_tecnico_actualizado_por,
                'created_at' => $order->cierre_tecnico_actualizado_en,
            ]);
        }
    }
};
