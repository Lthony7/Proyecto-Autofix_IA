<?php
namespace Src\Pago\Infrastructure\Requests;
use Illuminate\Foundation\Http\FormRequest;use Illuminate\Validation\Rule;
class RegistrarPagoRequest extends FormRequest{public function authorize():bool{return$this->user()?->can('pagos.registrar')??false;}protected function prepareForValidation():void{$this->merge(['pagado_en'=>$this->input('pagadoEn')?:now()->format('Y-m-d H:i:s')]);}public function rules():array{return['monto'=>'required|decimal:0,2|gt:0','metodo'=>['required',Rule::in(['efectivo','tarjeta','transferencia','otro'])],'referencia'=>'nullable|string|max:120','observaciones'=>'nullable|string','pagado_en'=>'required|date|before_or_equal:now'];}}
