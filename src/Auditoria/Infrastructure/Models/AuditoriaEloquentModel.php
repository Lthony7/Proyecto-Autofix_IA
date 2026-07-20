<?php

namespace Src\Auditoria\Infrastructure\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Auth\Infrastructure\Models\UserEloquentModel;

class AuditoriaEloquentModel extends Model
{
    use HasUuid;

    public const UPDATED_AT = null;

    protected $table = 'auditorias';

    protected $fillable = [
        'usuario_id', 'accion', 'recurso_tipo', 'recurso_id', 'cambios', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['cambios' => 'array', 'created_at' => 'immutable_datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(UserEloquentModel::class, 'usuario_id');
    }
}
