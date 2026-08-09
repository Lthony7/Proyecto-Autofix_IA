<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Testing\AssertableInertia as Assert;
use Src\Auth\Application\Controllers\PasswordResetController;
use Src\Auth\Infrastructure\Requests\ForgotPasswordRequest;
use Src\Auth\Infrastructure\Requests\ResetPasswordRequest;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    public function test_guest_password_recovery_pages_are_available(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword'));

        $this->get(route('password.reset', ['token' => 'test-token', 'email' => ' User@Example.COM ']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/ResetPassword')
                ->where('token', 'test-token')
                ->where('email', 'user@example.com'));
    }

    public function test_password_routes_have_expected_names_and_guest_middleware(): void
    {
        foreach (['password.request', 'password.email', 'password.reset', 'password.update'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);

            $this->assertNotNull($route);
            $this->assertContains('guest', $route->gatherMiddleware());
        }
    }

    public function test_link_response_is_generic_when_broker_cannot_find_a_user(): void
    {
        Password::shouldReceive('broker->sendResetLink')
            ->once()
            ->with(['email' => 'missing@example.com', 'activo' => true])
            ->andReturn(Password::INVALID_USER);

        $this->post(route('password.email'), ['email' => ' Missing@Example.COM '])
            ->assertSessionHas('success', PasswordResetController::LINK_RESPONSE)
            ->assertSessionHasNoErrors();
    }

    public function test_recovery_requests_normalize_email_and_enforce_registration_password_policy(): void
    {
        $forgot = ForgotPasswordRequest::create('/forgot-password', 'POST', ['email' => ' User@Example.COM ']);
        $forgot->setContainer(app());
        $forgot->validateResolved();
        $this->assertSame('user@example.com', $forgot->validated('email'));

        $request = new ResetPasswordRequest;
        $rules = $request->rules();
        $passwordRule = collect($rules['password'])->first(fn ($rule) => $rule instanceof PasswordRule);

        $this->assertNotNull($passwordRule);
        $this->assertTrue(Validator::make([
            'token' => 'token',
            'email' => 'user@example.com',
            'password' => 'Strong2026',
            'password_confirmation' => 'Strong2026',
        ], $rules)->passes());
        $this->assertFalse(Validator::make([
            'token' => 'token',
            'email' => 'user@example.com',
            'password' => 'weakpass',
            'password_confirmation' => 'weakpass',
        ], $rules)->passes());
    }
}
