<?php

namespace Src\Cita\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CitaEstadoHistorialEloquentModel extends Model
{
    use HasUuids;
    public const UPDATED_AT = null;
    protected $table = 'cita_estado_historial';
    protected $fillable = ['cita_id', 'estado_anterior', 'estado_nuevo', 'observaciones', 'datos_anteriores', 'usuario_id'];
    protected function casts(): array { return ['datos_anteriores' => 'array', 'created_at' => 'immutable_datetime']; }
}
