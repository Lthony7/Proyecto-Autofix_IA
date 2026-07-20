<?php
namespace Src\Inventario\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
class OrdenRepuestoEloquentModel extends Model{use HasUuids;public $timestamps=false;protected $table='orden_repuestos';protected $fillable=['orden_id','repuesto_id','cantidad','precio_unitario','movimiento_salida_id','revertido_en','revertido_por','registrado_por'];protected function casts():array{return['cantidad'=>'decimal:3','precio_unitario'=>'decimal:2','revertido_en'=>'immutable_datetime','created_at'=>'immutable_datetime'];}public function repuesto():BelongsTo{return$this->belongsTo(RepuestoEloquentModel::class,'repuesto_id');}}
