<?php
namespace Src\Pago\Application\Services;
use Brick\Math\BigDecimal;use Brick\Math\RoundingMode;use Illuminate\Support\Facades\DB;use Illuminate\Validation\ValidationException;use Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel;use Src\Pago\Infrastructure\Models\PagoEloquentModel;use Src\Pago\Infrastructure\Models\PagoHistorialEloquentModel;
class GestionarPago
{
    public function __construct(private readonly CalculadorTotalOrden$calculador){}
    public function registrar(OrdenTrabajoEloquentModel$orden,array$datos,string$usuarioId):PagoEloquentModel
    {
        return DB::transaction(function()use($orden,$datos,$usuarioId){$bloqueada=OrdenTrabajoEloquentModel::whereKey($orden->id)->lockForUpdate()->firstOrFail();if($bloqueada->estado==='cancelada')throw ValidationException::withMessages(['orden'=>'No se pueden registrar pagos en una orden cancelada.']);$resumen=$this->calculador->calcular($bloqueada->id);$monto=BigDecimal::of((string)$datos['monto'])->toScale(2,RoundingMode::HALF_UP);if($monto->isLessThanOrEqualTo(0))throw ValidationException::withMessages(['monto'=>'El monto debe ser mayor que cero.']);if($monto->isGreaterThan(BigDecimal::of($resumen['saldo'])))throw ValidationException::withMessages(['monto'=>'El monto supera el saldo pendiente de la orden.']);if(BigDecimal::of($resumen['saldo'])->isZero())throw ValidationException::withMessages(['monto'=>'La orden ya está pagada.']);$sufijo=mb_strtoupper(substr(str_replace('-','',(string)str()->uuid()),0,8));$pago=PagoEloquentModel::create(['numero'=>'PG-'.now()->format('Ymd').'-'.$sufijo,'comprobante_numero'=>'RC-'.now()->format('Ymd').'-'.$sufijo,'orden_id'=>$bloqueada->id,'monto'=>(string)$monto,'moneda'=>'COP','metodo'=>$datos['metodo'],'referencia'=>$datos['referencia']??null,'observaciones'=>$datos['observaciones']??null,'estado'=>'registrado','pagado_en'=>$datos['pagado_en'],'registrado_por'=>$usuarioId]);PagoHistorialEloquentModel::create(['pago_id'=>$pago->id,'evento'=>'registrado','monto'=>$pago->monto,'datos'=>['metodo'=>$pago->metodo,'referencia'=>$pago->referencia],'usuario_id'=>$usuarioId]);return$pago;});
    }
    public function anular(PagoEloquentModel$pago,string$motivo,string$usuarioId):PagoEloquentModel
    {
        return DB::transaction(function()use($pago,$motivo,$usuarioId){$bloqueado=PagoEloquentModel::whereKey($pago->id)->lockForUpdate()->firstOrFail();OrdenTrabajoEloquentModel::whereKey($bloqueado->orden_id)->lockForUpdate()->firstOrFail();if($bloqueado->estado==='anulado')throw ValidationException::withMessages(['pago'=>'Este pago ya fue anulado.']);$bloqueado->update(['estado'=>'anulado','anulado_en'=>now(),'anulado_por'=>$usuarioId,'motivo_anulacion'=>$motivo]);PagoHistorialEloquentModel::create(['pago_id'=>$bloqueado->id,'evento'=>'anulado','monto'=>$bloqueado->monto,'datos'=>['motivo'=>$motivo],'usuario_id'=>$usuarioId]);return$bloqueado;});
    }
}
