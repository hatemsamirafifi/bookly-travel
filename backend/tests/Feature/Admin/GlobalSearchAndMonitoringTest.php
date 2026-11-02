<?php

use App\Domains\Admin\Services\OperationalHealthService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Media\Models\MediaAsset;
use App\Domains\Payment\Models\Payment;
use App\Domains\Translation\Models\TranslationFieldState;
use App\Filament\Pages\OperationalMonitoringPage;
use App\Filament\Resources\AdminUserResource;
use App\Filament\Resources\BlogPostResource;
use App\Filament\Resources\BookingResource;
use App\Filament\Resources\MediaAssetResource;
use App\Filament\Resources\PartnerResource;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\StaticPageResource;
use App\Filament\Resources\TourResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function opsAdmin(array $flags): User
{
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => $flags]);

    return $admin->fresh('adminPermission');
}

it('exposes global search only for resources the admin can list', function () {
    $bookingAdmin = opsAdmin(['manage_bookings' => true]);

    actingAs($bookingAdmin);

    expect(BookingResource::canGloballySearch())->toBeTrue();
    expect(TourResource::canGloballySearch())->toBeFalse();
    expect(PaymentResource::canGloballySearch())->toBeFalse();
    expect(AdminUserResource::canGloballySearch())->toBeFalse();
    expect(PartnerResource::canGloballySearch())->toBeFalse();
    expect(BlogPostResource::canGloballySearch())->toBeFalse();
    expect(StaticPageResource::canGloballySearch())->toBeFalse();

    $tourAdmin = opsAdmin(['manage_tours' => true]);
    actingAs($tourAdmin);
    expect(TourResource::canGloballySearch())->toBeTrue();

    $financesAdmin = opsAdmin(['view_finances' => true]);
    actingAs($financesAdmin);
    expect(PaymentResource::canGloballySearch())->toBeTrue();

    $cmsAdmin = opsAdmin(['manage_cms' => true]);
    actingAs($cmsAdmin);
    expect(StaticPageResource::canGloballySearch())->toBeTrue();
    expect(MediaAssetResource::canGloballySearch())->toBeTrue();
    expect(BlogPostResource::canGloballySearch())->toBeFalse();

    $blogAdmin = opsAdmin(['manage_blog' => true]);
    actingAs($blogAdmin);
    expect(BlogPostResource::canGloballySearch())->toBeTrue();

    $usersAdmin = opsAdmin(['manage_users' => true]);
    actingAs($usersAdmin);
    expect(AdminUserResource::canGloballySearch())->toBeTrue();
});

it('declares global search attributes, titles and details for every resource', function () {
    foreach ([
        TourResource::class,
        PartnerResource::class,
        BookingResource::class,
        PaymentResource::class,
        BlogPostResource::class,
        StaticPageResource::class,
        MediaAssetResource::class,
        AdminUserResource::class,
    ] as $resource) {
        expect($resource::getGloballySearchableAttributes())->not->toBeEmpty();
        expect(
            method_exists($resource, 'getGlobalSearchResultTitle')
            && method_exists($resource, 'getGlobalSearchResultDetails')
        )->toBeTrue();
    }
});

it('aggregates operational health counts and recent failed jobs', function () {
    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => ['manage_blog' => true]]);

    TranslationFieldState::create([
        'translatable_type' => 'blog_post',
        'translatable_id' => $admin->id,
        'locale' => 'es',
        'field_name' => 'body',
        'status' => TranslationFieldState::STATUS_FAILED,
        'attempt_count' => 3,
        'last_error_code' => 'CORRUPTED_HTML',
    ]);

    MediaAsset::create([
        'uuid' => (string) Str::uuid(),
        'disk' => 'r2',
        'storage_key' => 'library/broken.jpg',
        'original_filename' => 'broken.jpg',
        'filename' => 'broken.jpg',
        'extension' => 'jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 2048,
        'checksum_sha256' => hash('sha256', 'broken.jpg'),
        'processing_status' => MediaAsset::STATUS_FAILED,
        'visibility' => 'private',
        'uploaded_by' => $admin->id,
    ]);

    $guest = User::factory()->create();
    $tour = makeSearchableTour('published');
    $booking = Booking::create([
        'reference' => 'BKO-' . strtoupper(Str::random(6)),
        'traveler_id' => $guest->id,
        'tour_id' => $tour->id,
        'tour_date' => now()->addDays(3)->toDateString(),
        'participant_count' => 2,
        'price_per_person' => 5000,
        'total_price' => 10000,
        'currency' => 'USD',
        'status' => Booking::STATUS_EXPIRED,
    ]);

    Payment::create([
        'booking_id' => $booking->id,
        'stripe_payment_intent_id' => 'pi_test_failed',
        'type' => 'payment',
        'amount' => 10000,
        'currency' => 'USD',
        'status' => 'failed',
    ]);

    DB::table('stripe_webhook_events')->insert([
        'stripe_event_id' => 'evt_test_failed',
        'event_type' => 'invoice.payment_failed',
        'processing_status' => 'failed',
        'payload_hash' => hash('sha256', 'evt_test_failed'),
        'created_at' => now(),
    ]);

    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => "First line\nsecond line",
        'failed_at' => now(),
    ]);

    $snapshot = app(OperationalHealthService::class)->snapshot();

    expect($snapshot['counts']['failed_translations'])->toBe(1);
    expect($snapshot['counts']['failed_media_jobs'])->toBe(1);
    expect($snapshot['counts']['failed_payments'])->toBe(1);
    expect($snapshot['counts']['failed_webhook_events'])->toBe(1);
    expect($snapshot['counts']['expired_bookings_24h'])->toBe(1);
    expect($snapshot['counts']['failed_queue_jobs'])->toBe(1);

    expect($snapshot['recent_failed_jobs'])->toHaveCount(1);
    expect($snapshot['recent_failed_jobs'][0]['exception'])->toBe('First line');
    expect($snapshot['recent_failed_jobs'][0]['queue'])->toBe('default');
});

it('renders the operational monitoring page only for authorized flags', function () {
    $allowed = opsAdmin(['view_system_health' => true]);
    actingAs($allowed);
    Livewire::test(OperationalMonitoringPage::class)->assertSuccessful();

    $auditReader = opsAdmin(['view_audit_log' => true]);
    actingAs($auditReader);
    expect(OperationalMonitoringPage::canAccess())->toBeTrue();

    $denied = opsAdmin([]);
    actingAs($denied);
    expect(OperationalMonitoringPage::canAccess())->toBeFalse();

    $settingsOnly = opsAdmin(['manage_settings' => true]);
    actingAs($settingsOnly);
    expect(OperationalMonitoringPage::canAccess())->toBeTrue();
});

it('retries all failed translations from the monitoring page action', function () {
    Queue::fake();

    $admin = User::factory()->admin()->create();
    $admin->adminPermission()->create(['flags' => ['manage_blog' => true, 'view_system_health' => true]]);
    actingAs($admin);

    TranslationFieldState::create([
        'translatable_type' => 'blog_post',
        'translatable_id' => $admin->id,
        'locale' => 'es',
        'field_name' => 'body',
        'status' => TranslationFieldState::STATUS_FAILED,
    ]);
    TranslationFieldState::create([
        'translatable_type' => 'blog_post',
        'translatable_id' => $admin->id,
        'locale' => 'it',
        'field_name' => 'excerpt',
        'status' => TranslationFieldState::STATUS_FAILED,
    ]);
    TranslationFieldState::create([
        'translatable_type' => 'blog_post',
        'translatable_id' => $admin->id,
        'locale' => 'it',
        'field_name' => 'body',
        'status' => TranslationFieldState::STATUS_COMPLETED,
    ]);

    $page = Livewire::test(OperationalMonitoringPage::class);
    $page->call('retryFailedTranslations');

    expect(TranslationFieldState::where('locale', 'es')->where('field_name', 'body')->first()->status)
        ->toBe(TranslationFieldState::STATUS_QUEUED);
    expect(TranslationFieldState::where('locale', 'it')->where('field_name', 'excerpt')->first()->status)
        ->toBe(TranslationFieldState::STATUS_QUEUED);
    expect(TranslationFieldState::where('locale', 'it')->where('field_name', 'body')->first()->status)
        ->toBe(TranslationFieldState::STATUS_COMPLETED);
});

it('denies the operational monitoring page URL without a health flag', function () {
    $verifier = opsAdmin(['view_system_health' => true]);
    actingAs($verifier);
    get('/admin/operational-monitoring')->assertOk();

    $denied = opsAdmin([]);
    actingAs($denied);
    get('/admin/operational-monitoring')->assertForbidden();
});
