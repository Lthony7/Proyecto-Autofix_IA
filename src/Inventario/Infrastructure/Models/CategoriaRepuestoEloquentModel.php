<?php
namespace Src\Inventario\Infrastructure\Models;use Illuminate\Database\Eloquent\Concerns\HasUuids;use Illuminate\Database\Eloquent\Model;
class CategoriaRepuestoEloquentModel extends Model{use HasUuids;protected $table='categorias_repuesto';protected $fillable=['nombre','descripcion','estado','creado_por','actualizado_por'];}
