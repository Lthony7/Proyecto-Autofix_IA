<?php
namespace Src\OrdenTrabajo\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
class OrdenRepuestoRequeridoEloquentModel extends Model{use HasUuids;protected $table='orden_repuestos_requeridos';protected $fillable=['orden_id','repuesto_id','solicitud_cita_id','origen','descripcion','cantidad','motivo','estado','agregado_por','retirado_en','retirado_por','motivo_retiro'];protected function casts():array{return['cantidad'=>'decimal:3','retirado_en'=>'immutable_datetime'];}public function orden():BelongsTo{return$this->belongsTo(OrdenTrabajoEloquentModel::class,'orden_id');}}
