<?php

namespace Src\Auth\Application\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Src\Auth\Infrastructure\Models\UserEloquentModel;
use Src\Auth\Infrastructure\Requests\ForgotPasswordRequest;
use Src\Auth\Infrastructure\Requests\ResetPasswordRequest;

class PasswordResetController extends Controller
{
    public const LINK_RESPONSE = 'Si existe una cuenta activa con ese correo, recibirás un enlace para restablecer tu contraseña.';

    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        Password::broker()->sendResetLink([
            'email' => $request->validated('email'),
            'activo' => true,
        ]);

        return back()->with('success', self::LINK_RESPONSE);
    }

    public function edit(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => mb_strtolower(trim((string) $request->query('email', ''))),
        ]);
    }

    public function update(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::broker()->reset(
            [...$request->validated(), 'activo' => true],
            function (UserEloquentModel $user, string $password): void {
                DB::transaction(function () use ($user, $password): void {
                    $user->forceFill([
                        'password' => $password,
                        'remember_token' => Str::random(60),
                    ])->save();

                    if (Schema::hasTable('sessions')) {
                        DB::table('sessions')->where('user_id', $user->getKey())->delete();
                    }

                    if (Schema::hasTable('personal_access_tokens')) {
                        $user->tokens()->delete();
                    }

                    event(new PasswordReset($user));
                });
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', __($status));
        }

        return back()->withErrors(['email' => __($status)])->onlyInput('email');
    }
}
