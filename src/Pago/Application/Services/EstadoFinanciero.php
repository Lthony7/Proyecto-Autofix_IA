<?php

namespace Src\Pago\Application\Services;

use Carbon\CarbonImmutable;

class EstadoFinanciero
{
    public function determinar(float $pagado, float $saldo, string|null $venceEn = null): string
    {
        if ($saldo <= 0.00001) {
            return 'pagado';
        }

        if ($venceEn && CarbonImmutable::parse($venceEn)->endOfDay()->isPast()) {
            return 'vencido';
        }

        return $pagado <= 0.00001 ? 'pendiente' : 'parcial';
    }
}
