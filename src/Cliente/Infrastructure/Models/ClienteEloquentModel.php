<?php

namespace Src\Cliente\Infrastructure\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\Factura\Infrastructure\Models\FacturaEloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Auth\Infrastructure\Models\UserEloquentModel;
use Src\Vehiculo\Infrastructure\Models\VehiculoEloquentModel;

class ClienteEloquentModel extends Model
{
    use HasUuid;

    protected $table = 'clientes';

    protected $fillable = [
        'tipo_documento',
        'numero_documento',
        'razon_social',
        'direccion',
        'telefono',
        'email',
        'usuario_id',
        'estado',
        'creado_por',
        'actualizado_por',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function facturas(): HasMany
    {
        return $this->hasMany(FacturaEloquentModel::class, 'cliente_id', 'id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(UserEloquentModel::class, 'usuario_id');
    }

    public function vehiculos(): HasMany
    {
        return $this->hasMany(VehiculoEloquentModel::class, 'cliente_id');
    }
}
