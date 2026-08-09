<?php

namespace Src\Facturacion\Application\Services;
use App\Support\ConsecutivoDocumentos;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Src\Facturacion\Infrastructure\Models\FacturaOrdenEloquentModel;
use Src\Facturacion\Infrastructure\Models\FacturaOrdenHistorialEloquentModel;
use Src\Facturacion\Infrastructure\Models\FacturaOrdenLineaEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel;

class GestionarFacturaOrden
{
    public function __construct(private readonly ConsecutivoDocumentos $consecutivos) {}

    public function emitir(OrdenTrabajoEloquentModel $orden, array $datos, string $usuarioId): FacturaOrdenEloquentModel
    {
        return DB::transaction(function () use ($orden, $datos, $usuarioId) {
            $bloqueada = OrdenTrabajoEloquentModel::with(['cliente', 'vehiculo'])->whereKey($orden->id)->lockForUpdate()->firstOrFail();
            if (! in_array($bloqueada->estado, ['finalizada', 'lista_entrega', 'entregada'], true)) throw ValidationException::withMessages(['orden' => 'Solo se puede facturar una orden finalizada.']);
            if (FacturaOrdenEloquentModel::where('orden_id', $bloqueada->id)->where('estado', 'emitida')->exists()) throw ValidationException::withMessages(['orden' => 'La orden ya tiene una factura vigente.']);
            $anterior = FacturaOrdenEloquentModel::where('orden_id', $bloqueada->id)->latest('emitida_en')->lockForUpdate()->first();

            $lineas = [];
            $subtotal = BigDecimal::zero();
            foreach (DB::table('orden_servicios')->where('orden_id', $bloqueada->id)->where('estado', 'completado')->where('aprobacion_estado', 'aprobado')->get() as $servicio) {
                $valor = BigDecimal::of((string) $servicio->precio_acordado)->toScale(2);
                $subtotal = $subtotal->plus($valor);
                $lineas[] = ['tipo' => 'servicio', 'referencia_id' => $servicio->servicio_id, 'codigo' => null, 'descripcion' => $servicio->nombre_servicio, 'cantidad' => '1.000', 'precio_unitario' => (string) $valor, 'subtotal' => (string) $valor];
            }
            foreach (DB::table('orden_repuestos')->where('orden_id', $bloqueada->id)->whereNull('revertido_en')->where('facturable', true)->get() as $repuesto) {
                $valor = BigDecimal::of((string) $repuesto->cantidad)->multipliedBy(BigDecimal::of((string) $repuesto->precio_unitario))->toScale(2, RoundingMode::HALF_UP);
                $subtotal = $subtotal->plus($valor);
                $lineas[] = ['tipo' => 'repuesto', 'referencia_id' => $repuesto->repuesto_id, 'codigo' => $repuesto->codigo_snapshot, 'descripcion' => $repuesto->nombre_snapshot, 'cantidad' => $repuesto->cantidad, 'precio_unitario' => $repuesto->precio_unitario, 'subtotal' => (string) $valor];
            }
            if (! $lineas || $subtotal->isZero()) throw ValidationException::withMessages(['orden' => 'La orden no tiene conceptos facturables realizados.']);
            $descuento = BigDecimal::of((string) $datos['descuento'])->toScale(2, RoundingMode::HALF_UP);
            if ($descuento->isGreaterThan($subtotal)) throw ValidationException::withMessages(['descuento' => 'El descuento no puede superar el subtotal.']);
            $base = $subtotal->minus($descuento)->toScale(2, RoundingMode::HALF_UP);
            $tasa = BigDecimal::of((string) config('autofix.tax_rate', '0.00'))->toScale(2, RoundingMode::HALF_UP);
            $impuesto = $base->multipliedBy($tasa)->dividedBy(100, 2, RoundingMode::HALF_UP);
            $total = $base->plus($impuesto)->toScale(2, RoundingMode::HALF_UP);
            $factura = FacturaOrdenEloquentModel::create([
                'numero' => $this->consecutivos->siguiente('factura_interna', 'FAC'), 'orden_id' => $bloqueada->id,
                'version' => ((int) $anterior?->version) + 1, 'reemplaza_factura_id' => $anterior?->id,
                'cliente_tipo_documento' => $bloqueada->cliente->tipo_documento, 'cliente_documento' => $bloqueada->cliente->numero_documento, 'cliente_nombre' => $bloqueada->cliente->razon_social,
                'cliente_direccion' => $bloqueada->cliente->direccion, 'cliente_email' => $bloqueada->cliente->email, 'vehiculo_placa' => $bloqueada->vehiculo->placa,
                'subtotal' => (string) $subtotal, 'descuento' => (string) $descuento,
                'motivo_descuento' => $descuento->isZero() ? null : ($datos['motivo_descuento'] ?? null),
                'descuento_autorizado_por' => $descuento->isZero() ? null : ($datos['descuento_autorizado_por'] ?? null),
                'descuento_autorizado_en' => $descuento->isZero() ? null : ($datos['descuento_autorizado_en'] ?? null),
                'base_impuesto' => (string) $base, 'tasa_impuesto' => (string) $tasa,
                'impuesto' => (string) $impuesto, 'total' => (string) $total, 'moneda' => 'USD', 'estado' => 'emitida', 'emitida_en' => now(),
                'vence_en' => $datos['vence_en'] ?? null, 'observaciones' => $datos['observaciones'] ?? null, 'emitida_por' => $usuarioId,
            ]);
            foreach ($lineas as $linea) FacturaOrdenLineaEloquentModel::create([...$linea, 'factura_id' => $factura->id]);
            FacturaOrdenHistorialEloquentModel::create(['factura_id' => $factura->id, 'evento' => 'emitida', 'datos' => ['total' => (string) $total, 'version' => $factura->version, 'reemplaza' => $anterior?->numero], 'usuario_id' => $usuarioId]);
            return $factura;
        });
    }

    public function anular(FacturaOrdenEloquentModel $factura, string $motivo, string $usuarioId): FacturaOrdenEloquentModel
    {
        return DB::transaction(function () use ($factura, $motivo, $usuarioId) {
            $bloqueada = FacturaOrdenEloquentModel::whereKey($factura->id)->lockForUpdate()->firstOrFail();
            $orden = OrdenTrabajoEloquentModel::whereKey($bloqueada->orden_id)->lockForUpdate()->firstOrFail();
            if ($orden->estado === 'entregada') throw ValidationException::withMessages(['factura' => 'No se puede anular la factura después de entregar el vehículo.']);
            if ($bloqueada->estado === 'anulada') throw ValidationException::withMessages(['factura' => 'La factura ya fue anulada.']);
            if (DB::table('pagos')->where('orden_id', $bloqueada->orden_id)->where('estado', 'registrado')->exists()) throw ValidationException::withMessages(['factura' => 'Primero anula los pagos vigentes de la orden.']);
            $bloqueada->update(['estado' => 'anulada', 'anulada_en' => now(), 'anulada_por' => $usuarioId, 'motivo_anulacion' => $motivo]);
            FacturaOrdenHistorialEloquentModel::create(['factura_id' => $bloqueada->id, 'evento' => 'anulada', 'datos' => ['motivo' => $motivo], 'usuario_id' => $usuarioId]);
            return $bloqueada;
        });
    }
}
