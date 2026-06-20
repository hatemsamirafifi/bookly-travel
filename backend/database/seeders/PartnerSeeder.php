<?php

namespace Database\Seeders;

use App\Domains\Partner\Models\AvailabilityException;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\Notification;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PartnerProfile;
use App\Domains\Partner\Models\PartnerSettings;
use App\Domains\Partner\Models\PricingTier;
use App\Domains\Partner\Models\TourDraft;
use App\Domains\Partner\Models\TourMedia;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PartnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates a partner user with sample profile, tours with translations,
     * bookings, reviews, and notifications for development and E2E testing.
     */
    public function run(): void
    {
        // Create partner user
        $user = User::firstOrCreate(
            ['email' => 'partner@bookly.test'],
            [
                'name' => 'Test Partner',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'partner',
            ]
        );

        // Create partner record
        $partner = Partner::firstOrCreate(
            ['user_id' => $user->id],
            [
                'onboarding_status' => 'complete',
                'is_active' => true,
            ]
        );

        // Create partner profile
        PartnerProfile::firstOrCreate(
            ['partner_id' => $partner->id],
            [
                'company_name' => 'Explore Roma Tours',
                'business_description' => 'Award-winning walking tours through the Eternal City.',
                'contact_email' => 'info@exploreroma.example',
                'contact_phone' => '+39 06 1234 5678',
                'website' => 'https://exploreroma.example',
                'business_address' => [
                    'street' => 'Via del Corso 42',
                    'city' => 'Rome',
                    'country' => 'IT',
                    'postal_code' => '00186',
                ],
                'payout_holder_name' => 'Marco Rossi',
                'payout_bank_name' => 'Banca Intesa',
            ]
        );

        // Create partner settings
        PartnerSettings::firstOrCreate(
            ['partner_id' => $partner->id],
            [
                'notify_new_booking' => true,
                'notify_cancellation' => true,
                'notify_daily_summary' => true,
                'notify_review_received' => true,
                'notify_tour_status_change' => true,
                'locale' => 'en',
            ]
        );

        // Seed categories if empty
        $this->seedCategories();

        $walkingCategory = Category::where('slug', 'walking')->first();
        $foodCategory = Category::where('slug', 'food')->first();
        $adventureCategory = Category::where('slug', 'adventure')->first();

        // Create sample tours using actual schema
        $publishedTour = $this->createTourWithTranslations($partner, $walkingCategory, [
            'slug' => 'hidden-gems-rome-walking-tour',
            'location' => 'Rome, Italy',
            'location_slug' => 'rome-italy',
            'duration_minutes' => 180,
            'duration_label' => '3 hours',
            'group_size_min' => 1,
            'group_size_max' => 20,
            'price_amount' => 4500, // €45.00 in cents
            'status' => 'published',
            'cover_image_url' => 'https://cdn.bookly.test/tours/rome-cover.jpg',
            'is_featured' => true,
            'published_at' => now()->subDays(30),
            'price_from' => 45.00,
            'currency' => 'EUR',
        ], [
            'en' => [
                'title' => 'Hidden Gems of Rome Walking Tour',
                'description' => 'Discover the secret corners of Rome that most tourists never see. From hidden courtyards to local artisan shops, this tour takes you off the beaten path through the Eternal City.',
                'highlights' => ['Hidden courtyards', 'Local artisan shops', 'Ancient Roman stories', 'Off the beaten path'],
                'inclusions' => ['Expert local guide', 'Headset for clear commentary', 'Small group experience'],
                'exclusions' => ['Hotel pickup', 'Food and drinks', 'Gratuities'],
                'meeting_point' => 'Colosseum main entrance',
                'cancellation_policy' => 'Free cancellation up to 24 hours before the tour.',
            ],
            'es' => [
                'title' => 'Joyas Ocultas de Roma - Tour a Pie',
                'description' => 'Descubre los rincones secretos de Roma que la mayoría de turistas nunca ven.',
                'highlights' => ['Patiios ocultos', 'Tiendas de artesanos locales', 'Historias romanas antiguas'],
                'inclusions' => ['Guía local experto', 'Auricular', 'Experiencia en grupo pequeño'],
                'exclusions' => ['Recogida en hotel', 'Comida y bebida'],
                'meeting_point' => 'Entrada principal del Coliseo',
                'cancellation_policy' => 'Cancelación gratuita hasta 24 horas antes.',
            ],
            'it' => [
                'title' => 'Gioielli Nascosti di Roma - Tour a Piedi',
                'description' => 'Scopri gli angoli segreti di Roma che la maggior parte dei turisti non vede mai.',
                'highlights' => ['Corti nascoste', 'Botteghe artigiane', 'Storie romane antiche'],
                'inclusions' => ['Guida locale esperta', 'Auricolare', 'Esperienza in piccolo gruppo'],
                'exclusions' => ['Pick-up in hotel', 'Cibo e bevande'],
                'meeting_point' => 'Entrata principale del Colosseo',
                'cancellation_policy' => 'Cancellazione gratuita fino a 24 ore prima.',
            ],
        ]);

        $pendingTour = $this->createTourWithTranslations($partner, $foodCategory, [
            'slug' => 'trastevere-food-wine-experience',
            'location' => 'Rome, Italy',
            'location_slug' => 'rome-italy',
            'duration_minutes' => 240,
            'duration_label' => '4 hours',
            'group_size_min' => 1,
            'group_size_max' => 12,
            'price_amount' => 8500, // €85.00 in cents
            'status' => 'pending_review',
            'cover_image_url' => 'https://cdn.bookly.test/tours/trastevere-cover.jpg',
            'is_featured' => false,
            'submitted_at' => now()->subDays(2),
            'price_from' => 85.00,
            'currency' => 'EUR',
        ], [
            'en' => [
                'title' => 'Trastevere Food & Wine Experience',
                'description' => 'Explore the culinary heart of Rome\'s most charming neighborhood. Taste traditional Roman dishes and sample local wines.',
                'highlights' => ['Family-run trattorias', 'Historic market', 'Gelato workshop', 'Local wine tasting'],
                'inclusions' => ['Food tastings', 'Wine samples', 'Expert food guide'],
                'exclusions' => ['Hotel pickup', 'Extra beverages'],
                'meeting_point' => 'Piazza Santa Maria in Trastevere',
                'cancellation_policy' => 'Free cancellation up to 48 hours before.',
            ],
        ]);

        $draftTour = $this->createTourWithTranslations($partner, $adventureCategory, [
            'slug' => 'ancient-rome-night-tour',
            'location' => 'Rome, Italy',
            'location_slug' => 'rome-italy',
            'duration_minutes' => 120,
            'duration_label' => '2 hours',
            'group_size_min' => 2,
            'group_size_max' => 15,
            'price_amount' => 5500, // €55.00 in cents
            'status' => 'draft',
            'cover_image_url' => 'https://cdn.bookly.test/tours/rome-night-cover.jpg',
            'is_featured' => false,
            'price_from' => 55.00,
            'currency' => 'EUR',
        ], [
            'en' => [
                'title' => 'Ancient Rome Night Tour',
                'description' => 'Experience the magic of Rome\'s ancient monuments illuminated at night.',
                'highlights' => ['Roman Forum at night', 'Colosseum illumination', 'Imperial Forums', 'Photo opportunities'],
                'inclusions' => ['Historian guide', 'Headset'],
                'exclusions' => ['Hotel pickup', 'Drinks'],
                'meeting_point' => 'Piazza del Campidoglio',
                'cancellation_policy' => 'Free cancellation up to 24 hours before.',
            ],
        ]);

        // Add pricing tiers to published tour
        $this->createPricingTiers($publishedTour);

        // Add availability rules to published tour
        $this->createAvailabilityRules($publishedTour);

        // Add availability exceptions to published tour
        $this->createAvailabilityExceptions($publishedTour);

        // Add tour media to published tour
        $this->createTourMedia($publishedTour);

        // Create draft for the pending tour
        TourDraft::create([
            'tour_id' => $pendingTour->id,
            'partner_id' => $partner->id,
            'payload' => [
                'title' => 'Trastevere Food & Wine Experience - Updated',
                'description' => 'Updated description with more stops.',
                'price_from' => 90.00,
            ],
            'status' => 'pending_review',
            'auto_saved_at' => now()->subHour(),
        ]);

        // Create sample notifications
        $this->createNotifications($partner);

        $this->command->info('PartnerSeeder: Created partner user, profile, settings, tours, and notifications.');
    }

    private function seedCategories(): void
    {
        $categories = [
            ['name' => 'Walking Tours', 'slug' => 'walking', 'description' => 'Explore cities on foot with expert guides', 'display_order' => 1, 'is_active' => true],
            ['name' => 'Food & Wine', 'slug' => 'food', 'description' => 'Taste local cuisine and discover culinary traditions', 'display_order' => 2, 'is_active' => true],
            ['name' => 'Adventure', 'slug' => 'adventure', 'description' => 'Thrilling outdoor experiences and activities', 'display_order' => 3, 'is_active' => true],
            ['name' => 'Cultural', 'slug' => 'cultural', 'description' => 'Immerse yourself in art, history, and local traditions', 'display_order' => 4, 'is_active' => true],
            ['name' => 'Water Sports', 'slug' => 'water-sports', 'description' => 'Diving, sailing, and water adventures', 'display_order' => 5, 'is_active' => true],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['slug' => $cat['slug']],
                $cat
            );
        }
    }

    private function createTourWithTranslations(Partner $partner, ?Category $category, array $attributes, array $translations): Tour
    {
        $tour = Tour::create(array_merge([
            'partner_id' => $partner->id,
            'category_id' => $category?->id,
            'itinerary' => null,
            'inclusions' => null,
        ], $attributes));

        foreach ($translations as $locale => $data) {
            TourTranslation::create([
                'tour_id' => $tour->id,
                'locale' => $locale,
                'title' => $data['title'],
                'description' => $data['description'],
                'highlights' => $data['highlights'] ?? [],
                'inclusions' => $data['inclusions'] ?? [],
                'exclusions' => $data['exclusions'] ?? [],
                'meeting_point' => $data['meeting_point'] ?? null,
                'cancellation_policy' => $data['cancellation_policy'] ?? null,
            ]);
        }

        return $tour;
    }

    private function createPricingTiers(Tour $tour): void
    {
        PricingTier::create([
            'tour_id' => $tour->id,
            'name' => 'Adult',
            'price' => 45.00,
            'min_participants' => 1,
            'max_participants' => null,
        ]);

        PricingTier::create([
            'tour_id' => $tour->id,
            'name' => 'Child',
            'price' => 25.00,
            'min_participants' => 1,
            'max_participants' => null,
        ]);

        PricingTier::create([
            'tour_id' => $tour->id,
            'name' => 'Senior',
            'price' => 35.00,
            'min_participants' => 1,
            'max_participants' => null,
        ]);
    }

    private function createAvailabilityRules(Tour $tour): void
    {
        AvailabilityRule::create([
            'tour_id' => $tour->id,
            'rule_type' => 'weekly',
            'days_of_week' => ['mon', 'wed', 'fri', 'sat'],
            'start_time' => '09:00:00',
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'capacity' => 20,
        ]);

        AvailabilityRule::create([
            'tour_id' => $tour->id,
            'rule_type' => 'daily',
            'days_of_week' => null,
            'start_time' => '14:00:00',
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'capacity' => 15,
        ]);
    }

    private function createAvailabilityExceptions(Tour $tour): void
    {
        AvailabilityException::create([
            'tour_id' => $tour->id,
            'exception_type' => 'blackout',
            'date' => now()->addMonths(2)->startOfMonth()->toDateString(),
            'start_time' => null,
            'capacity' => null,
            'price_multiplier' => 1.00,
            'note' => 'National holiday - no tours',
        ]);

        AvailabilityException::create([
            'tour_id' => $tour->id,
            'exception_type' => 'specific',
            'date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '10:00:00',
            'capacity' => 25,
            'price_multiplier' => 1.20,
            'note' => 'Holiday special - extended capacity',
        ]);
    }

    private function createTourMedia(Tour $tour): void
    {
        TourMedia::create([
            'tour_id' => $tour->id,
            'type' => 'cover',
            'url' => 'https://cdn.bookly.test/tours/rome-cover.jpg',
            'thumbnail_url' => 'https://cdn.bookly.test/tours/rome-cover-thumb.jpg',
            'sort_order' => 0,
        ]);

        for ($i = 1; $i <= 4; $i++) {
            TourMedia::create([
                'tour_id' => $tour->id,
                'type' => 'gallery',
                'url' => "https://cdn.bookly.test/tours/rome-gallery-{$i}.jpg",
                'thumbnail_url' => "https://cdn.bookly.test/tours/rome-gallery-{$i}-thumb.jpg",
                'sort_order' => $i,
            ]);
        }
    }

    private function createNotifications(Partner $partner): void
    {
        Notification::create([
            'partner_id' => $partner->id,
            'type' => 'new_booking',
            'title' => 'New Booking Received',
            'body' => 'A traveler has booked your Hidden Gems of Rome Walking Tour.',
            'data' => ['booking_id' => 1, 'tour_id' => 1],
            'read_at' => null,
        ]);

        Notification::create([
            'partner_id' => $partner->id,
            'type' => 'review_received',
            'title' => 'New Review',
            'body' => 'A traveler left a 5-star review on your Hidden Gems of Rome tour.',
            'data' => ['review_id' => 1, 'tour_id' => 1],
            'read_at' => now(),
        ]);

        Notification::create([
            'partner_id' => $partner->id,
            'type' => 'tour_approved',
            'title' => 'Tour Approved',
            'body' => 'Your "Hidden Gems of Rome Walking Tour" has been approved and is now live.',
            'data' => ['tour_id' => 1],
            'read_at' => now(),
        ]);

        Notification::create([
            'partner_id' => $partner->id,
            'type' => 'payment_status',
            'title' => 'Payment Received',
            'body' => 'Payment of €45.00 has been confirmed for booking #BK-001.',
            'data' => ['booking_id' => 1],
            'read_at' => null,
        ]);
    }
}