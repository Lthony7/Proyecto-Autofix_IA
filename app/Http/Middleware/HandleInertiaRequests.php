<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'errors' => fn () => $this->validationErrors($request),
            'auth' => fn () => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'roles' => $request->user()->getRoleNames()->values(),
                    'permissions' => $request->user()->getAllPermissions()->pluck('name')->values(),
                ] : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'navigation' => fn () => [
                'ordenesActivas' => $request->user()?->can('ordenes.ver')
                    ? OrdenTrabajoEloquentModel::query()
                        ->visiblePara($request->user())
                        ->yaRecibidas()
                        ->whereIn('estado', ['pendiente', 'asignada', 'en_diagnostico', 'esperando_aprobacion', 'esperando_repuestos', 'en_reparacion', 'pausada', 'en_prueba', 'finalizada', 'lista_entrega'])
                        ->count()
                    : null,
            ],
            'ziggy' => fn () => [
                ...\Illuminate\Support\Facades\Route::current() ? (new \Tighten\Ziggy\Ziggy)->toArray() : [],
                'location' => $request->url(),
            ],
        ];
    }

    private function validationErrors(Request $request): array
    {
        $bag = $request->session()->get('errors')?->getBag('default');
        $errors = [];
        foreach ($bag?->messages() ?? [] as $key => $messages) {
            $message = $messages[0] ?? null;
            if (! $message) continue;
            $errors[$key] = $message;
            $camel = collect(explode('.', $key))->map(fn (string $part) => ctype_digit($part) ? $part : Str::camel($part))->implode('.');
            $errors[$camel] = $message;
        }

        return $errors;
    }
}
