<?php

namespace Src\Pago\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PagoMovimientoEloquentModel extends Model
{
    use HasUuids;
    public $timestamps = false;
    protected $table = 'pago_movimientos';
    protected $fillable = ['pago_id', 'orden_id', 'tipo', 'monto', 'moneda', 'metodo', 'referencia', 'ocurrido_en', 'registrado_por', 'metadata'];
    protected function casts(): array { return ['monto' => 'decimal:2', 'ocurrido_en' => 'immutable_datetime', 'metadata' => 'array', 'created_at' => 'immutable_datetime']; }
}
