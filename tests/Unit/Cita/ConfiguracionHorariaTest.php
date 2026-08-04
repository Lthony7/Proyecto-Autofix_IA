<?php

namespace Tests\Unit\Cita;

use Tests\TestCase;

class ConfiguracionHorariaTest extends TestCase
{
    public function test_el_taller_usa_la_zona_horaria_de_colombia(): void
    {
        $this->assertSame('America/Bogota', config('app.timezone'));
    }
}
