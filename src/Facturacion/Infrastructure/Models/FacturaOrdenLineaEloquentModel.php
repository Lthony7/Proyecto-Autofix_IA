<?php
namespace Src\Facturacion\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
class FacturaOrdenLineaEloquentModel extends Model{use HasUuids;public $timestamps=false;protected $table='factura_orden_lineas';protected $fillable=['factura_id','tipo','referencia_id','codigo','descripcion','cantidad','precio_unitario','subtotal'];protected function casts():array{return['cantidad'=>'decimal:3','precio_unitario'=>'decimal:2','subtotal'=>'decimal:2','created_at'=>'immutable_datetime'];}public function factura():BelongsTo{return$this->belongsTo(FacturaOrdenEloquentModel::class,'factura_id');}}
