<?php

namespace Src\Pago\Application\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Validation\ValidationException;
use Src\Pago\Infrastructure\Models\PagoEloquentModel;

class GenerarComprobantePagoPdf
{
    public function datos(PagoEloquentModel $pago): array
    {
        $requeridos = [
            $pago->factura_id,
            $pago->detalle_snapshot,
            $pago->total_orden_snapshot,
            $pago->pagado_acumulado_snapshot,
            $pago->saldo_resultante_snapshot,
        ];
        if (in_array(null, $requeridos, true)) {
            throw ValidationException::withMessages([
                'pago' => 'Este pago histórico no tiene una instantánea completa para generar un comprobante determinista.',
            ]);
        }

        return [
            'numero' => $pago->numero,
            'comprobanteNumero' => $pago->comprobante_numero,
            'facturaNumero' => $pago->factura_numero_snapshot,
            'ordenNumero' => $pago->orden_numero_snapshot,
            'clienteTipoDocumento' => $pago->cliente_tipo_documento_snapshot,
            'clienteDocumento' => $pago->cliente_documento_snapshot,
            'clienteNombre' => $pago->cliente_nombre_snapshot,
            'vehiculoPlaca' => $pago->vehiculo_placa_snapshot,
            'vehiculoDescripcion' => $pago->vehiculo_descripcion_snapshot,
            'servicios' => $pago->servicios_snapshot,
            'repuestos' => $pago->repuestos_snapshot,
            'descuento' => $pago->descuento_snapshot,
            'impuesto' => $pago->impuesto_snapshot,
            'detalle' => $pago->detalle_snapshot,
            'totalOrden' => $pago->total_orden_snapshot,
            'monto' => $pago->monto,
            'pagadoAcumulado' => $pago->pagado_acumulado_snapshot,
            'saldoResultante' => $pago->saldo_resultante_snapshot,
            'moneda' => $pago->moneda,
            'metodo' => $pago->metodo,
            'referencia' => $pago->referencia,
            'observaciones' => $pago->observaciones,
            'estado' => $pago->estado,
            'pagadoEn' => $pago->pagado_en?->format('Y-m-d H:i:s P'),
            'motivoAnulacion' => $pago->motivo_anulacion,
            'motivoReembolso' => $pago->motivo_reembolso,
        ];
    }

    public function generar(PagoEloquentModel $pago): string
    {
        return $this->render(view('documents.comprobante-pago', ['documento' => $this->datos($pago)])->render());
    }

    private function render(string $html): string
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot(resource_path('views/documents'));

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4');
        $dompdf->render();

        return $dompdf->output();
    }
}
