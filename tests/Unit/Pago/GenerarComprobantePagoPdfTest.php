<?php

namespace Tests\Unit\Pago;

use Illuminate\Validation\ValidationException;
use Src\Pago\Application\Jobs\EnviarComprobantePagoPorCorreo;
use Src\Pago\Application\Services\GenerarComprobantePagoPdf;
use Src\Pago\Infrastructure\Models\PagoEloquentModel;
use Tests\TestCase;

class GenerarComprobantePagoPdfTest extends TestCase
{
    public function test_genera_pdf_solo_con_la_instantanea_del_pago(): void
    {
        $pago = new PagoEloquentModel([
            'numero' => 'PG-0001', 'comprobante_numero' => 'RC-0001', 'orden_id' => 'orden-id',
            'factura_id' => 'factura-id', 'monto' => '50000.00', 'moneda' => 'COP',
            'metodo' => 'transferencia', 'referencia' => 'REF-1', 'estado' => 'registrado',
            'pagado_en' => '2026-08-09 11:00:00', 'factura_numero_snapshot' => 'FAC-0001',
            'orden_numero_snapshot' => 'OT-0001', 'cliente_tipo_documento_snapshot' => 'CC',
            'cliente_documento_snapshot' => '123', 'cliente_nombre_snapshot' => 'Cliente histórico',
            'vehiculo_placa_snapshot' => 'ABC123', 'vehiculo_descripcion_snapshot' => 'Marca Modelo',
            'servicios_snapshot' => '100000.00', 'repuestos_snapshot' => '0.00',
            'descuento_snapshot' => '0.00', 'impuesto_snapshot' => '0.00',
            'detalle_snapshot' => [[
                'tipo' => 'servicio', 'codigo' => null, 'descripcion' => 'Diagnóstico',
                'cantidad' => '1.000', 'precioUnitario' => '100000.00', 'subtotal' => '100000.00',
            ]],
            'total_orden_snapshot' => '100000.00', 'pagado_acumulado_snapshot' => '50000.00',
            'saldo_resultante_snapshot' => '50000.00',
        ]);

        $generador = app(GenerarComprobantePagoPdf::class);
        $datos = $generador->datos($pago);
        $pdf = $generador->generar($pago);

        $this->assertSame('Cliente histórico', $datos['clienteNombre']);
        $this->assertArrayNotHasKey('orden', $datos);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_rechaza_pago_historico_sin_instantanea_completa(): void
    {
        $this->expectException(ValidationException::class);

        app(GenerarComprobantePagoPdf::class)->datos(new PagoEloquentModel([
            'numero' => 'PG-LEGACY', 'monto' => '100.00',
        ]));
    }

    public function test_job_de_envio_solo_transporta_ids_escalares(): void
    {
        $job = new EnviarComprobantePagoPorCorreo('pago-id', 'usuario-id');

        $this->assertSame('pago-id', $job->pagoId);
        $this->assertSame('usuario-id', $job->usuarioId);
        $this->assertStringNotContainsString('@example.test', serialize($job));
    }
}
