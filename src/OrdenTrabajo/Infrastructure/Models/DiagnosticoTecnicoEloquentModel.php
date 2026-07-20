<?php
namespace Src\OrdenTrabajo\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
class DiagnosticoTecnicoEloquentModel extends Model { use HasUuids; public const UPDATED_AT=null; protected $table='diagnosticos_tecnicos'; protected $fillable=['orden_id','mecanico_id','version','diagnostico','pruebas_realizadas','recomendaciones','vigente','registrado_por']; protected function casts(): array { return ['vigente'=>'boolean']; } }
