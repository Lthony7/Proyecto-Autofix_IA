<?php
namespace Src\OrdenTrabajo\Infrastructure\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class CambiarEstadoOrdenRequest extends FormRequest { public function authorize():bool{$permiso=$this->input('estado')==='cancelada'?'ordenes.cancelar':'ordenes.avanzar';return $this->user()?->can($permiso)??false;} public function rules():array{return ['estado'=>['required',Rule::in(['en_diagnostico','en_reparacion','finalizada','entregada','cancelada'])],'observaciones'=>['nullable','string'],'motivo'=>['required_if:estado,cancelada','nullable','string']];} }
