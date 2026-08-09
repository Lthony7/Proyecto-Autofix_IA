<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Unique;
use Src\Auth\Application\Controllers\WebAuthController;
use Src\Auth\Infrastructure\Requests\RegistrarClienteRequest;
use Tests\TestCase;

class PublicClientRegistrationTest extends TestCase
{
    public function test_registration_form_is_public(): void
    {
        $this->get('/register')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Register'));
    }

    public function test_public_registration_accepts_no_role_from_the_request(): void
    {
        $data = [
            'tipo_documento' => 'CEDULA',
            'numero_documento' => '1028267704',
            'razon_social' => 'Cliente Registrado',
            'direccion' => 'Av. 9 de Octubre y Pedro Carbo',
            'telefono' => '0987654321',
            'email' => 'cliente.registrado@example.com',
            'password' => 'Cliente2026',
            'password_confirmation' => 'Cliente2026',
            'role' => 'Administrador',
            'role_ids' => ['administrador'],
        ];
        $request = RegistrarClienteRequest::create('/register', 'POST', $data);
        $rules = collect($request->rules())->map(fn ($fieldRules) => array_values(array_filter(
            is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules),
            fn ($rule) => ! $rule instanceof Unique,
        )))->all();
        $validator = Validator::make($data, $rules);
        $request->setValidator($validator);

        $this->assertFalse($validator->fails());
        $this->assertArrayNotHasKey('role', $request->validated());
        $this->assertArrayNotHasKey('role_ids', $request->validated());
        $this->assertSame('Cliente', WebAuthController::PUBLIC_REGISTRATION_ROLE);
    }
}
