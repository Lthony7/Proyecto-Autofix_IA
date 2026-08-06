<?php
namespace Src\OrdenTrabajo\Infrastructure\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class AsignarMecanicosRequest extends FormRequest { public function authorize(): bool{return $this->user()?->can('ordenes.asignar')??false;} protected function prepareForValidation():void{$this->merge(['mecanico_ids'=>$this->input('mecanicoIds',[])]);} public function rules():array{return ['mecanico_ids'=>['required','array','min:1'],'mecanico_ids.*'=>['uuid','distinct',Rule::exists('mecanicos','id')->where('estado','activo')],'observaciones'=>['nullable','string']];} }
