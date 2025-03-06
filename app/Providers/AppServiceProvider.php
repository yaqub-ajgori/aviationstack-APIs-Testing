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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Check for required Aviationstack API environment variables
        if (app()->environment('local') && env('AVIATIONSTACK_API_KEY') === null) {
            \Log::warning('Aviationstack API key not set in .env file. Set AVIATIONSTACK_API_KEY to use real flight data.');
        }
    }
}
