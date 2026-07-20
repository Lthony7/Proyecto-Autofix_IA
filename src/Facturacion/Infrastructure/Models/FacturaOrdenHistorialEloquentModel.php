<?php
namespace Src\Facturacion\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;
class FacturaOrdenHistorialEloquentModel extends Model{use HasUuids;public $timestamps=false;protected $table='factura_orden_historial';protected $fillable=['factura_id','evento','datos','usuario_id'];protected function casts():array{return['datos'=>'array','created_at'=>'immutable_datetime'];}}
