<?php
namespace Src\OrdenTrabajo\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class OrdenServicioEloquentModel extends Model { use HasUuids; protected $table = 'orden_servicios'; protected $fillable = ['orden_id','servicio_id','nombre_servicio','precio_acordado','estado','observaciones','origen','tipo_trabajo','aprobacion_estado','aprobado_en','aprobado_por','trabajo_realizado','agregado_por','iniciado_en','iniciado_por','tiempo_trabajado_minutos','resultado_prueba','observaciones_posteriores','recomendaciones_cliente','completado_en','completado_por']; protected function casts(): array { return ['precio_acordado'=>'decimal:2','aprobado_en'=>'immutable_datetime','iniciado_en'=>'immutable_datetime','completado_en'=>'immutable_datetime']; } }
