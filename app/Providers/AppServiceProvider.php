<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Lead;
use App\Observers\EventObserver;
use App\Observers\LeadObserver;
use Illuminate\Support\Facades\DB;
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
        Event::observe(EventObserver::class);

        // Remove ONLY_FULL_GROUP_BY from SQL mode
        DB::statement("SET SQL_MODE=''");

        Gate::define('view_all_leads', function ($user) {
            // You can customize this based on your requirements, e.g., check for admin role
            return false; // By default, no one can view all leads
        });
    }
}
