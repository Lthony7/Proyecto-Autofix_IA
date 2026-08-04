<?php
namespace Src\AsistenteIA\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;
class RevisionSugerenciaIaEloquentModel extends Model{use HasUuids;public $timestamps=false;protected $table='revisiones_sugerencia_ia';protected $fillable=['consulta_id','version','estado_anterior','estado_nuevo','coincide_ia','respuesta_ajustada','observaciones','observaciones_cliente','notas_internas','motivo_diferencia','pruebas_realizadas','mecanico_id','revisada_por'];protected function casts():array{return['coincide_ia'=>'boolean','respuesta_ajustada'=>'array','pruebas_realizadas'=>'array','created_at'=>'immutable_datetime'];}}
