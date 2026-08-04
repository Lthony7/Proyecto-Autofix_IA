<?php

namespace Src\Cita\Infrastructure\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Src\Auth\Infrastructure\Models\UserEloquentModel;
use Src\Cliente\Infrastructure\Models\ClienteEloquentModel;
use Src\Taller\Infrastructure\Models\EspecialidadEloquentModel;
use Src\Taller\Infrastructure\Models\MecanicoEloquentModel;
use Src\Taller\Infrastructure\Models\ServicioEloquentModel;
use Src\Vehiculo\Infrastructure\Models\VehiculoEloquentModel;

class CitaEloquentModel extends Model
{
    use HasUuids;
    protected $table = 'citas';
    protected $fillable = ['numero', 'cliente_id', 'vehiculo_id', 'especialidad_id', 'servicio_id', 'mecanico_id', 'motivo', 'kilometraje', 'inicio', 'fin', 'estado', 'motivo_cancelacion', 'cancelada_en', 'cancelada_por', 'creado_por', 'actualizado_por'];
    protected function casts(): array { return ['inicio' => 'immutable_datetime', 'fin' => 'immutable_datetime', 'cancelada_en' => 'immutable_datetime']; }
    public function cliente(): BelongsTo { return $this->belongsTo(ClienteEloquentModel::class, 'cliente_id'); }
    public function vehiculo(): BelongsTo { return $this->belongsTo(VehiculoEloquentModel::class, 'vehiculo_id'); }
    public function especialidad(): BelongsTo { return $this->belongsTo(EspecialidadEloquentModel::class, 'especialidad_id'); }
    public function servicio(): BelongsTo { return $this->belongsTo(ServicioEloquentModel::class, 'servicio_id'); }
    public function mecanico(): BelongsTo { return $this->belongsTo(MecanicoEloquentModel::class, 'mecanico_id'); }
    public function historial(): HasMany { return $this->hasMany(CitaEstadoHistorialEloquentModel::class, 'cita_id'); }
    public function orden(): HasOne { return $this->hasOne(\Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel::class, 'cita_id'); }

    public function scopeVisiblePara(Builder $query, UserEloquentModel $usuario): Builder
    {
        if ($usuario->hasRole('Cliente')) return $query->whereHas('cliente', fn (Builder $q) => $q->where('usuario_id', $usuario->id));
        if ($usuario->hasRole('Mecánico')) return $query->whereHas('mecanico', fn (Builder $q) => $q->where('usuario_id', $usuario->id));
        return $query;
    }
}
