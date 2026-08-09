<?php

namespace Src\Pago\Application\Services;

use App\Support\ConsecutivoDocumentos;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Src\Facturacion\Infrastructure\Models\FacturaOrdenEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel;
use Src\Pago\Infrastructure\Models\PagoEloquentModel;
use Src\Pago\Infrastructure\Models\PagoHistorialEloquentModel;
use Src\Pago\Infrastructure\Models\PagoMovimientoEloquentModel;

class GestionarPago
{
    public function __construct(private readonly CalculadorTotalOrden $calculador, private readonly ConsecutivoDocumentos $consecutivos) {}

    public function registrar(OrdenTrabajoEloquentModel $orden, array $datos, string $usuarioId): PagoEloquentModel
    {
        return DB::transaction(function () use ($orden, $datos, $usuarioId) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['pago:'.$datos['idempotencia_clave']]);
            $bloqueada = OrdenTrabajoEloquentModel::with(['cliente', 'vehiculo'])->whereKey($orden->id)->lockForUpdate()->firstOrFail();
            $monto = BigDecimal::of((string) $datos['monto'])->toScale(2, RoundingMode::HALF_UP);
            $pagadoEn = CarbonImmutable::parse($datos['pagado_en'])->toIso8601String();
            $hash = hash('sha256', json_encode([
                'orden' => $bloqueada->id, 'monto' => (string) $monto, 'metodo' => $datos['metodo'],
                'referencia' => $datos['referencia'] ?? null, 'observaciones' => $datos['observaciones'] ?? null, 'pagado_en' => $pagadoEn,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            $existente = PagoEloquentModel::where('idempotencia_clave', $datos['idempotencia_clave'])->lockForUpdate()->first();
            if ($existente) {
                if ($existente->orden_id !== $bloqueada->id || ! hash_equals($existente->solicitud_hash, $hash)) {
                    throw ValidationException::withMessages(['idempotenciaClave' => 'La clave de idempotencia ya fue utilizada con datos diferentes.']);
                }
                return $existente;
            }
            if (in_array($datos['metodo'], ['tarjeta', 'transferencia'], true)) {
                DB::select('select pg_advisory_xact_lock(hashtext(?))', ['pago-referencia:'.$datos['metodo'].':'.$datos['referencia']]);
                if (PagoEloquentModel::where('metodo', $datos['metodo'])->where('referencia', $datos['referencia'])->exists()) {
                    throw ValidationException::withMessages(['referencia' => 'Esta referencia electrónica ya fue registrada.']);
                }
            }
            if ($bloqueada->estado === 'cancelada') throw ValidationException::withMessages(['orden' => 'No se pueden registrar pagos en una orden cancelada.']);

            $factura = FacturaOrdenEloquentModel::with('lineas')->where('orden_id', $bloqueada->id)->where('estado', 'emitida')->lockForUpdate()->first();
            if (! $factura) throw ValidationException::withMessages(['orden' => 'Emite la factura definitiva antes de registrar el pago.']);
            if (CarbonImmutable::parse($datos['pagado_en'])->isBefore($factura->emitida_en)) throw ValidationException::withMessages(['pagadoEn' => 'La fecha del pago no puede ser anterior a la emisión de la factura.']);

            $resumen = $this->calculador->calcular($bloqueada->id);
            $saldoActual = BigDecimal::of($resumen['saldo']);
            if ($monto->isLessThanOrEqualTo(0)) throw ValidationException::withMessages(['monto' => 'El monto debe ser mayor que cero.']);
            if ($monto->isGreaterThan($saldoActual)) throw ValidationException::withMessages(['monto' => 'El monto supera el saldo pendiente de la orden.']);
            if ($saldoActual->isZero()) throw ValidationException::withMessages(['monto' => 'La orden ya está pagada.']);

            $pagadoAcumulado = BigDecimal::of($resumen['pagado'])->plus($monto)->toScale(2, RoundingMode::HALF_UP);
            $saldoResultante = $saldoActual->minus($monto)->toScale(2, RoundingMode::HALF_UP);
            $detalle = $factura->lineas->map(fn ($linea) => [
                'tipo' => $linea->tipo, 'codigo' => $linea->codigo, 'descripcion' => $linea->descripcion,
                'cantidad' => $linea->cantidad, 'precioUnitario' => $linea->precio_unitario, 'subtotal' => $linea->subtotal,
            ])->values()->all();

            $pago = PagoEloquentModel::create([
                'numero' => $this->consecutivos->siguiente('pago', 'PG'),
                'comprobante_numero' => $this->consecutivos->siguiente('comprobante_pago', 'RC'),
                'orden_id' => $bloqueada->id, 'factura_id' => $factura->id,
                'idempotencia_clave' => $datos['idempotencia_clave'], 'solicitud_hash' => $hash,
                'monto' => (string) $monto, 'moneda' => 'COP', 'metodo' => $datos['metodo'],
                'referencia' => $datos['referencia'] ?? null, 'observaciones' => $datos['observaciones'] ?? null,
                'estado' => 'registrado', 'pagado_en' => $pagadoEn, 'registrado_por' => $usuarioId,
                'factura_numero_snapshot' => $factura->numero, 'orden_numero_snapshot' => $bloqueada->numero,
                'cliente_tipo_documento_snapshot' => $factura->cliente_tipo_documento,
                'cliente_documento_snapshot' => $factura->cliente_documento, 'cliente_nombre_snapshot' => $factura->cliente_nombre,
                'vehiculo_placa_snapshot' => $factura->vehiculo_placa,
                'vehiculo_descripcion_snapshot' => trim("{$bloqueada->vehiculo?->marca} {$bloqueada->vehiculo?->modelo}"),
                'servicios_snapshot' => $resumen['servicios'], 'repuestos_snapshot' => $resumen['repuestos'],
                'descuento_snapshot' => $resumen['descuento'], 'impuesto_snapshot' => $resumen['impuesto'],
                'detalle_snapshot' => $detalle, 'total_orden_snapshot' => $resumen['total'],
                'pagado_acumulado_snapshot' => (string) $pagadoAcumulado, 'saldo_resultante_snapshot' => (string) $saldoResultante,
            ]);
            PagoHistorialEloquentModel::create([
                'pago_id' => $pago->id, 'evento' => 'registrado', 'monto' => $pago->monto,
                'datos' => ['metodo' => $pago->metodo, 'referencia' => $pago->referencia, 'factura' => $factura->numero, 'total_orden' => $pago->total_orden_snapshot, 'pagado_acumulado' => $pago->pagado_acumulado_snapshot, 'saldo_resultante' => $pago->saldo_resultante_snapshot],
                'usuario_id' => $usuarioId,
            ]);
            PagoMovimientoEloquentModel::create([
                'pago_id' => $pago->id, 'orden_id' => $pago->orden_id, 'tipo' => 'ingreso', 'monto' => $pago->monto,
                'moneda' => $pago->moneda, 'metodo' => $pago->metodo, 'referencia' => $pago->referencia,
                'ocurrido_en' => $pago->pagado_en, 'registrado_por' => $usuarioId, 'metadata' => ['factura' => $factura->numero],
            ]);

            return $pago;
        });
    }

    public function anular(PagoEloquentModel $pago, string $motivo, string $usuarioId): PagoEloquentModel
    {
        return DB::transaction(function () use ($pago, $motivo, $usuarioId) {
            $bloqueado = PagoEloquentModel::whereKey($pago->id)->lockForUpdate()->firstOrFail();
            $orden = OrdenTrabajoEloquentModel::whereKey($bloqueado->orden_id)->lockForUpdate()->firstOrFail();
            if ($orden->estado === 'entregada') throw ValidationException::withMessages(['pago' => 'No se puede anular un pago después de entregar el vehículo.']);
            if ($bloqueado->estado !== 'registrado') throw ValidationException::withMessages(['pago' => 'Solo se pueden anular pagos vigentes.']);
            $bloqueado->update(['estado' => 'anulado', 'anulado_en' => now(), 'anulado_por' => $usuarioId, 'motivo_anulacion' => $motivo]);
            PagoHistorialEloquentModel::create(['pago_id' => $bloqueado->id, 'evento' => 'anulado', 'monto' => $bloqueado->monto, 'datos' => ['motivo' => $motivo], 'usuario_id' => $usuarioId]);
            $this->registrarReversion($bloqueado, 'anulacion', $motivo, $usuarioId);
            return $bloqueado;
        });
    }

    public function reembolsar(PagoEloquentModel $pago, string $motivo, string $usuarioId): PagoEloquentModel
    {
        return DB::transaction(function () use ($pago, $motivo, $usuarioId) {
            $bloqueado = PagoEloquentModel::whereKey($pago->id)->lockForUpdate()->firstOrFail();
            $orden = OrdenTrabajoEloquentModel::whereKey($bloqueado->orden_id)->lockForUpdate()->firstOrFail();
            if ($orden->estado === 'entregada') throw ValidationException::withMessages(['pago' => 'No se puede reembolsar un pago después de entregar el vehículo.']);
            if ($bloqueado->estado !== 'registrado') throw ValidationException::withMessages(['pago' => 'Solo se pueden reembolsar pagos vigentes.']);
            $bloqueado->update(['estado' => 'reembolsado', 'reembolsado_en' => now(), 'reembolsado_por' => $usuarioId, 'motivo_reembolso' => $motivo]);
            PagoHistorialEloquentModel::create(['pago_id' => $bloqueado->id, 'evento' => 'reembolsado', 'monto' => $bloqueado->monto, 'datos' => ['motivo' => $motivo, 'tipo' => 'reembolso_total'], 'usuario_id' => $usuarioId]);
            $this->registrarReversion($bloqueado, 'reembolso', $motivo, $usuarioId);
            return $bloqueado;
        });
    }

    private function registrarReversion(PagoEloquentModel $pago, string $tipo, string $motivo, string $usuarioId): void
    {
        PagoMovimientoEloquentModel::create([
            'pago_id' => $pago->id, 'orden_id' => $pago->orden_id, 'tipo' => $tipo,
            'monto' => (string) BigDecimal::of((string) $pago->monto)->negated(), 'moneda' => $pago->moneda,
            'metodo' => $pago->metodo, 'referencia' => $pago->referencia, 'ocurrido_en' => now(),
            'registrado_por' => $usuarioId, 'metadata' => ['motivo' => $motivo],
        ]);
    }
}
