<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Src\Auth\Domain\Contracts\UserRepositoryInterface;
use Src\Auth\Infrastructure\Repositories\EloquentUserRepository;

class BoundedContextServiceProvider extends ServiceProvider
{
    private const CONTEXTS = [
        'Auth',
        'Cliente',
        'Vehiculo',
        'Taller',
        'Cita',
        'OrdenTrabajo',
        'AsistenteIA',
        'Inventario',
        'Pago',
        'Facturacion',
        'Categoria',
        'Producto',
        'Factura',
        'Auditoria',
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        // Registro de bindings para Auth (único módulo con DDD completo)
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadBoundedContextRoutes();
        $this->loadBoundedContextMigrations();
    }

    /**
     * Cargar las rutas de cada bounded context
     */
    protected function loadBoundedContextRoutes(): void
    {
        foreach (self::CONTEXTS as $context) {
            $webRoutesPath = base_path("src/{$context}/web.php");
            if (file_exists($webRoutesPath)) {
                Route::middleware('web')
                    ->group($webRoutesPath);
            }
        }
    }

    /**
     * Cargar las migraciones de cada bounded context
     */
    protected function loadBoundedContextMigrations(): void
    {
        foreach (self::CONTEXTS as $context) {
            $migrationsPath = base_path("src/{$context}/Infrastructure/Migrations");

            if (is_dir($migrationsPath)) {
                $this->loadMigrationsFrom($migrationsPath);
            }
        }
    }
}
