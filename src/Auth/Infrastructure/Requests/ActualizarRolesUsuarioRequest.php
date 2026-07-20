<?php
namespace Src\Auth\Infrastructure\Requests;
use Illuminate\Foundation\Http\FormRequest;use Illuminate\Validation\Rule;
class ActualizarRolesUsuarioRequest extends FormRequest{public function authorize():bool{return$this->user()?->can('roles.administrar')??false;}protected function prepareForValidation():void{$this->merge(['role_ids'=>$this->input('roleIds',[])]);}public function rules():array{return['role_ids'=>'required|array|min:1','role_ids.*'=>['uuid','distinct',Rule::exists('roles','id')->where('guard_name','web')]];}}
