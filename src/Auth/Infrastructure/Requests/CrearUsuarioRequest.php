<?php

namespace Src\Auth\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Src\Auth\Infrastructure\Models\RoleEloquentModel;

class CrearUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('usuarios.crear') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'role_ids' => $this->input('roleIds', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'role_ids' => ['required', 'array', 'size:1'],
            'role_ids.*' => ['uuid', 'distinct', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (RoleEloquentModel::whereIn('id', $this->input('role_ids', []))->where('name', 'Cliente')->exists()) {
                $validator->errors()->add('roleIds', 'Las cuentas Cliente se crean exclusivamente desde el registro público.');
            }
        }];
    }
}
