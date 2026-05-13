<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     * Auth event listeners will be registered in Phase 2 (T017).
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        \App\Domains\Auth\Events\TravelerRegistered::class => [
            \App\Domains\Auth\Listeners\LogAuthEvent::class,
        ],
        \App\Domains\Auth\Events\TravelerLoggedIn::class => [
            \App\Domains\Auth\Listeners\LogAuthEvent::class,
        ],
        \App\Domains\Auth\Events\LoginFailed::class => [
            \App\Domains\Auth\Listeners\LogAuthEvent::class,
        ],
        \App\Domains\Auth\Events\AccountLockedOut::class => [
            \App\Domains\Auth\Listeners\LogAuthEvent::class,
            \App\Domains\Auth\Listeners\SendAccountLockedOutEmail::class,
        ],
        \App\Domains\Auth\Events\PasswordReset::class => [
            \App\Domains\Auth\Listeners\LogAuthEvent::class,
        ],
        \App\Domains\Auth\Events\PasswordChanged::class => [
            \App\Domains\Auth\Listeners\LogAuthEvent::class,
        ],
        \App\Domains\Auth\Events\EmailVerified::class => [
            \App\Domains\Auth\Listeners\LogAuthEvent::class,
        ],
        \App\Domains\Auth\Events\GuestConvertedToAccount::class => [
            \App\Domains\Auth\Listeners\LogAuthEvent::class,
        ],
        // FR-028: notify admins when booking confirmation email delivery is exhausted
        \App\Events\BookingEmailDeliveryFailed::class => [
            \App\Listeners\NotifyAdminOnEmailDeliveryFailure::class,
        ],
        \App\Domains\Payment\Events\PaymentSucceeded::class => [
            \App\Domains\Payment\Listeners\ConfirmBookingOnPayment::class,
        ],
        \App\Domains\Payment\Events\PaymentFailed::class => [
            \App\Domains\Payment\Listeners\ExpireBookingOnPaymentFailure::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        \App\Models\Tour::saved(function (\App\Models\Tour $tour) {
            if ($tour->shouldBeSearchable()) {
                \App\Domains\Search\Actions\IndexTourAction::dispatch($tour->id);
            } else {
                \App\Domains\Search\Actions\RemoveFromIndexAction::dispatch($tour->id);
            }
        });

        \App\Models\Tour::deleted(function (\App\Models\Tour $tour) {
            \App\Domains\Search\Actions\RemoveFromIndexAction::dispatch($tour->id);
        });
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
