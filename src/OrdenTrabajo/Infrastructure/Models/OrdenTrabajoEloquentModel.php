<?php

namespace Src\OrdenTrabajo\Infrastructure\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\Auth\Infrastructure\Models\UserEloquentModel;
use Src\Cita\Infrastructure\Models\CitaEloquentModel;
use Src\Cliente\Infrastructure\Models\ClienteEloquentModel;
use Src\Vehiculo\Infrastructure\Models\VehiculoEloquentModel;

class OrdenTrabajoEloquentModel extends Model
{
    use HasUuids;
    protected $table = 'ordenes_trabajo';
    protected $fillable = ['numero', 'cita_id', 'cliente_id', 'vehiculo_id', 'falla_reportada', 'kilometraje', 'estado', 'estado_anterior_pausa', 'recibida_en', 'fecha_estimada_finalizacion', 'finalizada_en', 'entregada_en', 'observaciones_entrega', 'proximo_mantenimiento_en', 'motivo_cancelacion', 'cancelada_en', 'cancelada_por', 'tiempo_trabajado_minutos', 'bloqueos_tecnicos', 'control_calidad_estado', 'control_calidad_notas', 'prueba_ruta_estado', 'prueba_ruta_notas', 'cierre_tecnico_actualizado_en', 'cierre_tecnico_actualizado_por', 'creado_por', 'actualizado_por'];
    protected function casts(): array { return ['recibida_en' => 'immutable_datetime', 'fecha_estimada_finalizacion' => 'immutable_datetime', 'finalizada_en' => 'immutable_datetime', 'entregada_en' => 'immutable_datetime', 'proximo_mantenimiento_en' => 'date', 'cancelada_en' => 'immutable_datetime', 'cierre_tecnico_actualizado_en' => 'immutable_datetime']; }
    public function cita(): BelongsTo { return $this->belongsTo(CitaEloquentModel::class, 'cita_id'); }
    public function cliente(): BelongsTo { return $this->belongsTo(ClienteEloquentModel::class, 'cliente_id'); }
    public function vehiculo(): BelongsTo { return $this->belongsTo(VehiculoEloquentModel::class, 'vehiculo_id'); }
    public function servicios(): HasMany { return $this->hasMany(OrdenServicioEloquentModel::class, 'orden_id'); }
    public function asignaciones(): HasMany { return $this->hasMany(OrdenMecanicoEloquentModel::class, 'orden_id'); }
    public function diagnosticos(): HasMany { return $this->hasMany(DiagnosticoTecnicoEloquentModel::class, 'orden_id'); }
    public function repuestosRequeridos(): HasMany { return $this->hasMany(OrdenRepuestoRequeridoEloquentModel::class, 'orden_id'); }
    public function avances(): HasMany { return $this->hasMany(OrdenAvanceEloquentModel::class, 'orden_id'); }
    public function historialEstados(): HasMany { return $this->hasMany(OrdenEstadoHistorialEloquentModel::class, 'orden_id'); }
    public function scopeYaRecibidas(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('cita_id')->orWhere('recibida_en', '<=', now()));
    }
    public function scopeVisiblePara(Builder $query, UserEloquentModel $usuario): Builder
    {
        if ($usuario->can('ordenes.administrar') || $usuario->hasAnyPermission(['ordenes.crear', 'ordenes.asignar', 'ordenes.entregar', 'ordenes.cancelar'])) return $query;
        if ($usuario->can('ordenes.ver_asignadas')) return $query->whereHas('asignaciones', fn (Builder $q) => $q->where('activo', true)->whereHas('mecanico', fn (Builder $m) => $m->where('usuario_id', $usuario->id)->where('estado', 'activo')));
        if ($usuario->can('ordenes.ver')) return $query->whereHas('cliente', fn (Builder $q) => $q->where('usuario_id', $usuario->id));

        return $query->whereRaw('1 = 0');
    }
}
