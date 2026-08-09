<?php

namespace Src\Auth\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Auth\Infrastructure\Models\RoleEloquentModel;

class ActualizarRolesUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('roles.administrar') ?? false)
            && ! ($this->route('usuario')?->hasRole('Cliente') ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['role_ids' => $this->input('roleIds', [])]);
    }

    public function rules(): array
    {
        return [
            'role_ids' => ['required', 'array', 'size:1'],
            'role_ids.*' => ['uuid', 'distinct', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (RoleEloquentModel::whereIn('id', $this->input('role_ids', []))->where('name', 'Cliente')->exists()) {
                $validator->errors()->add('roleIds', 'El rol Cliente se asigna únicamente mediante el registro público.');
            }
        }];
    }
}
