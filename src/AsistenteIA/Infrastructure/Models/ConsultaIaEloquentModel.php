<?php
namespace Src\AsistenteIA\Infrastructure\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\Auth\Infrastructure\Models\UserEloquentModel;
use Src\Cliente\Infrastructure\Models\ClienteEloquentModel;
use Src\Taller\Infrastructure\Models\EspecialidadEloquentModel;
use Src\Vehiculo\Infrastructure\Models\VehiculoEloquentModel;
class ConsultaIaEloquentModel extends Model
{
    use HasUuids; protected $table='consultas_ia'; protected $fillable=['cliente_id','vehiculo_id','cita_id','orden_id','solicitada_por','entrada','entrada_hash','respuesta_original','proveedor','modelo','simulada','estado','prioridad','especialidad_sugerida_id','reutilizada_de_id'];
    protected function casts():array{return['entrada'=>'array','respuesta_original'=>'array','simulada'=>'boolean'];}
    public function cliente():BelongsTo{return $this->belongsTo(ClienteEloquentModel::class,'cliente_id');} public function vehiculo():BelongsTo{return $this->belongsTo(VehiculoEloquentModel::class,'vehiculo_id');} public function especialidad():BelongsTo{return $this->belongsTo(EspecialidadEloquentModel::class,'especialidad_sugerida_id');} public function revisiones():HasMany{return $this->hasMany(RevisionSugerenciaIaEloquentModel::class,'consulta_id');}
    public function scopeVisiblePara(Builder$q,UserEloquentModel$u):Builder{if($u->hasRole('Cliente'))return$q->whereHas('cliente',fn(Builder$c)=>$c->where('usuario_id',$u->id));if($u->hasRole('Mecánico'))return$q->where(function($x)use($u){$x->where('solicitada_por',$u->id)->orWhereHas('orden.asignaciones',fn(Builder$a)=>$a->where('activo',true)->whereHas('mecanico',fn(Builder$m)=>$m->where('usuario_id',$u->id)))->orWhereHas('cita.mecanico',fn(Builder$m)=>$m->where('usuario_id',$u->id));});return$q;}
    public function orden():BelongsTo{return $this->belongsTo(\Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel::class,'orden_id');} public function cita():BelongsTo{return $this->belongsTo(\Src\Cita\Infrastructure\Models\CitaEloquentModel::class,'cita_id');}
}
