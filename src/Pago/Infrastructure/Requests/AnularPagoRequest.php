<?php
namespace Src\Pago\Infrastructure\Requests;
use Illuminate\Foundation\Http\FormRequest;
class AnularPagoRequest extends FormRequest{public function authorize():bool{return$this->user()?->can('pagos.anular')??false;}public function rules():array{return['motivo'=>'required|string|min:5|max:1000'];}}
