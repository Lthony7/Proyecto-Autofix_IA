<?php

declare(strict_types=1);

namespace Src\Cita\Application\Services;

use Carbon\CarbonImmutable;

final class VentanaRecordatorioCitas
{
    public const ESTADOS_ELEGIBLES = ['pendiente', 'confirmada', 'reprogramada'];

    public static function contiene(
        string $estado,
        CarbonImmutable $inicio,
        CarbonImmutable $ahora,
        int $minutos,
    ): bool {
        return in_array($estado, self::ESTADOS_ELEGIBLES, true)
            && $inicio->greaterThan($ahora)
            && $inicio->lessThanOrEqualTo($ahora->addMinutes(max(1, $minutos)));
    }
}
