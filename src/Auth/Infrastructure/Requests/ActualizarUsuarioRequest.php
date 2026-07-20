<?php
namespace Src\Auth\Infrastructure\Requests;
use Illuminate\Foundation\Http\FormRequest;use Illuminate\Validation\Rule;use Illuminate\Validation\Rules\Password;
class ActualizarUsuarioRequest extends FormRequest{public function authorize():bool{return$this->user()?->can('usuarios.editar')??false;}protected function prepareForValidation():void{$this->merge(['email'=>mb_strtolower(trim((string)$this->input('email')))]);}public function rules():array{$id=$this->route('usuario')?->id;return['name'=>'required|string|max:255','email'=>['required','email','max:255',Rule::unique('users','email')->ignore($id)],'password'=>['nullable','confirmed',Password::min(12)->mixedCase()->numbers()->symbols()]];}}
