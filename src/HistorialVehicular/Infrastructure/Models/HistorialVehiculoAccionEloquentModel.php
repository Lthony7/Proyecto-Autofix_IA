<?php

namespace Src\HistorialVehicular\Infrastructure\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Src\Auth\Infrastructure\Models\UserEloquentModel;
use Src\Vehiculo\Infrastructure\Models\VehiculoEloquentModel;

class HistorialVehiculoAccionEloquentModel extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected $table = 'historial_vehiculo_acciones';

    protected $fillable = [
        'vehiculo_id', 'usuario_id', 'rol', 'accion', 'descripcion', 'cambios', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['cambios' => 'array', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('La bitácora vehicular es inalterable.'));
        static::deleting(fn () => throw new LogicException('La bitácora vehicular es inalterable.'));
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(VehiculoEloquentModel::class, 'vehiculo_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(UserEloquentModel::class, 'usuario_id');
    }
}
