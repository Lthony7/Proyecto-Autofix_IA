<?php
namespace Src\Facturacion\Infrastructure\Requests;
use Illuminate\Foundation\Http\FormRequest;
class EmitirFacturaOrdenRequest extends FormRequest{public function authorize():bool{return$this->user()?->can('facturas.crear')??false;}protected function prepareForValidation():void{$this->merge(['tasa_impuesto'=>$this->input('tasaImpuesto',0),'vence_en'=>$this->input('venceEn')?:null]);}public function rules():array{return['descuento'=>'required|decimal:0,2|min:0','tasa_impuesto'=>'required|decimal:0,2|between:0,100','vence_en'=>'nullable|date|after_or_equal:today','observaciones'=>'nullable|string|max:2000'];}}
