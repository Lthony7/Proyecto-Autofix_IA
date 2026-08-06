<?php
namespace Src\OrdenTrabajo\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class OrdenServicioEloquentModel extends Model { use HasUuids; protected $table = 'orden_servicios'; protected $fillable = ['orden_id','servicio_id','nombre_servicio','precio_acordado','estado','observaciones','origen','trabajo_realizado','agregado_por','completado_en','completado_por']; protected function casts(): array { return ['precio_acordado'=>'decimal:2','completado_en'=>'immutable_datetime']; } }
