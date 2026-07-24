<?php

namespace Tests\Unit\Pago;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use Src\Pago\Application\Services\EstadoFinanciero;

class EstadoFinancieroTest extends TestCase
{
    public function test_determina_estados_por_saldo_y_vencimiento(): void
    {
        $servicio = new EstadoFinanciero();

        $this->assertSame('pendiente', $servicio->determinar(0, 100));
        $this->assertSame('parcial', $servicio->determinar(40, 60));
        $this->assertSame('pagado', $servicio->determinar(100, 0));
        $this->assertSame('vencido', $servicio->determinar(0, 100, CarbonImmutable::now()->subDay()->toDateString()));
        $this->assertSame('pendiente', $servicio->determinar(0, 100, CarbonImmutable::now()->addDay()->toDateString()));
    }
}
