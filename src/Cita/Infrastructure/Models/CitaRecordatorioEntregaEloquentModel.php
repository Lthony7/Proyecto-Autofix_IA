<?php

declare(strict_types=1);

namespace Src\Cita\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CitaRecordatorioEntregaEloquentModel extends Model
{
    use HasUuids;

    protected $table = 'cita_recordatorio_entregas';

    protected $fillable = [
        'cita_id',
        'inicio_programado',
        'canal',
        'destinatario',
        'encolado_en',
        'intentado_en',
        'invalidado_en',
    ];

    protected function casts(): array
    {
        return [
            'inicio_programado' => 'immutable_datetime',
            'encolado_en' => 'immutable_datetime',
            'intentado_en' => 'immutable_datetime',
            'invalidado_en' => 'immutable_datetime',
        ];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(CitaEloquentModel::class, 'cita_id');
    }
}
