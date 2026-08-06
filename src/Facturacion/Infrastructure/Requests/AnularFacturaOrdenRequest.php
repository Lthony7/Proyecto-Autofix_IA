<?php
namespace Src\Facturacion\Infrastructure\Requests;
use Illuminate\Foundation\Http\FormRequest;
class AnularFacturaOrdenRequest extends FormRequest{public function authorize():bool{return$this->user()?->can('facturas.editar')??false;}public function rules():array{return['motivo'=>'required|string'];}}
