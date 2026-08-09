<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') return;

        // Tipos de documento: migrar datos históricos colombianos a ecuatorianos.
        // Primero se retira la restricción, luego se normalizan los datos y se agrega la nueva.
        DB::statement('ALTER TABLE clientes DROP CONSTRAINT IF EXISTS clientes_tipo_documento_check');
        DB::table('clientes')->where('tipo_documento', 'DNI')->update(['tipo_documento' => 'CEDULA']);
        DB::table('clientes')->whereIn('tipo_documento', ['CC', 'CE'])->update(['tipo_documento' => 'CEDULA']);
        DB::table('clientes')->where('tipo_documento', 'NIT')->update(['tipo_documento' => 'RUC']);
        DB::statement("ALTER TABLE clientes ADD CONSTRAINT clientes_tipo_documento_check CHECK (tipo_documento IN ('CEDULA', 'RUC', 'PASAPORTE'))");

        // Moneda: convertir COP a USD en las tablas que ya tienen restricciones.
        // Cada bloque solo actúa sobre tablas existentes (engañoso en entornos donde
        // la migración 2026_08_04_000002 no ha corrido aún en esa base).
        $monedaPorTabla = [
            'facturas_orden' => 'facturas_orden_moneda_check',
            'facturas_cita' => 'facturas_cita_moneda_check',
            'pagos' => 'pagos_moneda_check',
            'pago_movimientos' => 'pago_movimientos_moneda_check',
        ];
        foreach ($monedaPorTabla as $tabla => $constraint) {
            if (! Schema::hasTable($tabla)) continue;
            DB::table($tabla)->where('moneda', 'COP')->update(['moneda' => 'USD']);
            DB::statement("ALTER TABLE {$tabla} DROP CONSTRAINT IF EXISTS {$constraint}");
            DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$constraint} CHECK (moneda = 'USD')");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') return;

        DB::statement('ALTER TABLE clientes DROP CONSTRAINT IF EXISTS clientes_tipo_documento_check');
        DB::statement('ALTER TABLE facturas_orden DROP CONSTRAINT IF EXISTS facturas_orden_moneda_check');
        DB::statement('ALTER TABLE facturas_cita DROP CONSTRAINT IF EXISTS facturas_cita_moneda_check');
        DB::statement('ALTER TABLE pagos DROP CONSTRAINT IF EXISTS pagos_moneda_check');
        DB::statement('ALTER TABLE pago_movimientos DROP CONSTRAINT IF EXISTS pago_movimientos_moneda_check');
    }
};