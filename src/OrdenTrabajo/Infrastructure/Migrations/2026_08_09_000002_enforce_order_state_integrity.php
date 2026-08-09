<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql' || DB::scalar('select current_schema()') === 'public') {
            throw new RuntimeException('La migración exige PostgreSQL y un DB_SCHEMA dedicado distinto de public.');
        }

        DB::statement("ALTER TABLE ordenes_trabajo ADD CONSTRAINT ordenes_finalizacion_integridad_check CHECK (estado NOT IN ('finalizada','lista_entrega','entregada') OR finalizada_en IS NOT NULL)");
        DB::statement("ALTER TABLE ordenes_trabajo ADD CONSTRAINT ordenes_entrega_integridad_check CHECK (estado <> 'entregada' OR entregada_en IS NOT NULL)");
        DB::statement("ALTER TABLE ordenes_trabajo ADD CONSTRAINT ordenes_cancelacion_integridad_check CHECK (estado <> 'cancelada' OR (cancelada_en IS NOT NULL AND NULLIF(BTRIM(motivo_cancelacion), '') IS NOT NULL))");
        DB::statement("ALTER TABLE ordenes_trabajo ADD CONSTRAINT ordenes_pausa_integridad_check CHECK (estado <> 'pausada' OR estado_anterior_pausa IN ('asignada','en_diagnostico','esperando_aprobacion','esperando_repuestos','en_reparacion','en_prueba'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ordenes_trabajo DROP CONSTRAINT IF EXISTS ordenes_pausa_integridad_check');
        DB::statement('ALTER TABLE ordenes_trabajo DROP CONSTRAINT IF EXISTS ordenes_cancelacion_integridad_check');
        DB::statement('ALTER TABLE ordenes_trabajo DROP CONSTRAINT IF EXISTS ordenes_entrega_integridad_check');
        DB::statement('ALTER TABLE ordenes_trabajo DROP CONSTRAINT IF EXISTS ordenes_finalizacion_integridad_check');
    }
};
