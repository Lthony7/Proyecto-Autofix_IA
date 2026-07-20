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
    protected $fillable = ['numero', 'cita_id', 'cliente_id', 'vehiculo_id', 'falla_reportada', 'kilometraje', 'estado', 'recibida_en', 'finalizada_en', 'entregada_en', 'motivo_cancelacion', 'cancelada_en', 'cancelada_por', 'creado_por', 'actualizado_por'];
    protected function casts(): array { return ['recibida_en' => 'immutable_datetime', 'finalizada_en' => 'immutable_datetime', 'entregada_en' => 'immutable_datetime', 'cancelada_en' => 'immutable_datetime']; }
    public function cita(): BelongsTo { return $this->belongsTo(CitaEloquentModel::class, 'cita_id'); }
    public function cliente(): BelongsTo { return $this->belongsTo(ClienteEloquentModel::class, 'cliente_id'); }
    public function vehiculo(): BelongsTo { return $this->belongsTo(VehiculoEloquentModel::class, 'vehiculo_id'); }
    public function servicios(): HasMany { return $this->hasMany(OrdenServicioEloquentModel::class, 'orden_id'); }
    public function asignaciones(): HasMany { return $this->hasMany(OrdenMecanicoEloquentModel::class, 'orden_id'); }
    public function diagnosticos(): HasMany { return $this->hasMany(DiagnosticoTecnicoEloquentModel::class, 'orden_id'); }
    public function scopeVisiblePara(Builder $query, UserEloquentModel $usuario): Builder
    {
        if ($usuario->hasRole('Cliente')) return $query->whereHas('cliente', fn (Builder $q) => $q->where('usuario_id', $usuario->id));
        if ($usuario->hasRole('Mecánico')) return $query->whereHas('asignaciones', fn (Builder $q) => $q->where('activo', true)->whereHas('mecanico', fn (Builder $m) => $m->where('usuario_id', $usuario->id)));
        return $query;
    }
}
