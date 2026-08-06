<?php

namespace Tests\Unit\Cita;

use ReflectionMethod;
use Src\Cita\Application\Controllers\CitaWebController;
use Tests\TestCase;

class ReporteIaParaCitaTest extends TestCase
{
    public function test_el_resumen_para_la_cita_conserva_el_reporte_detallado_del_cliente(): void
    {
        $metodo = new ReflectionMethod(CitaWebController::class, 'resumenReporteIa');
        $resumen = $metodo->invoke(new CitaWebController, [
            'sintoma_principal' => 'El vehículo pierde potencia.',
            'momento_ocurre' => 'Al acelerar',
            'frecuencia' => 'muy_frecuente',
            'tiempo_desde_inicio' => 'Hace dos semanas',
            'intensidad' => 'alta',
            'condiciones' => ['caliente', 'subida'],
            'senales' => 'Humo oscuro',
            'luces_tablero' => 'Check engine',
            'perdida_potencia_arranque' => 'Pérdida de potencia',
            'codigos_obd' => 'P0299',
            'pruebas_realizadas' => 'Se revisó el nivel de aceite',
            'reparaciones_recientes' => 'Cambio de filtro',
            'observaciones' => 'Empeora con carga',
        ]);

        $this->assertStringContainsString('El vehículo pierde potencia.', $resumen);
        $this->assertStringContainsString('Momento: Al acelerar', $resumen);
        $this->assertStringContainsString('Frecuencia: muy frecuente', $resumen);
        $this->assertStringContainsString('Condiciones: caliente, subida', $resumen);
        $this->assertStringContainsString('Códigos OBD: P0299', $resumen);
        $this->assertStringContainsString('Observaciones: Empeora con carga', $resumen);
    }
}
