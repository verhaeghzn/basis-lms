<?php

namespace App\Providers;

use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Foundation\ViteManifestNotFoundException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

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
        // Vite::asset() requires public/build/manifest.json. During Forge deploys,
        // composer install (package:discover) runs before npm run build.
        try {
            FilamentAsset::register([
                Js::make('chart-js-plugins', Vite::asset('resources/js/filament-chart-js-plugins.js'))->module(),
            ]);
        } catch (ViteManifestNotFoundException) {
            //
        }

        Event::listen('Illuminate\Database\Events\QueryExecuted', function ($query) {
            logger([$query->sql, $query->bindings, $query->time]);
        });
    }
}
