<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Admin\Models\AdminRole;
use App\Domains\Admin\Models\StaticPage;
use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Blog\Models\BlogCategory;
use App\Domains\Blog\Models\BlogPost;
use App\Domains\Booking\Models\Booking;
use App\Domains\Media\Models\MediaAsset;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PartnerProfile;
use App\Domains\Payment\Models\FinancialLedgerEntry;
use App\Domains\Payment\Models\Payment;
use App\Domains\Translation\Models\TranslationFieldState;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminE2EFixtureSeeder extends Seeder
{
    /**
     * Run the Admin 2.0 E2E database fixtures.
     *
     * Provisions the reproducible test fixtures required by the
     * browser Playwright test suite (frontend/tests/e2e/admin-2.0.spec.ts).
     *
     * Environment & Database Guard:
     * Strictly positively authorized:
     * 1. Application environment must be explicitly 'testing'.
     * 2. Opt-in flag (ALLOW_ADMIN_E2E_FIXTURES=true) must be enabled.
     * 3. Target database must be disposable and designated for testing (name contains 'test'/'testing').
     */
    public function run(): void
    {
        // 1. Strict positive environment check: must be explicitly 'testing'
        if (app()->environment() !== 'testing') {
            throw new \RuntimeException(sprintf(
                'AdminE2EFixtureSeeder is strictly prohibited from running outside the testing environment. Current environment: "%s".',
                app()->environment()
            ));
        }

        // 2. Strict opt-in configuration flag check
        $optIn = (bool) config('app.allow_admin_e2e_fixtures');
        if (! $optIn) {
            throw new \RuntimeException(
                'AdminE2EFixtureSeeder requires explicit authorization via ALLOW_ADMIN_E2E_FIXTURES=true or config("app.allow_admin_e2e_fixtures").'
            );
        }

        // 3. Verification that target database is disposable and designated for testing
        $connection = config('database.default', 'pgsql');
        $databaseName = (string) (config("database.connections.{$connection}.database") ?: DB::connection()->getDatabaseName());
        $isDisposableTestDb = Str::contains(Str::lower($databaseName), ['test', 'testing', ':memory:', 'sqlite_memory'])
            || Str::endsWith(Str::lower($databaseName), ['_test', '_testing']);

        if (! $isDisposableTestDb) {
            throw new \RuntimeException(sprintf(
                'AdminE2EFixtureSeeder refused to run: database "%s" is not designated as a disposable test database (must contain "test" or "testing").',
                $databaseName
            ));
        }

        // 1. System roles + Super Admin
        AdminRole::ensureSystemRoles();
        $allFlags = array_fill_keys(AdminAuthorizationService::FLAGS, true);
        $superRole = AdminRole::query()->where('slug', 'super_admin')->firstOrFail();

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@bookly.test'],
            [
                'name' => 'Bookly Super Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $admin->adminPermission()->updateOrCreate(
            ['user_id' => $admin->id],
            ['flags' => $allFlags]
        );
        $admin->adminRoles()->syncWithoutDetaching([$superRole->id]);
        $admin->authorProfile()->updateOrCreate(
            ['user_id' => $admin->id],
            [
                'display_name' => ['en' => 'Bookly Editorial', 'es' => 'Editorial Bookly', 'it' => 'Redazione Bookly'],
                'bio' => ['en' => 'Platform editorial team.'],
            ]
        );

        // 2. Restricted Admin (Support Agent: bookings/travelers only — denied users/roles/cms/finance)
        $supportRole = AdminRole::query()->where('slug', 'support_agent')->firstOrFail();

        $restrictedAdmin = User::query()->updateOrCreate(
            ['email' => 'restricted-admin@bookly.test'],
            [
                'name' => 'Restricted Support Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $restrictedAdmin->adminPermission()->updateOrCreate(
            ['user_id' => $restrictedAdmin->id],
            ['flags' => $supportRole->permissions]
        );
        $restrictedAdmin->adminRoles()->sync([$supportRole->id]);

        // 3. Traveler for moderation
        $traveler = User::query()->updateOrCreate(
            ['email' => 'e2e-traveler@bookly.test'],
            [
                'name' => 'E2E Moderate Traveler',
                'password' => Hash::make('Password123!'),
                'role' => 'traveler',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // 4. Pending Partner for moderation
        $pendingPartnerUser = User::query()->updateOrCreate(
            ['email' => 'e2e-pending-partner@bookly.test'],
            [
                'name' => 'E2E Pending Partner Contact',
                'password' => Hash::make('password'),
                'role' => 'partner',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $pendingPartner = Partner::query()->updateOrCreate(
            ['user_id' => $pendingPartnerUser->id],
            [
                'onboarding_status' => 'pending',
                'is_active' => false,
            ]
        );
        PartnerProfile::query()->updateOrCreate(
            ['partner_id' => $pendingPartner->id],
            [
                'company_name' => 'E2E Pending Coastal Partner',
                'contact_email' => 'e2e-pending-partner@bookly.test',
                'contact_phone' => '+39089123456',
                'payout_country' => 'IT',
            ]
        );

        // 5. Approved Partner + Category + Pending Tour + Published Tour
        $approvedPartnerUser = User::query()->updateOrCreate(
            ['email' => 'partner@bookly.test'],
            [
                'name' => 'E2E Approved Partner Contact',
                'password' => Hash::make('password'),
                'role' => 'partner',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $approvedPartner = Partner::query()->updateOrCreate(
            ['user_id' => $approvedPartnerUser->id],
            [
                'onboarding_status' => 'approved',
                'is_active' => true,
            ]
        );
        PartnerProfile::query()->updateOrCreate(
            ['partner_id' => $approvedPartner->id],
            [
                'company_name' => 'E2E Approved Amalfi Partner',
                'contact_email' => 'partner@bookly.test',
                'contact_phone' => '+39089654321',
                'payout_country' => 'IT',
            ]
        );

        $category = Category::query()->updateOrCreate(
            ['slug' => 'boat-tours'],
            [
                'name' => 'Boat Tours',
                'display_order' => 1,
                'is_active' => true,
            ]
        );

        $pendingTour = Tour::query()->updateOrCreate(
            ['slug' => 'e2e-pending-amalfi-tour'],
            [
                'partner_id' => $approvedPartner->id,
                'category_id' => $category->id,
                'location' => 'Amalfi Coast',
                'location_slug' => 'amalfi-coast',
                'duration_minutes' => 180,
                'duration_label' => '3 hours',
                'group_size_min' => 1,
                'group_size_max' => 12,
                'price_amount' => 12000,
                'status' => 'pending_review',
                'is_featured' => false,
            ]
        );
        TourTranslation::query()->updateOrCreate(
            ['tour_id' => $pendingTour->id, 'locale' => 'en'],
            [
                'title' => 'E2E Pending Amalfi Tour',
                'description' => '<p>Scenic boat cruise along the Amalfi Coast.</p>',
                'meeting_point' => 'Amalfi Pier 2',
                'cancellation_policy' => 'Free cancellation up to 24 hours before departure.',
            ]
        );

        $publishedTour = Tour::query()->updateOrCreate(
            ['slug' => 'e2e-published-capri-tour'],
            [
                'partner_id' => $approvedPartner->id,
                'category_id' => $category->id,
                'location' => 'Capri',
                'location_slug' => 'capri',
                'duration_minutes' => 240,
                'duration_label' => '4 hours',
                'group_size_min' => 1,
                'group_size_max' => 10,
                'price_amount' => 15000,
                'status' => 'published',
                'published_at' => now()->subDays(5),
                'is_featured' => true,
            ]
        );
        TourTranslation::query()->updateOrCreate(
            ['tour_id' => $publishedTour->id, 'locale' => 'en'],
            [
                'title' => 'E2E Published Capri Tour',
                'description' => '<p>Full day island cruise around Capri.</p>',
                'meeting_point' => 'Marina Grande',
                'cancellation_policy' => 'Free cancellation up to 24 hours before departure.',
            ]
        );

        // 6. Booking for moderation & CSV export
        $booking = Booking::query()->updateOrCreate(
            ['reference' => 'BK-E2E901'],
            [
                'traveler_id' => $traveler->id,
                'tour_id' => $publishedTour->id,
                'tour_date' => now()->addDays(7)->toDateString(),
                'participant_count' => 2,
                'price_per_person' => 15000,
                'total_price' => 30000,
                'currency' => 'EUR',
                'status' => 'confirmed',
            ]
        );

        // 7. Payment & FinancialLedgerEntry for Financial Overview & Payout liability inspection
        $payment = Payment::query()->updateOrCreate(
            ['stripe_payment_intent_id' => 'pi_e2e_amalfi_001'],
            [
                'booking_id' => $booking->id,
                'type' => 'charge',
                'amount' => 30000,
                'currency' => 'EUR',
                'status' => 'succeeded',
                'card_brand' => 'visa',
                'card_last_four' => '4242',
            ]
        );

        if (! FinancialLedgerEntry::query()->where('booking_id', $booking->id)->exists()) {
            FinancialLedgerEntry::query()->create([
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'entry_type' => 'credit',
                'amount' => 30000,
                'currency' => 'EUR',
                'actor' => 'system',
                'description' => 'E2E Booking Capture Ledger Entry',
            ]);
        }

        // 8. MediaAsset for Media Library & MediaPicker
        $fixtureKey = 'media/2026/08/e2e-amalfi-hero.jpg';
        $disk = Storage::disk('public');
        if (! $disk->exists($fixtureKey)) {
            $img = imagecreatetruecolor(1600, 900);
            $bg = imagecolorallocate($img, 30, 60, 90);
            imagefill($img, 0, 0, $bg);
            $textColor = imagecolorallocate($img, 240, 240, 240);
            imagestring($img, 5, 50, 50, 'E2E Amalfi Hero Fixture', $textColor);
            ob_start();
            imagejpeg($img, null, 85);
            $imageContent = (string) ob_get_clean();
            imagedestroy($img);

            $disk->put($fixtureKey, $imageContent);
        }

        $realPath = $disk->path($fixtureKey);
        $fileSize = $disk->size($fixtureKey);
        $checksum = hash_file('sha256', $realPath);
        $imageInfo = @getimagesize($realPath);
        $width = $imageInfo ? (int) $imageInfo[0] : 1600;
        $height = $imageInfo ? (int) $imageInfo[1] : 900;
        $mime = $imageInfo ? (string) $imageInfo['mime'] : 'image/jpeg';
        $isReadable = is_readable($realPath);

        MediaAsset::query()->updateOrCreate(
            ['storage_key' => $fixtureKey],
            [
                'uuid' => (string) Str::uuid(),
                'disk' => 'public',
                'filename' => 'e2e-amalfi-hero.jpg',
                'original_filename' => 'e2e-amalfi-hero.jpg',
                'mime_type' => $mime,
                'extension' => 'jpg',
                'size_bytes' => $fileSize,
                'width' => $width,
                'height' => $height,
                'checksum_sha256' => $checksum,
                'processing_status' => $isReadable ? 'ready' : 'failed',
                'visibility' => 'public',
                'title' => ['en' => 'Amalfi Hero Shot'],
                'alt_text' => ['en' => 'Amalfi Coast Sunset'],
                'uploaded_by' => $admin->id,
            ]
        );

        // 9. BlogCategory & BlogPost for CMS editing, sanitization, and translation fallback
        $blogCategory = BlogCategory::query()->updateOrCreate(
            ['slug' => 'e2e-travel-guides'],
            [
                'name' => 'E2E Travel Guides',
                'description' => 'Curated E2E travel guides',
                'display_order' => 1,
                'is_active' => true,
            ]
        );

        $sanitizedPost = BlogPost::query()->updateOrCreate(
            ['slug' => 'e2e-sanitized-guide'],
            [
                'author_id' => $admin->id,
                'blog_category_id' => $blogCategory->id,
                'status' => BlogPost::STATUS_PUBLISHED,
                'published_at' => now()->subMonths(6),
                'title' => ['en' => 'E2E Sanitized Travel Guide'],
                'excerpt' => ['en' => 'Safe travel guide excerpt.'],
                'body' => ['en' => '<p>Initial safe guide body.</p>'],
                'meta_title' => ['en' => 'E2E Sanitized Meta Title'],
                'meta_description' => ['en' => 'E2E Sanitized Meta Description'],
            ]
        );

        $fallbackPost = BlogPost::query()->updateOrCreate(
            ['slug' => 'e2e-fallback-post'],
            [
                'author_id' => $admin->id,
                'blog_category_id' => $blogCategory->id,
                'status' => BlogPost::STATUS_PUBLISHED,
                'published_at' => now()->subMonths(7),
                'title' => [
                    'en' => 'English Fallback Blog Title',
                    'es' => 'Título Español Obsoleto',
                ],
                'excerpt' => [
                    'en' => 'English fallback excerpt.',
                    'es' => 'Resumen español obsoleto.',
                ],
                'body' => [
                    'en' => '<p>English fallback blog body.</p>',
                    'es' => '<p>Cuerpo español obsoleto.</p>',
                ],
            ]
        );

        TranslationFieldState::query()->updateOrCreate(
            [
                'translatable_type' => $fallbackPost->getMorphClass(),
                'translatable_id' => $fallbackPost->id,
                'locale' => 'es',
                'field_name' => 'body',
            ],
            [
                'status' => TranslationFieldState::STATUS_OUTDATED,
                'origin' => TranslationFieldState::ORIGIN_AUTO,
                'source_hash' => hash('sha256', '<p>English fallback blog body.</p>'),
                'translated_source_hash' => hash('sha256', '<p>Old English body.</p>'),
            ]
        );

        // 10. StaticPages for editing & public fallback
        StaticPage::query()->updateOrCreate(
            ['slug' => 'privacy'],
            [
                'status' => StaticPage::STATUS_PUBLISHED,
                'published_at' => now()->subDays(1),
                'title' => [
                    'en' => 'Privacy Policy',
                    'es' => 'Política de Privacidad',
                    'it' => 'Informativa sulla Privacy',
                ],
                'body' => [
                    'en' => '<p>We respect your data.</p><h2>Information We Collect</h2><p>Data.</p><h2>How We Use Information</h2><p>Usage.</p><h2>Third-Party Services</h2><p>Third parties.</p><h2>Your Privacy Rights</h2><p>Rights.</p><h2>Contact Us</h2><p>Support.</p>',
                    'es' => '<p>Respetamos tus datos.</p><h2>Información que Recopilamos</h2><p>Datos.</p><h2>Cómo Usamos la Información</h2><p>Uso.</p><h2>Servicios de Terceros</h2><p>Terceros.</p><h2>Sus Derechos de Privacidad</h2><p>Derechos.</p><h2>Contáctenos</h2><p>Soporte.</p>',
                    'it' => '<p>Rispettiamo i tuoi dati.</p><h2>Informazioni Raccolte</h2><p>Dati.</p><h2>Come Utilizziamo le Informazioni</h2><p>Utilizzo.</p><h2>Servizi di Terze Parti</h2><p>Terze parti.</p><h2>I Tuoi Diritti sulla Privacy</h2><p>Diritti.</p><h2>Contattaci</h2><p>Supporto.</p>',
                ],
                'meta_description' => [
                    'en' => 'Privacy Policy meta',
                    'es' => 'Política de Privacidad meta',
                    'it' => 'Informativa sulla Privacy meta',
                ],
                'updated_by' => $admin->id,
            ]
        );

        $fallbackStaticPage = StaticPage::query()->updateOrCreate(
            ['slug' => 'e2e-fallback-page'],
            [
                'status' => StaticPage::STATUS_PUBLISHED,
                'published_at' => now()->subHours(2),
                'title' => [
                    'en' => 'English Fallback Static Title',
                    'es' => 'Título Estático Obsoleto',
                ],
                'body' => [
                    'en' => '<p>English fallback static body.</p>',
                    'es' => '<p>Cuerpo estático obsoleto.</p>',
                ],
                'updated_by' => $admin->id,
            ]
        );

        TranslationFieldState::query()->updateOrCreate(
            [
                'translatable_type' => $fallbackStaticPage->getMorphClass(),
                'translatable_id' => $fallbackStaticPage->id,
                'locale' => 'es',
                'field_name' => 'body',
            ],
            [
                'status' => TranslationFieldState::STATUS_OUTDATED,
                'origin' => TranslationFieldState::ORIGIN_AUTO,
                'source_hash' => hash('sha256', '<p>English fallback static body.</p>'),
                'translated_source_hash' => hash('sha256', '<p>Old static body.</p>'),
            ]
        );

        // 11. Failed TranslationFieldState for Translation Health & Operational Monitoring
        TranslationFieldState::query()->updateOrCreate(
            [
                'translatable_type' => $sanitizedPost->getMorphClass(),
                'translatable_id' => $sanitizedPost->id,
                'locale' => 'it',
                'field_name' => 'body',
            ],
            [
                'status' => TranslationFieldState::STATUS_FAILED,
                'origin' => TranslationFieldState::ORIGIN_AUTO,
                'source_hash' => hash('sha256', '<p>Initial safe guide body.</p>'),
                'attempt_count' => 3,
                'last_error_code' => 'PROVIDER_TIMEOUT',
                'last_error_message' => 'Simulated timeout for E2E monitoring verification',
            ]
        );
    }
}
