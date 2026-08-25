<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //p
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // remove temporary 
        DB::prohibitDestructiveCommands(app()->environment('production'));
         if (env('APP_ENV') === 'production') {
        \URL::forceScheme('https');
    }
    }
}
