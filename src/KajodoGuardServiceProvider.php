<?php

namespace Kajodo\Guard;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Kajodo\Guard\Console\PruneVisitorsCommand;
use Kajodo\Guard\Http\Middleware\RestrictVisitors;

class KajodoGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/kajodo-guard.php', 'kajodo-guard');

        $this->app->singleton(KajodoClient::class, function (): KajodoClient {
            return new KajodoClient(
                baseUrl: (string) config('kajodo-guard.base_url', ''),
                apiKey: (string) config('kajodo-guard.api_key', ''),
                origin: (string) config('kajodo-guard.origin', ''),
                timeout: (int) config('kajodo-guard.timeout', 3),
            );
        });

        $this->app->singleton(KajodoGuard::class, function (): KajodoGuard {
            return new KajodoGuard(
                client: $this->app->make(KajodoClient::class),
                cache: $this->app['cache']->store(config('kajodo-guard.cache_store')),
                cacheEnabled: (bool) config('kajodo-guard.cache_enabled', true),
                cacheTtl: (int) config('kajodo-guard.cache_ttl', 3600),
                failOpen: (bool) config('kajodo-guard.fail_open', true),
                storeEnabled: (bool) config('kajodo-guard.store_enabled', true),
            );
        });

        $this->app->alias(KajodoGuard::class, 'kajodo.guard');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/kajodo-guard.php' => config_path('kajodo-guard.php'),
        ], 'kajodo-guard-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'kajodo-guard-migrations');

        $this->commands([PruneVisitorsCommand::class]);

        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('kajodo.guard', RestrictVisitors::class);
    }
}
