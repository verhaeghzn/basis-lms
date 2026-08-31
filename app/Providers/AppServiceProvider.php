<?php

namespace App\Providers;

use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Foundation\ViteManifestNotFoundException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

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
        Passport::authorizationView('oauth.authorize');
        Passport::tokensCan([
            'profile:read' => 'Read your BASIS account identity.',
            'samples:read' => 'Browse samples you may use in Semphony.',
            'samples:attach' => 'Verify and attach samples to Semphony sessions.',
        ]);
        Passport::tokensExpireIn(now()->addHour());
        Passport::refreshTokensExpireIn(now()->addDays(30));

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
