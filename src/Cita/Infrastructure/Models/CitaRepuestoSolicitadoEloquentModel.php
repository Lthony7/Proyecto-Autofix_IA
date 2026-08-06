<?php
namespace Src\Cita\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CitaRepuestoSolicitadoEloquentModel extends Model{use HasUuids;protected $table='cita_repuestos_solicitados';protected $fillable=['cita_id','repuesto_id','descripcion','cantidad','observaciones','solicitado_por'];protected function casts():array{return['cantidad'=>'decimal:3'];}public function cita():BelongsTo{return$this->belongsTo(CitaEloquentModel::class,'cita_id');}}
