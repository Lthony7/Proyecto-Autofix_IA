<?php

namespace Src\Taller\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EspecialidadEloquentModel extends Model
{
    use HasUuids;
    protected $table = 'especialidades';
    protected $fillable = ['codigo', 'nombre', 'descripcion', 'estado', 'creado_por', 'actualizado_por'];

    public function mecanicos(): BelongsToMany
    {
        return $this->belongsToMany(MecanicoEloquentModel::class, 'mecanico_especialidad', 'especialidad_id', 'mecanico_id')->withPivot(['activo', 'asignado_en', 'asignado_por']);
    }

    public function servicios(): HasMany
    {
        return $this->hasMany(ServicioEloquentModel::class, 'especialidad_id');
    }
}
