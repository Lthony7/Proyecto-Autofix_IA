<?php

namespace Src\OrdenTrabajo\Application\Services;

class FlujoEstadosOrden
{
    public const DESTINOS = ['asignada', 'en_diagnostico', 'esperando_aprobacion', 'esperando_repuestos', 'en_reparacion', 'pausada', 'en_prueba', 'finalizada', 'lista_entrega', 'entregada', 'cancelada'];

    private const TRANSICIONES = [
        'pendiente' => ['asignada', 'cancelada'],
        'asignada' => ['en_diagnostico', 'pausada', 'cancelada'],
        'en_diagnostico' => ['esperando_aprobacion', 'esperando_repuestos', 'en_reparacion', 'pausada', 'cancelada'],
        'esperando_aprobacion' => ['en_diagnostico', 'esperando_repuestos', 'en_reparacion', 'pausada', 'cancelada'],
        'esperando_repuestos' => ['en_reparacion', 'pausada', 'cancelada'],
        'en_reparacion' => ['esperando_aprobacion', 'esperando_repuestos', 'en_prueba', 'pausada', 'cancelada'],
        'en_prueba' => ['en_reparacion', 'finalizada', 'pausada', 'cancelada'],
        'finalizada' => ['lista_entrega'],
        'lista_entrega' => ['entregada'],
        'entregada' => [],
        'cancelada' => [],
    ];

    public function permite(string $actual, string $nuevo, ?string $anteriorPausa = null): bool
    {
        return in_array($nuevo, $this->siguientes($actual, $anteriorPausa), true);
    }

    public function siguientes(string $actual, ?string $anteriorPausa = null): array
    {
        if ($actual === 'pausada') return array_values(array_filter([$anteriorPausa, 'cancelada']));

        return self::TRANSICIONES[$actual] ?? [];
    }
}
