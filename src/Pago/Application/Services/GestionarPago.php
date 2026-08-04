<?php

namespace Src\Pago\Application\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel;
use Src\Pago\Infrastructure\Models\PagoEloquentModel;
use Src\Pago\Infrastructure\Models\PagoHistorialEloquentModel;

class GestionarPago
{
    public function __construct(private readonly CalculadorTotalOrden $calculador) {}

    public function registrar(OrdenTrabajoEloquentModel $orden, array $datos, string $usuarioId): PagoEloquentModel
    {
        return DB::transaction(function () use ($orden, $datos, $usuarioId) {
            $bloqueada = OrdenTrabajoEloquentModel::whereKey($orden->id)->lockForUpdate()->firstOrFail();
            if ($bloqueada->estado === 'cancelada') {
                throw ValidationException::withMessages(['orden' => 'No se pueden registrar pagos en una orden cancelada.']);
            }

            $resumen = $this->calculador->calcular($bloqueada->id);
            $monto = BigDecimal::of((string) $datos['monto'])->toScale(2, RoundingMode::HALF_UP);
            $saldoActual = BigDecimal::of($resumen['saldo']);
            if ($monto->isLessThanOrEqualTo(0)) {
                throw ValidationException::withMessages(['monto' => 'El monto debe ser mayor que cero.']);
            }
            if ($monto->isGreaterThan($saldoActual)) {
                throw ValidationException::withMessages(['monto' => 'El monto supera el saldo pendiente de la orden.']);
            }
            if ($saldoActual->isZero()) {
                throw ValidationException::withMessages(['monto' => 'La orden ya está pagada.']);
            }

            $pagadoAcumulado = BigDecimal::of($resumen['pagado'])->plus($monto)->toScale(2, RoundingMode::HALF_UP);
            $saldoResultante = $saldoActual->minus($monto)->toScale(2, RoundingMode::HALF_UP);
            $detalle = DB::table('orden_servicios')->where('orden_id', $bloqueada->id)->where('estado', '<>', 'cancelado')->get()->map(fn ($linea) => [
                'tipo' => 'servicio', 'codigo' => null, 'descripcion' => $linea->nombre_servicio, 'cantidad' => '1.000',
                'precioUnitario' => $linea->precio_acordado, 'subtotal' => $linea->precio_acordado,
            ]);
            $detalle = $detalle->concat(DB::table('orden_repuestos as uso')
                ->join('repuestos as repuesto', 'repuesto.id', '=', 'uso.repuesto_id')
                ->where('uso.orden_id', $bloqueada->id)->whereNull('uso.revertido_en')->get()
                ->map(fn ($linea) => [
                    'tipo' => 'repuesto', 'codigo' => $linea->codigo, 'descripcion' => $linea->nombre, 'cantidad' => $linea->cantidad,
                    'precioUnitario' => $linea->precio_unitario,
                    'subtotal' => (string) BigDecimal::of((string) $linea->cantidad)
                        ->multipliedBy(BigDecimal::of((string) $linea->precio_unitario))
                        ->toScale(2, RoundingMode::HALF_UP),
                ]))->values()->all();
            $sufijo = mb_strtoupper(substr(str_replace('-', '', (string) str()->uuid()), 0, 8));
            $pago = PagoEloquentModel::create([
                'numero' => 'PG-'.now()->format('Ymd').'-'.$sufijo,
                'comprobante_numero' => 'RC-'.now()->format('Ymd').'-'.$sufijo,
                'orden_id' => $bloqueada->id,
                'monto' => (string) $monto,
                'moneda' => 'COP',
                'metodo' => $datos['metodo'],
                'referencia' => $datos['referencia'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
                'estado' => 'registrado',
                'pagado_en' => $datos['pagado_en'],
                'registrado_por' => $usuarioId,
                'servicios_snapshot' => $resumen['servicios'],
                'repuestos_snapshot' => $resumen['repuestos'],
                'descuento_snapshot' => $resumen['descuento'],
                'impuesto_snapshot' => $resumen['impuesto'],
                'detalle_snapshot' => $detalle,
                'total_orden_snapshot' => $resumen['total'],
                'pagado_acumulado_snapshot' => (string) $pagadoAcumulado,
                'saldo_resultante_snapshot' => (string) $saldoResultante,
            ]);
            PagoHistorialEloquentModel::create([
                'pago_id' => $pago->id,
                'evento' => 'registrado',
                'monto' => $pago->monto,
                'datos' => [
                    'metodo' => $pago->metodo,
                    'referencia' => $pago->referencia,
                    'total_orden' => $pago->total_orden_snapshot,
                    'pagado_acumulado' => $pago->pagado_acumulado_snapshot,
                    'saldo_resultante' => $pago->saldo_resultante_snapshot,
                ],
                'usuario_id' => $usuarioId,
            ]);

            return $pago;
        });
    }

    public function anular(PagoEloquentModel $pago, string $motivo, string $usuarioId): PagoEloquentModel
    {
        return DB::transaction(function () use ($pago, $motivo, $usuarioId) {
            $bloqueado = PagoEloquentModel::whereKey($pago->id)->lockForUpdate()->firstOrFail();
            $orden = OrdenTrabajoEloquentModel::whereKey($bloqueado->orden_id)->lockForUpdate()->firstOrFail();
            if ($orden->estado === 'entregada') {
                throw ValidationException::withMessages(['pago' => 'No se puede anular un pago después de entregar el vehículo.']);
            }
            if ($bloqueado->estado !== 'registrado') {
                throw ValidationException::withMessages(['pago' => 'Solo se pueden anular pagos vigentes.']);
            }
            $bloqueado->update(['estado' => 'anulado', 'anulado_en' => now(), 'anulado_por' => $usuarioId, 'motivo_anulacion' => $motivo]);
            PagoHistorialEloquentModel::create(['pago_id' => $bloqueado->id, 'evento' => 'anulado', 'monto' => $bloqueado->monto, 'datos' => ['motivo' => $motivo], 'usuario_id' => $usuarioId]);

            return $bloqueado;
        });
    }

    public function reembolsar(PagoEloquentModel $pago, string $motivo, string $usuarioId): PagoEloquentModel
    {
        return DB::transaction(function () use ($pago, $motivo, $usuarioId) {
            $bloqueado = PagoEloquentModel::whereKey($pago->id)->lockForUpdate()->firstOrFail();
            $orden = OrdenTrabajoEloquentModel::whereKey($bloqueado->orden_id)->lockForUpdate()->firstOrFail();
            if ($orden->estado === 'entregada') {
                throw ValidationException::withMessages(['pago' => 'No se puede reembolsar un pago después de entregar el vehículo.']);
            }
            if ($bloqueado->estado !== 'registrado') {
                throw ValidationException::withMessages(['pago' => 'Solo se pueden reembolsar pagos vigentes.']);
            }
            $bloqueado->update([
                'estado' => 'reembolsado',
                'reembolsado_en' => now(),
                'reembolsado_por' => $usuarioId,
                'motivo_reembolso' => $motivo,
            ]);
            PagoHistorialEloquentModel::create([
                'pago_id' => $bloqueado->id,
                'evento' => 'reembolsado',
                'monto' => $bloqueado->monto,
                'datos' => ['motivo' => $motivo, 'tipo' => 'reembolso_total'],
                'usuario_id' => $usuarioId,
            ]);

            return $bloqueado;
        });
    }
}
