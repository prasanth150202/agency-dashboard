<?php

namespace App\Providers;

use App\Models\Referral\Lead;
use App\Models\Referral\TrackingLink;
use App\Policies\LeadPolicy;
use App\Policies\TrackingLinkPolicy;
use Illuminate\Support\Facades\Gate;
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
        // TrackingLink lives under App\Models\Referral, one level deeper
        // than Laravel's default App\Models\* -> App\Policies\*Policy
        // auto-discovery guesses, so it's registered explicitly.
        Gate::policy(TrackingLink::class, TrackingLinkPolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);
    }
}
