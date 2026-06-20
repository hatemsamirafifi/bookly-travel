<?php

namespace App\Providers;

use App\Domains\Auth\Events\AccountLockedOut;
use App\Domains\Auth\Events\EmailVerified;
use App\Domains\Auth\Events\GuestConvertedToAccount;
use App\Domains\Auth\Events\LoginFailed;
use App\Domains\Auth\Events\PasswordChanged;
use App\Domains\Auth\Events\PasswordReset;
use App\Domains\Auth\Events\TravelerLoggedIn;
use App\Domains\Auth\Events\TravelerRegistered;
use App\Domains\Auth\Listeners\LogAuthEvent;
use App\Domains\Auth\Listeners\SendAccountLockedOutEmail;
use App\Domains\Payment\Events\PaymentFailed;
use App\Domains\Payment\Events\PaymentSucceeded;
use App\Domains\Payment\Listeners\ConfirmBookingOnPayment;
use App\Domains\Payment\Listeners\ExpireBookingOnPaymentFailure;
use App\Domains\Payment\Listeners\NotifyAdminOnPaymentFailure;
use App\Domains\Reviews\Events\ReviewFlagged;
use App\Domains\Reviews\Events\ReviewSubmitted;
use App\Domains\Reviews\Listeners\UpdateTourAggregateRating;
use App\Domains\Search\Actions\IndexTourAction;
use App\Domains\Search\Actions\RemoveFromIndexAction;
use App\Events\BookingEmailDeliveryFailed;
use App\Listeners\NotifyAdminOnEmailDeliveryFailure;
use App\Models\Tour;
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
        TravelerRegistered::class => [
            LogAuthEvent::class,
        ],
        TravelerLoggedIn::class => [
            LogAuthEvent::class,
        ],
        LoginFailed::class => [
            LogAuthEvent::class,
        ],
        AccountLockedOut::class => [
            LogAuthEvent::class,
            SendAccountLockedOutEmail::class,
        ],
        PasswordReset::class => [
            LogAuthEvent::class,
        ],
        PasswordChanged::class => [
            LogAuthEvent::class,
        ],
        EmailVerified::class => [
            LogAuthEvent::class,
        ],
        GuestConvertedToAccount::class => [
            LogAuthEvent::class,
        ],
        // FR-028: notify admins when booking confirmation email delivery is exhausted
        BookingEmailDeliveryFailed::class => [
            NotifyAdminOnEmailDeliveryFailure::class,
        ],
        PaymentSucceeded::class => [
            ConfirmBookingOnPayment::class,
        ],
        PaymentFailed::class => [
            ExpireBookingOnPaymentFailure::class,
            NotifyAdminOnPaymentFailure::class,
        ],
        ReviewSubmitted::class => [
            UpdateTourAggregateRating::class,
        ],
        ReviewFlagged::class => [
            UpdateTourAggregateRating::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        Tour::saved(function (Tour $tour) {
            if ($tour->shouldBeSearchable()) {
                IndexTourAction::dispatch($tour->id);
            } else {
                RemoveFromIndexAction::dispatch($tour->id);
            }
        });

        Tour::deleted(function (Tour $tour) {
            RemoveFromIndexAction::dispatch($tour->id);
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
