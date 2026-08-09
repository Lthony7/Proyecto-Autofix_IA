<?php

namespace Src\Facturacion\Application\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Src\Facturacion\Infrastructure\Models\FacturaOrdenEloquentModel;

class GenerarFacturaPdf
{
    public function datos(FacturaOrdenEloquentModel $factura): array
    {
        $factura->loadMissing(['lineas' => fn ($query) => $query->orderBy('created_at')->orderBy('id')]);

        return [
            'numero' => $factura->numero,
            'version' => $factura->version,
            'clienteTipoDocumento' => $factura->cliente_tipo_documento,
            'clienteDocumento' => $factura->cliente_documento,
            'clienteNombre' => $factura->cliente_nombre,
            'clienteDireccion' => $factura->cliente_direccion,
            'clienteEmail' => $factura->cliente_email,
            'vehiculoPlaca' => $factura->vehiculo_placa,
            'subtotal' => $factura->subtotal,
            'descuento' => $factura->descuento,
            'motivoDescuento' => $factura->motivo_descuento,
            'baseImpuesto' => $factura->base_impuesto,
            'tasaImpuesto' => $factura->tasa_impuesto,
            'impuesto' => $factura->impuesto,
            'total' => $factura->total,
            'moneda' => $factura->moneda,
            'estado' => $factura->estado,
            'emitidaEn' => $factura->emitida_en?->format('Y-m-d H:i:s P'),
            'venceEn' => $factura->vence_en?->format('Y-m-d'),
            'observaciones' => $factura->observaciones,
            'motivoAnulacion' => $factura->motivo_anulacion,
            'lineas' => $factura->lineas->map(fn ($linea) => [
                'tipo' => $linea->tipo,
                'codigo' => $linea->codigo,
                'descripcion' => $linea->descripcion,
                'cantidad' => $linea->cantidad,
                'precioUnitario' => $linea->precio_unitario,
                'subtotal' => $linea->subtotal,
            ])->all(),
        ];
    }

    public function generar(FacturaOrdenEloquentModel $factura): string
    {
        return $this->render(view('documents.factura', ['documento' => $this->datos($factura)])->render());
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
