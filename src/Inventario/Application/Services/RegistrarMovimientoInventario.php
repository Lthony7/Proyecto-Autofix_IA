<?php
namespace Src\Inventario\Application\Services;
use Brick\Math\BigDecimal;use Brick\Math\RoundingMode;use Illuminate\Support\Facades\DB;use Illuminate\Validation\ValidationException;use Src\Inventario\Infrastructure\Models\MovimientoInventarioEloquentModel;use Src\Inventario\Infrastructure\Models\RepuestoEloquentModel;
class RegistrarMovimientoInventario
{
    public function registrar(string $repuestoId,string $cantidad,string $tipo,string $motivo,string $usuarioId,?string $ordenId=null,?string $costo=null,?string $origenId=null):MovimientoInventarioEloquentModel
    {
        return DB::transaction(function()use($repuestoId,$cantidad,$tipo,$motivo,$usuarioId,$ordenId,$costo,$origenId){$repuesto=RepuestoEloquentModel::whereKey($repuestoId)->lockForUpdate()->firstOrFail();$delta=BigDecimal::of($cantidad)->toScale(3,RoundingMode::HALF_UP);if($delta->isZero())throw ValidationException::withMessages(['cantidad'=>'La cantidad no puede ser cero.']);if($tipo==='entrada'&&$delta->isNegative())throw ValidationException::withMessages(['cantidad'=>'Una entrada debe ser positiva.']);if($tipo==='salida'&&!$delta->isNegative())throw ValidationException::withMessages(['cantidad'=>'Una salida debe ser negativa.']);$anterior=BigDecimal::of($repuesto->stock_actual)->toScale(3);$nuevo=$anterior->plus($delta);if($nuevo->isNegative())throw ValidationException::withMessages(['cantidad'=>'Stock insuficiente para completar el movimiento.']);$movimiento=MovimientoInventarioEloquentModel::create(['repuesto_id'=>$repuesto->id,'orden_id'=>$ordenId,'tipo'=>$tipo,'cantidad'=>(string)$delta,'stock_anterior'=>(string)$anterior,'stock_resultante'=>(string)$nuevo,'costo_unitario'=>$costo,'motivo'=>$motivo,'movimiento_origen_id'=>$origenId,'registrado_por'=>$usuarioId]);$repuesto->stock_actual=(string)$nuevo;$repuesto->actualizado_por=$usuarioId;$repuesto->save();return$movimiento;});
    }
    public function revertir(MovimientoInventarioEloquentModel $origen,string $motivo,string $usuarioId):MovimientoInventarioEloquentModel
    {
        return DB::transaction(function()use($origen,$motivo,$usuarioId){$bloqueado=MovimientoInventarioEloquentModel::whereKey($origen->id)->lockForUpdate()->firstOrFail();if(MovimientoInventarioEloquentModel::where('movimiento_origen_id',$bloqueado->id)->exists())throw ValidationException::withMessages(['movimiento'=>'Este movimiento ya fue revertido.']);$cantidad=(string)BigDecimal::of($bloqueado->cantidad)->negated();return$this->registrar($bloqueado->repuesto_id,$cantidad,'reversion',$motivo,$usuarioId,$bloqueado->orden_id,$bloqueado->costo_unitario,$bloqueado->id);});
    }
}
