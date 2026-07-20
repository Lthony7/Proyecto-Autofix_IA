<?php
namespace Src\OrdenTrabajo\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class OrdenEstadoHistorialEloquentModel extends Model { use HasUuids; public const UPDATED_AT=null; protected $table='orden_estado_historial'; protected $fillable=['orden_id','estado_anterior','estado_nuevo','observaciones','usuario_id']; }
