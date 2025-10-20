<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\HarvestPublished;
use App\Listeners\TriggerHarvestMatching;

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
        // Register event listeners for harvest pipeline
        Event::listen(HarvestPublished::class, TriggerHarvestMatching::class);
    }
}
