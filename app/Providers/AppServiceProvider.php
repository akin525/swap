<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->bind('App\Console\Commands\StartCommand', function ($app) {
            return new \App\Console\Commands\StartCommand();
        });
    
        $this->app->bind('App\Console\Commands\ChatIdCommand', function ($app) {
            return new \App\Console\Commands\ChatIdCommand();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        if (env('ENFORCE_SSL', false)) {
            \URL::forceScheme('https');
        }
    }
}
