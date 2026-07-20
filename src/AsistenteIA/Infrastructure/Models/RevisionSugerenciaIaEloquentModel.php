<?php
namespace Src\AsistenteIA\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;
class RevisionSugerenciaIaEloquentModel extends Model{use HasUuids;public $timestamps=false;protected $table='revisiones_sugerencia_ia';protected $fillable=['consulta_id','estado_anterior','estado_nuevo','respuesta_ajustada','observaciones','mecanico_id','revisada_por'];protected function casts():array{return['respuesta_ajustada'=>'array','created_at'=>'immutable_datetime'];}}
