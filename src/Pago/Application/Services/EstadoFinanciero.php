<?php

namespace Src\Pago\Application\Services;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

class EstadoFinanciero
{
    public function determinar(string|int|float $pagado, string|int|float $saldo, ?string $venceEn = null): string
    {
        $pagado = BigDecimal::of((string) $pagado);
        $saldo = BigDecimal::of((string) $saldo);
        if ($saldo->isLessThanOrEqualTo(0)) return 'pagado';
        if ($venceEn && CarbonImmutable::parse($venceEn)->endOfDay()->isPast()) return 'vencido';

        return $pagado->isLessThanOrEqualTo(0) ? 'pendiente' : 'parcial';
    }
}
