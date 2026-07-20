<?php

namespace Src\Auth\Application\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Src\Auth\Infrastructure\Requests\LoginRequest;

class WebAuthController extends Controller
{

    /**
     * Mostrar formulario de login
     */
    public function showLoginForm(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Procesar login
     *
     * Este método usa tu sistema de autenticación basado en tokens
     * pero lo adapta para funcionar con sesiones web
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = [
            'email' => mb_strtolower($request->string('email')->trim()->toString()),
            'password' => $request->string('password')->toString(),
            'activo' => true,
        ];

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Bienvenido de vuelta.');
    }

    /**
     * Cerrar sesión
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Sesión cerrada exitosamente.');
    }
}
