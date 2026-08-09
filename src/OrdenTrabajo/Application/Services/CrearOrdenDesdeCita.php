<?php

namespace Src\OrdenTrabajo\Application\Services;

use Src\AsistenteIA\Infrastructure\Models\ConsultaIaEloquentModel;
use Src\Cita\Infrastructure\Models\CitaEloquentModel;
use Src\Inventario\Infrastructure\Models\RepuestoEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenEstadoHistorialEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenMecanicoEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenRepuestoRequeridoEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenServicioEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel;

class CrearOrdenDesdeCita
{
    public function crear(CitaEloquentModel $cita, string $usuarioId): OrdenTrabajoEloquentModel
    {
        $existente = OrdenTrabajoEloquentModel::where('cita_id', $cita->id)->first();
        if ($existente) return $existente;

        $cita->loadMissing(['servicio', 'repuestosSolicitados']);
        $orden = OrdenTrabajoEloquentModel::create([
            'numero' => 'OT-'.now()->format('Ymd').'-'.mb_strtoupper(substr(str_replace('-', '', (string) str()->uuid()), 0, 6)),
            'cita_id' => $cita->id,
            'cliente_id' => $cita->cliente_id,
            'vehiculo_id' => $cita->vehiculo_id,
            'falla_reportada' => $cita->motivo,
            'kilometraje' => $cita->kilometraje,
            'estado' => 'pendiente',
            'recibida_en' => $cita->inicio,
            'creado_por' => $usuarioId,
            'actualizado_por' => $usuarioId,
        ]);
        if ($cita->servicio) OrdenServicioEloquentModel::create(['orden_id' => $orden->id, 'servicio_id' => $cita->servicio_id, 'nombre_servicio' => $cita->servicio->nombre, 'precio_acordado' => $cita->servicio->precio_base, 'estado' => 'pendiente', 'origen' => 'cita', 'tipo_trabajo' => 'solicitado', 'aprobacion_estado' => 'aprobado', 'agregado_por' => $usuarioId]);
        foreach ($cita->repuestosSolicitados as $solicitud) { $repuesto = $solicitud->repuesto_id ? RepuestoEloquentModel::find($solicitud->repuesto_id) : null; OrdenRepuestoRequeridoEloquentModel::create(['orden_id' => $orden->id, 'repuesto_id' => $solicitud->repuesto_id, 'solicitud_cita_id' => $solicitud->id, 'origen' => 'cita', 'descripcion' => $solicitud->descripcion, 'cantidad' => $solicitud->cantidad, 'motivo' => $solicitud->observaciones ?: 'Solicitado por el cliente al agendar.', 'estado' => 'pendiente_aprobacion', 'prioridad' => 'media', 'obligatorio' => false, 'fuente_suministro' => $repuesto ? 'inventario' : 'externo', 'unidad_snapshot' => $repuesto?->unidad ?: 'unidad', 'agregado_por' => $usuarioId, 'actualizado_por' => $usuarioId]); }
        if ($cita->mecanico_id) OrdenMecanicoEloquentModel::create(['orden_id' => $orden->id, 'mecanico_id' => $cita->mecanico_id, 'activo' => true, 'asignado_por' => $usuarioId, 'observaciones' => 'Asignado automáticamente desde la cita.']);
        OrdenEstadoHistorialEloquentModel::create(['orden_id' => $orden->id, 'estado_nuevo' => 'pendiente', 'observaciones' => 'Orden creada automáticamente al agendar la cita.', 'usuario_id' => $usuarioId]);
        $consultas = ConsultaIaEloquentModel::with('revisiones')->where('cita_id', $cita->id)->whereNotIn('estado', ['descartada', 'cerrada'])->get();
        foreach ($consultas as $consulta) {
            $consulta->update(['orden_id' => $orden->id]);
            $respuesta = $consulta->revisiones->where('estado_nuevo', 'modificada')->last()?->respuesta_ajustada ?: $consulta->respuesta_original;
            foreach (($respuesta['repuestos_posibles'] ?? []) as $recomendacion) {
                $nombre = trim((string) ($recomendacion['nombre'] ?? ''));
                if ($nombre === '') continue;
                $repuesto = RepuestoEloquentModel::where('estado', 'activo')->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first();
                $duplicado = OrdenRepuestoRequeridoEloquentModel::where('orden_id', $orden->id)->where('estado', '<>', 'cancelado')->where(function ($query) use ($repuesto, $nombre) {
                    if ($repuesto) $query->where('repuesto_id', $repuesto->id);
                    else $query->whereRaw('LOWER(descripcion) = ?', [mb_strtolower($nombre)]);
                })->exists();
                if ($duplicado) continue;
                $probabilidad = trim((string) ($recomendacion['probabilidad_o_nivel'] ?? ''));
                $motivo = $recomendacion['motivo'] ?? 'Recomendación preliminar de IA pendiente de confirmación.';
                if ($probabilidad !== '') $motivo .= " Probabilidad estimada por IA: {$probabilidad}.";
                OrdenRepuestoRequeridoEloquentModel::create(['orden_id' => $orden->id, 'repuesto_id' => $repuesto?->id, 'origen' => 'ia', 'descripcion' => $nombre, 'cantidad' => $recomendacion['cantidad'] ?? 1, 'motivo' => $motivo, 'estado' => 'pendiente_aprobacion', 'prioridad' => 'baja', 'obligatorio' => false, 'fuente_suministro' => $repuesto ? 'inventario' : 'externo', 'unidad_snapshot' => $repuesto?->unidad ?: 'unidad', 'agregado_por' => $usuarioId, 'actualizado_por' => $usuarioId]);
            }
        }
        return $orden;
    }
}
