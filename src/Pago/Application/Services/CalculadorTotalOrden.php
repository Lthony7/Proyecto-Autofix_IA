<?php

namespace Src\Pago\Application\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

class CalculadorTotalOrden
{
    public function __construct(private readonly EstadoFinanciero $estadoFinanciero) {}

    public function calcular(string $ordenId): array
    {
        $servicios = BigDecimal::of((string) DB::table('orden_servicios')
            ->where('orden_id', $ordenId)->where('estado', '<>', 'cancelado')->sum('precio_acordado'));
        $repuestos = BigDecimal::zero();
        foreach (DB::table('orden_repuestos')->where('orden_id', $ordenId)->whereNull('revertido_en')->get(['cantidad', 'precio_unitario']) as $repuesto) {
            $repuestos = $repuestos->plus(BigDecimal::of((string) $repuesto->cantidad)->multipliedBy(BigDecimal::of((string) $repuesto->precio_unitario)));
        }

        $pagado = BigDecimal::of((string) DB::table('pagos')->where('orden_id', $ordenId)->where('estado', 'registrado')->sum('monto'));
        $servicios = $servicios->toScale(2, RoundingMode::HALF_UP);
        $repuestos = $repuestos->toScale(2, RoundingMode::HALF_UP);
        $factura = DB::table('facturas_orden')->where('orden_id', $ordenId)->where('estado', 'emitida')->first(['descuento', 'impuesto', 'total', 'vence_en']);
        $total = $factura ? BigDecimal::of((string) $factura->total) : $servicios->plus($repuestos);
        $saldo = $total->minus($pagado)->toScale(2, RoundingMode::HALF_UP);
        $pagado = $pagado->toScale(2, RoundingMode::HALF_UP);
        $estado = $this->estadoFinanciero->determinar((float) (string) $pagado, (float) (string) $saldo, $factura?->vence_en);

        return [
            'servicios' => (string) $servicios,
            'repuestos' => (string) $repuestos,
            'descuento' => $factura ? (string) $factura->descuento : '0.00',
            'impuesto' => $factura ? (string) $factura->impuesto : '0.00',
            'total' => (string) $total,
            'pagado' => (string) $pagado,
            'saldo' => (string) $saldo,
            'estado' => $estado,
            'moneda' => 'COP',
        ];
    }
}
