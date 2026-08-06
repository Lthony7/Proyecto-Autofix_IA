<?php

namespace Src\Auth\Application\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Src\Auditoria\Application\Services\RegistrarAuditoria;
use Src\Auth\Infrastructure\Requests\LoginRequest;
use Src\Auth\Infrastructure\Requests\RegistrarClienteRequest;
use Src\Auth\Infrastructure\Models\RoleEloquentModel;
use Src\Auth\Infrastructure\Models\UserEloquentModel;
use Src\Cliente\Infrastructure\Models\ClienteEloquentModel;

class WebAuthController extends Controller
{
    public const PUBLIC_REGISTRATION_ROLE = 'Cliente';

    /**
     * Mostrar formulario de login
     */
    public function showLoginForm(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function showRegistrationForm(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function register(RegistrarClienteRequest $request, RegistrarAuditoria $auditoria): RedirectResponse
    {
        $usuario = DB::transaction(function () use ($request, $auditoria) {
            $datos = $request->validated();
            $usuario = UserEloquentModel::create([
                'name' => $datos['razon_social'],
                'email' => $datos['email'],
                'password' => $datos['password'],
                'activo' => true,
            ]);

            $rolCliente = RoleEloquentModel::findByName(self::PUBLIC_REGISTRATION_ROLE, 'web');
            $usuario->syncRoles([$rolCliente]);

            $cliente = ClienteEloquentModel::create([
                'tipo_documento' => $datos['tipo_documento'],
                'numero_documento' => $datos['numero_documento'],
                'razon_social' => $datos['razon_social'],
                'direccion' => $datos['direccion'],
                'telefono' => $datos['telefono'],
                'email' => $datos['email'],
                'usuario_id' => $usuario->id,
                'estado' => 'activo',
                'creado_por' => $usuario->id,
                'actualizado_por' => $usuario->id,
            ]);

            $auditoria->registrar('cliente.autorregistrado', 'cliente', $cliente->id, ['email' => $cliente->email, 'rol' => self::PUBLIC_REGISTRATION_ROLE], $request, $usuario->id);

            return $usuario;
        });

        Auth::login($usuario);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Cuenta creada exitosamente.');
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
