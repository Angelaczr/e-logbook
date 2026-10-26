<?php

namespace App\Providers;

use App\Models\Logbook;
use App\Models\User;
use App\Policies\LogbookPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        JsonResource::withoutWrapping();

        // Lazy loading dilarang di luar produksi supaya N+1 ketahuan saat development dan test.
        Model::preventLazyLoading(! $this->app->isProduction());

        RateLimiter::for('review', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('review-bulk', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));

        Gate::policy(Logbook::class, LogbookPolicy::class);

        Gate::define('view-analytics', fn (User $user): bool => $user->is_active && $user->role->canViewAnalytics());
        Gate::define('export-reports', fn (User $user): bool => $user->is_active && $user->role->canViewAnalytics());
    }
}
