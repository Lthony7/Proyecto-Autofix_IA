<?php
namespace Src\AsistenteIA\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;
class ConsumoIaEloquentModel extends Model{use HasUuids;public $timestamps=false;protected $table='consumos_ia';protected $fillable=['consulta_id','usuario_id','proveedor','proveedor_intentado','modelo','modelo_intentado','resultado','latencia_ms','tokens_entrada','tokens_salida','codigo_error','meta'];protected function casts():array{return['meta'=>'array','created_at'=>'immutable_datetime'];}}
