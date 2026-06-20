<?php

namespace Database\Seeders;

use App\Domains\Partner\Models\AvailabilityException;
use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\Notification;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PartnerProfile;
use App\Domains\Partner\Models\PartnerSettings;
use App\Domains\Partner\Models\PricingTier;
use App\Domains\Partner\Models\ReviewResponse;
use App\Domains\Partner\Models\TourDraft;
use App\Domains\Partner\Models\TourMedia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PartnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates a partner user with sample profile, tours in various statuses,
     * bookings, reviews, and notifications for development and E2E testing.
     */
    public function run(): void
    {
        // Create partner user
        $user = \App\Models\User::firstOrCreate(
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
                'business_description' => 'Award-winning walking tours through the Eternal City. We bring Rome\'s history to life with expert local guides.',
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

        // Create sample tours in various statuses
        $publishedTour = $this->createTour($partner, [
            'title' => 'Hidden Gems of Rome Walking Tour',
            'description' => 'Discover the secret corners of Rome that most tourists never see. From hidden courtyards to local artisan shops, this tour takes you off the beaten path through the Eternal City. Our expert guides share stories of ancient Rome, Renaissance intrigue, and modern Roman life that bring each stop to life.',
            'category' => 'walking',
            'destination' => 'Rome, Italy',
            'duration_value' => 3,
            'duration_unit' => 'hour',
            'difficulty_level' => 'easy',
            'meeting_point' => 'Colosseum main entrance',
            'status' => 'published',
            'price_from' => 45.00,
            'currency' => 'EUR',
            'published_at' => now()->subDays(30),
        ]);

        $pendingTour = $this->createTour($partner, [
            'title' => 'Trastevere Food & Wine Experience',
            'description' => 'Explore the culinary heart of Rome\'s most charming neighborhood. Taste traditional Roman dishes, sample local wines, and learn about the food culture that has defined Trastevere for centuries. This tour includes stops at family-run trattorias, a historic market, and a gelato workshop where you\'ll see artisan production firsthand.',
            'category' => 'food',
            'destination' => 'Rome, Italy',
            'duration_value' => 4,
            'duration_unit' => 'hour',
            'difficulty_level' => 'easy',
            'meeting_point' => 'Piazza Santa Maria in Trastevere',
            'status' => 'pending_review',
            'price_from' => 85.00,
            'currency' => 'EUR',
            'submitted_at' => now()->subDays(2),
        ]);

        $draftTour = $this->createTour($partner, [
            'title' => 'Ancient Rome Night Tour',
            'description' => 'Experience the magic of Rome\'s ancient monuments illuminated at night. Walk through the Roman Forum, past the Colosseum, and along the Imperial Forums as our historian guide brings the stories of emperors and gladiators to life under the stars. This unique perspective offers incredible photo opportunities and a deeper understanding of Rome\'s dramatic history.',
            'category' => 'adventure',
            'destination' => 'Rome, Italy',
            'duration_value' => 2,
            'duration_unit' => 'hour',
            'difficulty_level' => 'moderate',
            'meeting_point' => 'Piazza del Campidoglio',
            'status' => 'draft',
            'price_from' => 55.00,
            'currency' => 'EUR',
        ]);

        // Add pricing tiers to published tour
        $this->createPricingTiers($publishedTour);

        // Add availability rules to published tour
        $this->createAvailabilityRules($publishedTour);

        // Add availability exceptions to published tour
        $this->createAvailabilityExceptions($publishedTour);

        // Add tour media to published tour
        $this->createTourMedia($publishedTour);

        // Create draft for the pending tour (simulating edit of published tour)
        TourDraft::create([
            'tour_id' => $pendingTour->id,
            'partner_id' => $partner->id,
            'payload' => [
                'title' => 'Trastevere Food & Wine Experience - Updated',
                'description' => $pendingTour->description,
                'price_from' => 90.00,
            ],
            'status' => 'pending_review',
            'auto_saved_at' => now()->subHour(),
        ]);

        // Create sample notifications
        $this->createNotifications($partner);

        $this->command->info('PartnerSeeder: Created partner user, profile, settings, tours, and notifications.');
    }

    private function createTour(Partner $partner, array $attributes): \App\Models\Tour
    {
        // Use the existing Tour model since tours table already exists
        return \App\Models\Tour::create(array_merge([
            'partner_id' => $partner->id,
            'itinerary' => null,
            'inclusions' => null,
        ], $attributes));
    }

    private function createPricingTiers(\App\Models\Tour $tour): void
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

    private function createAvailabilityRules(\App\Models\Tour $tour): void
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

    private function createAvailabilityExceptions(\App\Models\Tour $tour): void
    {
        // Blackout date (e.g., national holiday)
        AvailabilityException::create([
            'tour_id' => $tour->id,
            'exception_type' => 'blackout',
            'date' => now()->addMonths(2)->startOfMonth()->toDateString(),
            'start_time' => null,
            'capacity' => null,
            'price_multiplier' => 1.00,
            'note' => 'National holiday - no tours',
        ]);

        // Specific date with special pricing (holiday surcharge)
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

    private function createTourMedia(\App\Models\Tour $tour): void
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