<?php

namespace App\Providers;

use Illuminate\Support\Facades\Http;
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
        // PHP sans liste de certificats (fréquent sous Windows) : on indique celle à utiliser pour les appels HTTPS
        if ($caBundle = config('services.http_ca_bundle')) {
            Http::globalOptions(['verify' => $caBundle]);
        }
    }
}
