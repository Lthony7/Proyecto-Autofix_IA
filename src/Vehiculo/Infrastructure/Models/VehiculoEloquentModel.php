<?php

namespace Src\Vehiculo\Infrastructure\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\Auth\Infrastructure\Models\UserEloquentModel;
use Src\Cliente\Infrastructure\Models\ClienteEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel;

class VehiculoEloquentModel extends Model
{
    use HasUuid;

    protected $table = 'vehiculos';

    protected $fillable = [
        'cliente_id', 'placa', 'placa_normalizada', 'marca', 'modelo', 'anio', 'color',
        'kilometraje', 'combustible', 'observaciones', 'estado', 'creado_por', 'actualizado_por',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(ClienteEloquentModel::class, 'cliente_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(UserEloquentModel::class, 'creado_por');
    }

    public function ordenes(): HasMany
    {
        return $this->hasMany(OrdenTrabajoEloquentModel::class, 'vehiculo_id');
    }

    public function scopeVisiblePara(Builder $query, UserEloquentModel $usuario): Builder
    {
        if ($usuario->hasRole('Cliente')) {
            return $query->whereHas('cliente', fn (Builder $q) => $q->where('usuario_id', $usuario->id));
        }

        return $query;
    }
}
