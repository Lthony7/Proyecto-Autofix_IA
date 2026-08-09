<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') return;

        // Tipos de documento: migrar datos históricos colombianos a ecuatorianos.
        DB::table('clientes')->where('tipo_documento', 'DNI')->update(['tipo_documento' => 'CEDULA']);
        DB::table('clientes')->whereIn('tipo_documento', ['CC', 'CE'])->update(['tipo_documento' => 'CEDULA']);
        DB::table('clientes')->where('tipo_documento', 'NIT')->update(['tipo_documento' => 'RUC']);

        DB::statement('ALTER TABLE clientes DROP CONSTRAINT IF EXISTS clientes_tipo_documento_check');
        DB::statement("ALTER TABLE clientes ADD CONSTRAINT clientes_tipo_documento_check CHECK (tipo_documento IN ('CEDULA', 'RUC', 'PASAPORTE'))");

        // Moneda: convertir COP a USD en las tablas que ya tienen restricciones.
        DB::table('facturas_orden')->where('moneda', 'COP')->update(['moneda' => 'USD']);
        DB::statement('ALTER TABLE facturas_orden DROP CONSTRAINT IF EXISTS facturas_orden_moneda_check');
        DB::statement("ALTER TABLE facturas_orden ADD CONSTRAINT facturas_orden_moneda_check CHECK (moneda = 'USD')");

        DB::table('facturas_cita')->where('moneda', 'COP')->update(['moneda' => 'USD']);
        DB::statement('ALTER TABLE facturas_cita DROP CONSTRAINT IF EXISTS facturas_cita_moneda_check');
        DB::statement("ALTER TABLE facturas_cita ADD CONSTRAINT facturas_cita_moneda_check CHECK (moneda = 'USD')");

        DB::table('pagos')->where('moneda', 'COP')->update(['moneda' => 'USD']);
        DB::statement('ALTER TABLE pagos DROP CONSTRAINT IF EXISTS pagos_moneda_check');
        DB::statement("ALTER TABLE pagos ADD CONSTRAINT pagos_moneda_check CHECK (moneda = 'USD')");

        DB::table('pago_movimientos')->where('moneda', 'COP')->update(['moneda' => 'USD']);
        DB::statement('ALTER TABLE pago_movimientos DROP CONSTRAINT IF EXISTS pago_movimientos_moneda_check');
        DB::statement("ALTER TABLE pago_movimientos ADD CONSTRAINT pago_movimientos_moneda_check CHECK (moneda = 'USD')");
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