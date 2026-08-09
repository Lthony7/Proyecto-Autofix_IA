<?php

namespace Tests\Feature;

use Tests\TestCase;

class ModulesAccessTest extends TestCase
{
    public function test_new_modules_require_authentication(): void
    {
        $this->get(route('historial-vehicular.index'))->assertRedirect(route('login'));
        $this->get(route('historial-vehicular.bitacora'))->assertRedirect(route('login'));
        $this->get(route('mi-historial.index'))->assertRedirect(route('login'));
        $this->get(route('reportes.index'))->assertRedirect(route('login'));
        $this->get(route('reportes.exportar', ['tipo' => 'servicios']))->assertRedirect(route('login'));
        foreach ([
            'inventario.nueva-referencia',
            'inventario.registrar-movimiento',
            'inventario.catalogos-auxiliares',
            'inventario.catalogo',
            'inventario.ultimos-movimientos',
            'reportes.filtros',
            'reportes.ordenes-pendientes',
            'reportes.ordenes-en-reparacion',
            'reportes.ordenes-finalizadas',
            'reportes.ingresos',
            'reportes.servicios',
            'reportes.repuestos',
            'reportes.vehiculos-clientes',
            'taller.especialidades',
            'taller.servicios',
        ] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }
}
