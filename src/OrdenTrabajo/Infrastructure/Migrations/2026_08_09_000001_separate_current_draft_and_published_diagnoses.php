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

        DB::statement('DROP INDEX IF EXISTS diagnosticos_una_version_vigente_idx');
        DB::statement('UPDATE diagnosticos_tecnicos SET vigente = false');
        DB::statement(<<<'SQL'
            UPDATE diagnosticos_tecnicos AS diagnostico
            SET vigente = true
            WHERE diagnostico.estado = 'confirmado'
              AND NOT EXISTS (
                  SELECT 1 FROM diagnosticos_tecnicos AS posterior
                  WHERE posterior.orden_id = diagnostico.orden_id
                    AND posterior.estado = 'confirmado'
                    AND posterior.version > diagnostico.version
              )
        SQL);
        DB::statement(<<<'SQL'
            UPDATE diagnosticos_tecnicos AS diagnostico
            SET vigente = true
            WHERE diagnostico.estado = 'borrador'
              AND NOT EXISTS (
                  SELECT 1 FROM diagnosticos_tecnicos AS posterior
                  WHERE posterior.orden_id = diagnostico.orden_id
                    AND posterior.estado = 'borrador'
                    AND posterior.version > diagnostico.version
              )
              AND NOT EXISTS (
                  SELECT 1 FROM diagnosticos_tecnicos AS publicado
                  WHERE publicado.orden_id = diagnostico.orden_id
                    AND publicado.estado = 'confirmado'
                    AND publicado.version > diagnostico.version
              )
        SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX diagnosticos_una_version_vigente_por_estado_idx
            ON diagnosticos_tecnicos (orden_id, estado)
            WHERE vigente = true
        SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('La separación entre borrador actual y diagnóstico publicado actual es irreversible.');
    }
};
