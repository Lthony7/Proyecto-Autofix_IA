<?php
namespace Src\OrdenTrabajo\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Taller\Infrastructure\Models\MecanicoEloquentModel;
class OrdenMecanicoEloquentModel extends Model { use HasUuids; public $timestamps = false; protected $table='orden_mecanicos'; protected $fillable=['orden_id','mecanico_id','activo','asignado_en','retirado_en','asignado_por','observaciones']; protected function casts(): array { return ['activo'=>'boolean','asignado_en'=>'immutable_datetime','retirado_en'=>'immutable_datetime']; } public function mecanico(): BelongsTo { return $this->belongsTo(MecanicoEloquentModel::class,'mecanico_id'); } }
