<?php

namespace DDD\App\Providers;

use Illuminate\Support\ServiceProvider;

// Vendors
use Laravel\Cashier\Cashier;

// Domains
use DDD\Domain\Base\Organizations\Organization;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Cashier::useCustomerModel(Organization::class);
    }
}
