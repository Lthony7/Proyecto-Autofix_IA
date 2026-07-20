<?php

namespace Src\Taller\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DisponibilidadMecanicoEloquentModel extends Model
{
    use HasUuids;
    protected $table = 'disponibilidades_mecanico';
    protected $fillable = ['mecanico_id', 'dia_semana', 'hora_inicio', 'hora_fin', 'activo', 'vigente_desde', 'vigente_hasta', 'creado_por'];
    protected function casts(): array { return ['activo' => 'boolean', 'vigente_desde' => 'date', 'vigente_hasta' => 'date']; }
}
