<?php

namespace Tests\Unit\Cita;

use Tests\TestCase;

class ConfiguracionHorariaTest extends TestCase
{
    public function test_el_taller_usa_la_zona_horaria_de_ecuador(): void
    {
        $this->assertSame('America/Guayaquil', config('app.timezone'));
    }
}
