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
        DB::statement('ALTER TABLE diagnosticos_tecnicos ALTER COLUMN resumen_cliente DROP NOT NULL');
    }

    public function down(): void
    {
        if (DB::table('diagnosticos_tecnicos')->whereNull('resumen_cliente')->exists()) throw new RuntimeException('Existen borradores privados sin resumen para cliente.');
        DB::statement('ALTER TABLE diagnosticos_tecnicos ALTER COLUMN resumen_cliente SET NOT NULL');
    }
};
