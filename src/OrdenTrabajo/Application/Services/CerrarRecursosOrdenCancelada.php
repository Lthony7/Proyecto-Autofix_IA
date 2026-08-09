<?php

namespace Src\OrdenTrabajo\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenRepuestoRequeridoEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenServicioEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel;

class CerrarRecursosOrdenCancelada
{
    public function ejecutar(OrdenTrabajoEloquentModel $orden, string $usuarioId): void
    {
        $servicios = OrdenServicioEloquentModel::where('orden_id', $orden->id)
            ->whereIn('estado', ['pendiente', 'en_proceso'])->lockForUpdate()->get();
        foreach ($servicios as $servicio) {
            $anterior = $servicio->estado;
            $servicio->update([
                'estado' => 'cancelado',
                'aprobacion_estado' => $servicio->aprobacion_estado === 'pendiente_aprobacion' ? 'rechazado' : $servicio->aprobacion_estado,
                'observaciones' => trim(($servicio->observaciones ? $servicio->observaciones."\n" : '').'Cancelado automáticamente con la orden.'),
            ]);
            DB::table('orden_servicio_historial')->insert([
                'id' => (string) Str::uuid(), 'orden_servicio_id' => $servicio->id,
                'estado_anterior' => $anterior, 'estado_nuevo' => 'cancelado',
                'detalle' => 'Cancelado automáticamente con la orden.', 'usuario_id' => $usuarioId, 'created_at' => now(),
            ]);
        }

        $requerimientos = OrdenRepuestoRequeridoEloquentModel::where('orden_id', $orden->id)
            ->whereIn('estado', ['pendiente_aprobacion', 'aprobado', 'no_disponible'])->lockForUpdate()->get();
        foreach ($requerimientos as $requerimiento) {
            $anterior = $requerimiento->estado;
            $requerimiento->update([
                'estado' => 'cancelado', 'retirado_en' => now(), 'retirado_por' => $usuarioId,
                'motivo_retiro' => 'Cancelado automáticamente con la orden.', 'actualizado_por' => $usuarioId,
            ]);
            DB::table('orden_repuesto_requerido_historial')->insert([
                'id' => (string) Str::uuid(), 'requerimiento_id' => $requerimiento->id,
                'estado_anterior' => $anterior, 'estado_nuevo' => 'cancelado', 'cantidad' => $requerimiento->cantidad,
                'motivo' => 'Cancelado automáticamente con la orden.', 'usuario_id' => $usuarioId, 'created_at' => now(),
            ]);
        }

        $orden->asignaciones()->where('activo', true)->update(['activo' => false, 'retirado_en' => now()]);
    }
}
