<?php

namespace Tests\Feature\Facturacion;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DocumentRoutesTest extends TestCase
{
    public function test_rutas_financieras_exigen_autenticacion_y_permisos_separados(): void
    {
        $pdf = Route::getRoutes()->getByName('facturacion.pdf');
        $enviar = Route::getRoutes()->getByName('facturacion.enviar');

        $this->assertSame(['GET', 'HEAD'], $pdf->methods());
        $this->assertContains('auth', $pdf->gatherMiddleware());
        $this->assertContains('permission:facturas.ver', $pdf->gatherMiddleware());
        $this->assertSame(['POST'], $enviar->methods());
        $this->assertContains('auth', $enviar->gatherMiddleware());
        $this->assertContains('permission:facturas.enviar', $enviar->gatherMiddleware());
    }
}
