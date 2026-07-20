<?php
namespace Src\OrdenTrabajo\Infrastructure\Requests;
use Illuminate\Foundation\Http\FormRequest;
class RegistrarDiagnosticoRequest extends FormRequest { public function authorize():bool{return $this->user()?->can('diagnosticos.registrar')??false;} public function rules():array{return ['diagnostico'=>['required','string','min:20','max:10000'],'pruebasRealizadas'=>['nullable','string','max:10000'],'recomendaciones'=>['nullable','string','max:5000']];} }
