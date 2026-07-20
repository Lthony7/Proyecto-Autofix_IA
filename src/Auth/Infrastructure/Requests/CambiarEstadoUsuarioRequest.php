<?php
namespace Src\Auth\Infrastructure\Requests;
use Illuminate\Foundation\Http\FormRequest;
class CambiarEstadoUsuarioRequest extends FormRequest{public function authorize():bool{return$this->user()?->can('usuarios.desactivar')??false;}public function rules():array{return['activo'=>'required|boolean'];}}
