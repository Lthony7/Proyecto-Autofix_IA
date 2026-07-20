<?php
namespace Src\Pago\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PagoHistorialEloquentModel extends Model{use HasUuids;public $timestamps=false;protected $table='pago_historial';protected $fillable=['pago_id','evento','monto','datos','usuario_id'];protected function casts():array{return['monto'=>'decimal:2','datos'=>'array','created_at'=>'immutable_datetime'];}public function pago():BelongsTo{return$this->belongsTo(PagoEloquentModel::class,'pago_id');}}
