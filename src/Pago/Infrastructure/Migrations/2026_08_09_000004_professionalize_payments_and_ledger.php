<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql' || DB::scalar('select current_schema()') === 'public') {
            throw new RuntimeException('La migración exige PostgreSQL y un DB_SCHEMA dedicado distinto de public.');
        }

        Schema::table('pagos', function (Blueprint $table) {
            $table->foreignUuid('factura_id')->nullable()->constrained('facturas_orden')->restrictOnDelete();
            $table->uuid('idempotencia_clave')->nullable()->unique();
            $table->char('solicitud_hash', 64)->nullable();
            $table->string('factura_numero_snapshot', 30)->nullable();
            $table->string('orden_numero_snapshot', 30)->nullable();
            $table->string('cliente_tipo_documento_snapshot', 30)->nullable();
            $table->string('cliente_documento_snapshot', 50)->nullable();
            $table->string('cliente_nombre_snapshot', 180)->nullable();
            $table->string('vehiculo_placa_snapshot', 20)->nullable();
            $table->string('vehiculo_descripcion_snapshot', 220)->nullable();
            $table->index(['factura_id', 'estado']);
        });

        foreach (DB::table('pagos')->get() as $pago) {
            $factura = DB::table('facturas_orden')->where('orden_id', $pago->orden_id)->where('emitida_en', '<=', $pago->pagado_en)->latest('emitida_en')->first();
            $orden = DB::table('ordenes_trabajo')->where('id', $pago->orden_id)->first();
            if ($factura && $orden) DB::table('pagos')->where('id', $pago->id)->update([
                'factura_id' => $factura->id, 'factura_numero_snapshot' => $factura->numero, 'orden_numero_snapshot' => $orden->numero,
                'cliente_tipo_documento_snapshot' => $factura->cliente_tipo_documento, 'cliente_documento_snapshot' => $factura->cliente_documento,
                'cliente_nombre_snapshot' => $factura->cliente_nombre, 'vehiculo_placa_snapshot' => $factura->vehiculo_placa,
                'vehiculo_descripcion_snapshot' => $factura->vehiculo_placa,
            ]);
        }

        Schema::create('pago_movimientos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pago_id')->constrained('pagos')->restrictOnDelete();
            $table->foreignUuid('orden_id')->constrained('ordenes_trabajo')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->decimal('monto', 14, 2);
            $table->char('moneda', 3)->default('COP');
            $table->string('metodo', 30);
            $table->string('referencia', 120)->nullable();
            $table->timestampTz('ocurrido_en');
            $table->foreignUuid('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['pago_id', 'tipo']);
            $table->index(['ocurrido_en', 'tipo']);
            $table->index(['orden_id', 'ocurrido_en']);
        });
        DB::statement("ALTER TABLE pago_movimientos ADD CONSTRAINT pago_movimientos_tipo_check CHECK (tipo IN ('ingreso','anulacion','reembolso'))");
        DB::statement("ALTER TABLE pago_movimientos ADD CONSTRAINT pago_movimientos_signo_check CHECK ((tipo = 'ingreso' AND monto > 0) OR (tipo IN ('anulacion','reembolso') AND monto < 0))");
        DB::statement("ALTER TABLE pago_movimientos ADD CONSTRAINT pago_movimientos_moneda_check CHECK (moneda = 'COP')");
        DB::statement('ALTER TABLE pagos ADD CONSTRAINT pagos_idempotencia_check CHECK ((idempotencia_clave IS NULL AND solicitud_hash IS NULL) OR (idempotencia_clave IS NOT NULL AND solicitud_hash IS NOT NULL))');
        DB::statement('ALTER TABLE pagos ADD CONSTRAINT pagos_snapshot_calculo_check CHECK (idempotencia_clave IS NULL OR (factura_id IS NOT NULL AND factura_numero_snapshot IS NOT NULL AND orden_numero_snapshot IS NOT NULL AND cliente_nombre_snapshot IS NOT NULL AND vehiculo_placa_snapshot IS NOT NULL AND total_orden_snapshot IS NOT NULL AND pagado_acumulado_snapshot IS NOT NULL AND saldo_resultante_snapshot IS NOT NULL AND saldo_resultante_snapshot = total_orden_snapshot - pagado_acumulado_snapshot))');

        foreach (DB::table('pagos')->orderBy('created_at')->get() as $pago) {
            DB::table('pago_movimientos')->insert([
                'id' => (string) Str::uuid(), 'pago_id' => $pago->id, 'orden_id' => $pago->orden_id,
                'tipo' => 'ingreso', 'monto' => $pago->monto, 'moneda' => $pago->moneda,
                'metodo' => $pago->metodo, 'referencia' => $pago->referencia, 'ocurrido_en' => $pago->pagado_en,
                'registrado_por' => $pago->registrado_por, 'metadata' => json_encode(['reconstruido' => true]), 'created_at' => $pago->created_at,
            ]);
            if ($pago->estado === 'anulado') $this->insertarReversion($pago, 'anulacion', $pago->anulado_en, $pago->anulado_por, $pago->motivo_anulacion);
            if ($pago->estado === 'reembolsado') $this->insertarReversion($pago, 'reembolso', $pago->reembolsado_en, $pago->reembolsado_por, $pago->motivo_reembolso);
        }
    }

    private function insertarReversion(object $pago, string $tipo, mixed $fecha, ?string $usuario, ?string $motivo): void
    {
        DB::table('pago_movimientos')->insert([
            'id' => (string) Str::uuid(), 'pago_id' => $pago->id, 'orden_id' => $pago->orden_id,
            'tipo' => $tipo, 'monto' => '-'.$pago->monto, 'moneda' => $pago->moneda,
            'metodo' => $pago->metodo, 'referencia' => $pago->referencia, 'ocurrido_en' => $fecha,
            'registrado_por' => $usuario, 'metadata' => json_encode(['motivo' => $motivo, 'reconstruido' => true]), 'created_at' => $fecha,
        ]);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pagos DROP CONSTRAINT IF EXISTS pagos_snapshot_calculo_check');
        DB::statement('ALTER TABLE pagos DROP CONSTRAINT IF EXISTS pagos_idempotencia_check');
        Schema::dropIfExists('pago_movimientos');
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropIndex(['factura_id', 'estado']);
            $table->dropUnique(['idempotencia_clave']);
            $table->dropConstrainedForeignId('factura_id');
            $table->dropColumn(['idempotencia_clave', 'solicitud_hash', 'factura_numero_snapshot', 'orden_numero_snapshot', 'cliente_tipo_documento_snapshot', 'cliente_documento_snapshot', 'cliente_nombre_snapshot', 'vehiculo_placa_snapshot', 'vehiculo_descripcion_snapshot']);
        });
    }
};
