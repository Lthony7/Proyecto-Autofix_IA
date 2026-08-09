<?php

namespace App\Providers;

use App\Console\PrepareDatabaseSchema;
use App\Models\PersonalAccessToken;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;    
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Src\OrdenTrabajo\Infrastructure\Models\OrdenTrabajoEloquentModel;
use Src\OrdenTrabajo\Infrastructure\Policies\WorkOrderPolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        Gate::policy(OrdenTrabajoEloquentModel::class, WorkOrderPolicy::class);
        RateLimiter::for('ia', fn (Request $request) => Limit::perHour(10)->by((string) ($request->user()?->id ?? $request->ip())));

        if ($this->app->runningInConsole()) {
            Event::listen(CommandStarting::class, function (CommandStarting $event): void {
                if (str_starts_with((string) $event->command, 'migrate')) {
                    $this->app->make(PrepareDatabaseSchema::class)->prepare();
                }
            });
        }
    }
}
