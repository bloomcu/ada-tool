<?php

namespace DDD\App\Providers;

use Illuminate\Support\ServiceProvider;

use DDD\App\Services\Apify\ApifyADAScanner;
use DDD\App\Services\Apify\ApifyInterface;

class ApifyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(ApifyInterface::class, function ($app) {
            return new ApifyADAScanner(
                token: config('services.apify.token'),
                actor: config('services.apify.actor'),
            );
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
