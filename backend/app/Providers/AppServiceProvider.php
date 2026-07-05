<?php

namespace App\Providers;

use App\Domains\Admin\Policies\BookingPolicy;
use App\Domains\Admin\Models\StaticPage;
use App\Domains\Admin\Policies\GovernanceAuditPolicy;
use App\Domains\Admin\Policies\StaticPagePolicy;
use App\Domains\Admin\Policies\PartnerPolicy;
use App\Domains\Admin\Policies\TourPolicy;
use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\Partner;
use App\Domains\Reviews\Models\Review;
use App\Models\Tour;
use App\Models\User;
use App\Policies\ReviewPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        // Register policies
        Gate::policy(Review::class, ReviewPolicy::class);
        Gate::policy(Tour::class, TourPolicy::class);
        Gate::policy(Partner::class, PartnerPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(GovernanceAuditLog::class, GovernanceAuditPolicy::class);
        Gate::policy(StaticPage::class, StaticPagePolicy::class);

        // Spec 013 governance morph maps: GovernanceAuditLog stores actor/target
        // types as these aliases (data-model.md §1/§7). `setting` is a plain
        // target_type string with a null target_id (settings are not Eloquent
        // models), so it has no morph-map entry. StaticPage is added in US9.
        Relation::morphMap([
            'admin' => User::class,
            'tour' => Tour::class,
            'partner' => Partner::class,
            'booking' => Booking::class,
            'review' => Review::class,
            'static_page' => StaticPage::class,
        ]);

        // Register rate limiters so they are available during testing
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('detail', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('listing', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('homepage', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('sitemap', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('reviews', function (Request $request) {
            return Limit::perHour(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('booking.create', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('booking.get', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('traveler', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
