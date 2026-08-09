<?php

namespace Tests\Unit\OrdenTrabajo;

use PHPUnit\Framework\Attributes\DataProvider;
use Src\OrdenTrabajo\Application\Services\FlujoEstadosOrden;
use Tests\TestCase;

class FlujoEstadosOrdenTest extends TestCase
{
    #[DataProvider('transicionesValidas')]
    public function test_permite_todas_las_transiciones_operativas(string $actual, string $nuevo, ?string $anteriorPausa = null): void
    {
        $this->assertTrue(app(FlujoEstadosOrden::class)->permite($actual, $nuevo, $anteriorPausa));
    }

    public function test_estados_terminales_no_tienen_salida(): void
    {
        $flujo = app(FlujoEstadosOrden::class);

        $this->assertSame([], $flujo->siguientes('entregada'));
        $this->assertSame([], $flujo->siguientes('cancelada'));
        $this->assertFalse($flujo->permite('pausada', 'en_reparacion', 'en_diagnostico'));
    }

    public function test_la_matriz_completa_no_expone_transiciones_inesperadas(): void
    {
        $flujo = app(FlujoEstadosOrden::class);
        $esperadas = [
            'pendiente' => ['asignada', 'cancelada'],
            'asignada' => ['en_diagnostico', 'pausada', 'cancelada'],
            'en_diagnostico' => ['esperando_aprobacion', 'esperando_repuestos', 'en_reparacion', 'pausada', 'cancelada'],
            'esperando_aprobacion' => ['en_diagnostico', 'esperando_repuestos', 'en_reparacion', 'pausada', 'cancelada'],
            'esperando_repuestos' => ['en_reparacion', 'pausada', 'cancelada'],
            'en_reparacion' => ['esperando_aprobacion', 'esperando_repuestos', 'en_prueba', 'pausada', 'cancelada'],
            'en_prueba' => ['en_reparacion', 'finalizada', 'pausada', 'cancelada'],
            'finalizada' => ['lista_entrega'], 'lista_entrega' => ['entregada'],
            'entregada' => [], 'cancelada' => [],
        ];

        foreach ($esperadas as $estado => $siguientes) {
            $this->assertSame($siguientes, $flujo->siguientes($estado));
        }
    }

    public static function transicionesValidas(): array
    {
        return [
            ['pendiente', 'asignada'], ['pendiente', 'cancelada'],
            ['asignada', 'en_diagnostico'], ['asignada', 'pausada'],
            ['en_diagnostico', 'esperando_aprobacion'], ['en_diagnostico', 'esperando_repuestos'], ['en_diagnostico', 'en_reparacion'],
            ['esperando_aprobacion', 'en_diagnostico'], ['esperando_aprobacion', 'en_reparacion'],
            ['esperando_repuestos', 'en_reparacion'], ['en_reparacion', 'en_prueba'],
            ['en_prueba', 'en_reparacion'], ['en_prueba', 'finalizada'],
            ['finalizada', 'lista_entrega'], ['lista_entrega', 'entregada'],
            ['pausada', 'en_diagnostico', 'en_diagnostico'], ['pausada', 'cancelada', 'en_diagnostico'],
        ];
    }
}
