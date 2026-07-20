<?php
namespace Src\Inventario\Infrastructure\Models;use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;
class ProveedorEloquentModel extends Model{use HasUuids;protected $table='proveedores';protected $fillable=['documento','nombre','contacto','telefono','email','estado','creado_por','actualizado_por'];}
