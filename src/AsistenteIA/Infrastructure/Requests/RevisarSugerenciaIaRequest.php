<?php
namespace Src\AsistenteIA\Infrastructure\Requests;
use Illuminate\Foundation\Http\FormRequest;use Illuminate\Validation\Rule;
class RevisarSugerenciaIaRequest extends FormRequest{public function authorize():bool{return$this->user()?->can('ia.revisar')??false;}public function rules():array{return['estado'=>['required',Rule::in(['en_revision','confirmada','modificada','descartada'])],'observaciones'=>['required_if:estado,modificada,descartada','nullable','string','max:3000'],'resumenAjustado'=>['required_if:estado,modificada','nullable','string','max:1500']];}}
