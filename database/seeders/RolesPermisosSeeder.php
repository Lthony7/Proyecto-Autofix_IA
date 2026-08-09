<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Src\Auth\Infrastructure\Models\PermissionEloquentModel;
use Src\Auth\Infrastructure\Models\RoleEloquentModel;
use Src\Auth\Infrastructure\Models\UserEloquentModel;

class RolesPermisosSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permisos = [
            'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.desactivar', 'roles.administrar',
            'clientes.ver', 'clientes.crear', 'clientes.editar', 'clientes.desactivar',
            'vehiculos.ver', 'vehiculos.crear', 'vehiculos.editar', 'vehiculos.desactivar',
            'mecanicos.ver', 'mecanicos.gestionar', 'especialidades.gestionar', 'servicios.gestionar',
            'citas.ver', 'citas.crear', 'citas.gestionar', 'citas.cancelar',
            'ordenes.ver', 'ordenes.crear', 'ordenes.asignar', 'ordenes.avanzar', 'ordenes.entregar', 'ordenes.cancelar',
            'ordenes.ver_asignadas', 'ordenes.actualizar_estado', 'ordenes.cierre_tecnico', 'ordenes.administrar',
            'diagnosticos.registrar', 'diagnosticos.crear', 'diagnosticos.editar', 'diagnosticos.corregir',
            'avances.registrar', 'repuestos.solicitar', 'repuestos.aprobar', 'repuestos.utilizar',
            'servicios.registrar', 'servicios.aprobar', 'ia.solicitar', 'ia.revisar',
            'inventario.ver', 'inventario.gestionar', 'inventario.consumir',
            'pagos.ver', 'pagos.registrar', 'pagos.anular', 'pagos.reembolsar', 'pagos.enviar',
            'historial.ver', 'historial.servicios.ver', 'historial.acciones.ver', 'historial.tecnico.registrar', 'historial.finanzas.ver',
            'facturas.ver', 'facturas.crear', 'facturas.editar', 'facturas.enviar',
            'descuentos.autorizar',
            'reportes.ver', 'reportes.financieros', 'reportes.exportar',
            'auditorias.ver',
        ];

        foreach ($permisos as $permiso) {
            PermissionEloquentModel::findOrCreate($permiso, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $matriz = [
            'Administrador' => $permisos,
            'Recepcionista' => [
                'clientes.ver', 'clientes.crear', 'clientes.editar', 'clientes.desactivar',
                'vehiculos.ver', 'vehiculos.crear', 'vehiculos.editar',
                'citas.ver', 'citas.crear', 'citas.gestionar', 'citas.cancelar',
                'ordenes.ver', 'ordenes.crear', 'ordenes.asignar', 'ordenes.entregar', 'ordenes.cancelar',
                'repuestos.aprobar', 'servicios.aprobar',
                'ia.solicitar', 'inventario.ver', 'pagos.ver', 'pagos.registrar', 'pagos.enviar',
                'historial.ver', 'historial.servicios.ver', 'historial.acciones.ver', 'historial.finanzas.ver',
                'facturas.ver', 'facturas.crear', 'facturas.editar', 'facturas.enviar',
                'reportes.ver', 'reportes.financieros', 'reportes.exportar',
            ],
            'Mecánico' => [
                'ordenes.ver', 'ordenes.ver_asignadas', 'ordenes.avanzar', 'ordenes.actualizar_estado', 'ordenes.cierre_tecnico',
                'diagnosticos.registrar', 'diagnosticos.crear', 'diagnosticos.editar', 'diagnosticos.corregir',
                'avances.registrar', 'repuestos.solicitar', 'repuestos.utilizar', 'servicios.registrar',
                'ia.solicitar', 'ia.revisar',
                'inventario.ver', 'inventario.consumir', 'historial.ver', 'historial.servicios.ver', 'historial.tecnico.registrar',
                'reportes.ver',
            ],
            'Cliente' => ['vehiculos.ver', 'citas.ver', 'citas.crear', 'ordenes.ver', 'historial.ver', 'historial.servicios.ver', 'historial.finanzas.ver'],
        ];

        foreach ($matriz as $nombre => $permisosRol) {
            RoleEloquentModel::findOrCreate($nombre, 'web')->syncPermissions($permisosRol);
        }

        $adminEmail = env('ADMIN_EMAIL');
        $adminPassword = env('ADMIN_PASSWORD');
        if ($adminEmail && $adminPassword) {
            $admin = UserEloquentModel::firstOrCreate(
                ['email' => mb_strtolower(trim($adminEmail))],
                [
                    'name' => env('ADMIN_NAME', 'Administrador'),
                    'password' => Hash::make($adminPassword),
                    'activo' => true,
                ],
            );
            $admin->syncRoles(['Administrador']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
