<?php

declare(strict_types=1);

namespace Tests\Unit\Cita;

use Tests\TestCase;

final class ConfiguracionRecordatoriosCitasTest extends TestCase
{
    public function test_los_recordatorios_tienen_interruptor_y_ventana_validos(): void
    {
        $this->assertIsBool(config('autofix.appointment_reminders.enabled'));
        $this->assertGreaterThan(0, config('autofix.appointment_reminders.window_minutes'));
        $this->assertSame('America/Bogota', config('app.timezone'));
    }
}
