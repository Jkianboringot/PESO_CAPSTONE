<?php

namespace App\Providers;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        Event::listen(Login::class, \App\Listeners\LogSuccessfulLogin::class);
        Event::listen(Failed::class, \App\Listeners\LogFailedLogin::class);
    }
}
