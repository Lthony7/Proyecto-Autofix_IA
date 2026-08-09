<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $expected = (string) config('database.connections.pgsql.search_path');
        if (DB::scalar('SELECT current_schema()') !== $expected || ! DB::scalar('SELECT current_schemas(false) = ARRAY[?]::name[]', [$expected])) {
            throw new RuntimeException('La migración exige el DB_SCHEMA dedicado configurado y aislado.');
        }

        DB::statement('ALTER TABLE citas DROP CONSTRAINT IF EXISTS citas_estado_check');
        DB::statement("ALTER TABLE citas ADD CONSTRAINT citas_estado_check CHECK (estado IN ('pendiente', 'confirmada', 'reprogramada', 'atendida', 'cancelada', 'vencida'))");
        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS citas_pendientes_vencimiento_idx
            ON citas (fin)
            WHERE estado IN ('pendiente', 'confirmada', 'reprogramada')
        SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('El estado vencida conserva historial operativo y no debe revertirse.');
    }
};
