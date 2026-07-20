<?php

namespace Src\Taller\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\Auth\Infrastructure\Models\UserEloquentModel;

class MecanicoEloquentModel extends Model
{
    use HasUuids;
    protected $table = 'mecanicos';
    protected $fillable = ['usuario_id', 'tipo_documento', 'numero_documento', 'nombres', 'apellidos', 'telefono', 'email', 'fecha_ingreso', 'estado', 'creado_por', 'actualizado_por'];
    protected function casts(): array { return ['fecha_ingreso' => 'date']; }
    public function usuario(): BelongsTo { return $this->belongsTo(UserEloquentModel::class, 'usuario_id'); }
    public function especialidades(): BelongsToMany { return $this->belongsToMany(EspecialidadEloquentModel::class, 'mecanico_especialidad', 'mecanico_id', 'especialidad_id')->withPivot(['activo', 'asignado_en', 'asignado_por']); }
    public function disponibilidades(): HasMany { return $this->hasMany(DisponibilidadMecanicoEloquentModel::class, 'mecanico_id'); }
}
