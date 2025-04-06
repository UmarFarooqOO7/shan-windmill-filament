<?php

namespace App\Providers;

use App\Models\Lead;
use App\Observers\LeadObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

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
        // Register Lead model observer
        Lead::observe(LeadObserver::class);

        Gate::define('view_all_leads', function ($user) {
            // You can customize this based on your requirements, e.g., check for admin role
            return false; // By default, no one can view all leads
        });
    }
}
