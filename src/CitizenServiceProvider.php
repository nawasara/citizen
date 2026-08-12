<?php

namespace Nawasara\Citizen;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Nawasara\Citizen\Services\CitizenProvisioner;
use Symfony\Component\Finder\Finder;

class CitizenServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nawasara-citizen');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerLivewire();
        $this->registerCitizenApiRoutes();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nawasara-citizen.php', 'nawasara-citizen');

        $this->app->singleton(CitizenProvisioner::class, fn () => new CitizenProvisioner);
    }

    /**
     * Citizen-facing API — behind api.citizen (Keycloak JWT), NOT api.auth.
     *
     * No scope middleware here. Scopes belong to the nws_ token model, where a
     * caller is granted a subset of what the system can do. A citizen is not
     * granted anything: they reach their own row and nothing else, and that is
     * enforced by taking the identity from the token rather than the request.
     *
     * Guarded on class_exists rather than a composer dependency, matching the
     * other packages: this one still works without nawasara/api, it just has
     * no API.
     */
    protected function registerCitizenApiRoutes(): void
    {
        if (! class_exists(\Nawasara\Api\ApiServiceProvider::class)) {
            return;
        }

        $prefix = (string) config('nawasara-api.route.prefix', 'api/v1').'/citizen';

        Route::prefix($prefix)
            ->middleware(['api', 'api.citizen', 'throttle:nawasara-citizen'])
            ->name('nawasara-api.citizen.')
            ->group(__DIR__.'/../routes/api.php');
    }

    /**
     * Auto-discover Livewire components under src/Livewire.
     *
     * Alias = nawasara-citizen.<kebab-path>, so
     * Livewire/Profile/Section/Table.php becomes
     * nawasara-citizen.profile.section.table.
     */
    protected function registerLivewire(): void
    {
        $base = __DIR__.'/Livewire';

        if (! is_dir($base)) {
            return;
        }

        foreach ((new Finder)->files()->in($base)->name('*.php') as $file) {
            $relative = Str::of($file->getRelativePathname())
                ->replace(['/', '\\'], '\\')
                ->replace('.php', '');

            $class = 'Nawasara\\Citizen\\Livewire\\'.$relative;

            if (! class_exists($class)) {
                continue;
            }

            $alias = 'nawasara-citizen.'.Str::of($relative)
                ->explode('\\')
                ->map(fn ($segment) => Str::kebab($segment))
                ->implode('.');

            Livewire::component($alias, $class);
        }
    }
}
