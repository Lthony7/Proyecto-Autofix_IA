<?php

declare(strict_types=1);

namespace Tests\Unit\Cita;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Cita\Application\Services\VentanaRecordatorioCitas;

final class VentanaRecordatorioCitasTest extends TestCase
{
    #[DataProvider('casosDeElegibilidad')]
    public function test_selecciona_solo_estados_proximos_elegibles(
        string $estado,
        int $desplazamientoMinutos,
        bool $esperado,
    ): void {
        $ahora = CarbonImmutable::parse('2026-08-09 08:00:00', 'America/Guayaquil');

        $this->assertSame(
            $esperado,
            VentanaRecordatorioCitas::contiene(
                $estado,
                $ahora->addMinutes($desplazamientoMinutos),
                $ahora,
                60,
            ),
        );
    }

    public static function casosDeElegibilidad(): array
    {
        return [
            'pendiente dentro de ventana' => ['pendiente', 30, true],
            'confirmada al limite' => ['confirmada', 60, true],
            'reprogramada dentro de ventana' => ['reprogramada', 1, true],
            'cita ya iniciada' => ['pendiente', 0, false],
            'fuera de ventana' => ['confirmada', 61, false],
            'estado final' => ['cancelada', 30, false],
            'cita vencida' => ['vencida', 30, false],
        ];
    }
}
