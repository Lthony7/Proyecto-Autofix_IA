<?php

namespace Src\Taller\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicioEloquentModel extends Model
{
    use HasUuids;
    protected $table = 'servicios_taller';
    protected $fillable = ['especialidad_id', 'codigo', 'nombre', 'descripcion', 'duracion_minutos', 'precio_base', 'estado', 'creado_por', 'actualizado_por'];
    protected function casts(): array { return ['precio_base' => 'decimal:2']; }
    public function especialidad(): BelongsTo { return $this->belongsTo(EspecialidadEloquentModel::class, 'especialidad_id'); }
}
