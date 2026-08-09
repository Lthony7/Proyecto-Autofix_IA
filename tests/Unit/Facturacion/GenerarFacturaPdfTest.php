<?php

namespace Tests\Unit\Facturacion;

use Src\Facturacion\Application\Jobs\EnviarFacturaPorCorreo;
use Src\Facturacion\Application\Services\GenerarFacturaPdf;
use Src\Facturacion\Infrastructure\Models\FacturaOrdenEloquentModel;
use Src\Facturacion\Infrastructure\Models\FacturaOrdenLineaEloquentModel;
use Tests\TestCase;

class GenerarFacturaPdfTest extends TestCase
{
    public function test_genera_pdf_solo_con_la_instantanea_de_la_factura(): void
    {
        $factura = new FacturaOrdenEloquentModel([
            'numero' => 'FAC-0001', 'version' => 1, 'cliente_tipo_documento' => 'CC',
            'cliente_documento' => '123', 'cliente_nombre' => 'Cliente histórico',
            'cliente_direccion' => 'Calle 1', 'cliente_email' => 'historico@example.test',
            'vehiculo_placa' => 'ABC123', 'subtotal' => '100000.00', 'descuento' => '0.00',
            'base_impuesto' => '100000.00', 'tasa_impuesto' => '0.00', 'impuesto' => '0.00',
            'total' => '100000.00', 'moneda' => 'COP', 'estado' => 'emitida',
            'emitida_en' => '2026-08-09 10:00:00',
        ]);
        $factura->setRelation('lineas', collect([new FacturaOrdenLineaEloquentModel([
            'tipo' => 'servicio', 'descripcion' => 'Diagnóstico', 'cantidad' => '1.000',
            'precio_unitario' => '100000.00', 'subtotal' => '100000.00',
        ])]));

        $generador = app(GenerarFacturaPdf::class);
        $datos = $generador->datos($factura);
        $pdf = $generador->generar($factura);

        $this->assertSame('Cliente histórico', $datos['clienteNombre']);
        $this->assertArrayNotHasKey('orden', $datos);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_job_de_envio_solo_transporta_ids_escalares(): void
    {
        $job = new EnviarFacturaPorCorreo('factura-id', 'usuario-id');

        $this->assertSame('factura-id', $job->facturaId);
        $this->assertSame('usuario-id', $job->usuarioId);
        $this->assertStringNotContainsString('@example.test', serialize($job));
    }
}
