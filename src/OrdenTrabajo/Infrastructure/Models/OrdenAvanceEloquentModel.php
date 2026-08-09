<?php

namespace Src\OrdenTrabajo\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Auth\Infrastructure\Models\UserEloquentModel;

class OrdenAvanceEloquentModel extends Model
{
    use HasUuids;

    public $timestamps = false;
    protected $table = 'orden_avances';
    protected $fillable = ['orden_id', 'servicio_id', 'tipo', 'descripcion', 'visibilidad', 'estado_orden', 'porcentaje', 'fecha_estimada_finalizacion', 'nota_interna', 'registrado_por'];
    protected function casts(): array { return ['created_at' => 'immutable_datetime', 'fecha_estimada_finalizacion' => 'immutable_datetime']; }
    protected static function booted(): void { static::updating(fn () => throw new \LogicException('Los avances son inmutables.')); static::deleting(fn () => throw new \LogicException('Los avances no se eliminan.')); }
    public function orden(): BelongsTo { return $this->belongsTo(OrdenTrabajoEloquentModel::class, 'orden_id'); }
    public function servicio(): BelongsTo { return $this->belongsTo(OrdenServicioEloquentModel::class, 'servicio_id'); }
    public function autor(): BelongsTo { return $this->belongsTo(UserEloquentModel::class, 'registrado_por'); }
}
