<?php
namespace Src\OrdenTrabajo\Infrastructure\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Auth\Infrastructure\Models\UserEloquentModel;
class DiagnosticoTecnicoEloquentModel extends Model { use HasUuids; public const UPDATED_AT=null; protected $table='diagnosticos_tecnicos'; protected $fillable=['orden_id','mecanico_id','version','diagnostico','causa','componentes_afectados','severidad','resumen_cliente','pruebas_realizadas','recomendaciones','observaciones_tecnicas','indicaciones_seguridad','puede_circular','proximo_mantenimiento_en','notas_internas','estado','motivo_correccion','confirmado_en','reemplaza_id','vigente','registrado_por']; protected function casts(): array { return ['vigente'=>'boolean','confirmado_en'=>'immutable_datetime','proximo_mantenimiento_en'=>'date']; } public function scopeBorradorActual(Builder $query):Builder{return$query->where('estado','borrador')->where('vigente',true);} public function scopePublicadoActual(Builder $query):Builder{return$query->where('estado','confirmado')->where('vigente',true);} public function autor():BelongsTo{return $this->belongsTo(UserEloquentModel::class,'registrado_por');} protected static function booted():void{static::deleting(fn()=>throw new \LogicException('Los diagnósticos no se eliminan; se corrigen mediante una nueva versión.'));} }
