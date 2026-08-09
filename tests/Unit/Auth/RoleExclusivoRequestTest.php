<?php

namespace Tests\Unit\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Src\Auth\Infrastructure\Requests\ActualizarRolesUsuarioRequest;
use Src\Auth\Infrastructure\Requests\CrearUsuarioRequest;
use Tests\TestCase;

class RoleExclusivoRequestTest extends TestCase
{
    public function test_creacion_y_actualizacion_exigen_exactamente_un_rol(): void
    {
        foreach ([CrearUsuarioRequest::class, ActualizarRolesUsuarioRequest::class] as $requestClass) {
            $this->assertTrue($this->validator($requestClass, [])->fails());
            $this->assertFalse($this->validator($requestClass, ['rol-interno'])->fails());
            $this->assertTrue($this->validator($requestClass, ['rol-a', 'rol-b'])->fails());
        }
    }

    private function validator(string $requestClass, array $roles): \Illuminate\Contracts\Validation\Validator
    {
        /** @var FormRequest $request */
        $request = new $requestClass();

        return Validator::make(['role_ids' => $roles], [
            'role_ids' => $request->rules()['role_ids'],
        ]);
    }
}
