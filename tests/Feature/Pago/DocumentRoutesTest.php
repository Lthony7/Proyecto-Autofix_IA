<?php

namespace Tests\Feature\Pago;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DocumentRoutesTest extends TestCase
{
    public function test_rutas_financieras_exigen_autenticacion_y_permisos_separados(): void
    {
        $pdf = Route::getRoutes()->getByName('pagos.pdf');
        $enviar = Route::getRoutes()->getByName('pagos.enviar');

        $this->assertSame(['GET', 'HEAD'], $pdf->methods());
        $this->assertContains('auth', $pdf->gatherMiddleware());
        $this->assertContains('permission:pagos.ver', $pdf->gatherMiddleware());
        $this->assertSame(['POST'], $enviar->methods());
        $this->assertContains('auth', $enviar->gatherMiddleware());
        $this->assertContains('permission:pagos.enviar', $enviar->gatherMiddleware());
    }
}
