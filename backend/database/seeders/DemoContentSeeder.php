<?php

namespace Database\Seeders;

use App\Domains\Partner\Models\AvailabilityRule;
use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\PartnerProfile;
use App\Domains\Partner\Models\PartnerSettings;
use App\Domains\Partner\Models\PricingTier;
use App\Domains\Partner\Models\TourMedia;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoContentSeeder extends Seeder
{
    /**
     * Seed additional demo content: more tours, destinations, partners
     */
    public function run(): void
    {
        $this->seedMoreCategories();
        $this->seedMorePartners();
        
        $this->command->info('✓ Added demo tours, destinations, and partners');
    }

    private function seedMoreCategories(): void
    {
        $categories = [
            [
                'name' => 'Beach & Water Sports',
                'slug' => 'beach-water',
                'description' => 'Enjoy beaches, snorkeling, diving, and water activities',
                'image_url' => 'http://cdn.bookly.test/categories/beach.jpg',
                'display_order' => 6,
                'is_active' => true,
            ],
            [
                'name' => 'Museums & Art',
                'slug' => 'museums-art',
                'description' => 'Explore world-class museums and art galleries',
                'image_url' => 'http://cdn.bookly.test/categories/museums.jpg',
                'display_order' => 7,
                'is_active' => true,
            ],
            [
                'name' => 'Nature & Wildlife',
                'slug' => 'nature-wildlife',
                'description' => 'Discover natural wonders and wildlife experiences',
                'image_url' => 'http://cdn.bookly.test/categories/nature.jpg',
                'display_order' => 8,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }

    private function seedMorePartners(): void
    {
        // Partner 2: Venice Tours
        $venicePartner = $this->createPartner(
            'venice@bookly.test',
            'Venice Explorer',
            [
                'company_name' => 'Venice Dream Tours',
                'business_description' => 'Authentic Venetian experiences with local guides',
                'contact_email' => 'info@venicedream.example',
                'contact_phone' => '+39 041 234 5678',
                'website' => 'https://venicedream.example',
                'business_address' => [
                    'street' => 'San Marco 123',
                    'city' => 'Venice',
                    'country' => 'IT',
                    'postal_code' => '30124',
                ],
            ]
        );

        // Partner 3: Barcelona Adventures
        $barcelonaPartner = $this->createPartner(
            'barcelona@bookly.test',
            'Barcelona Guide',
            [
                'company_name' => 'Barcelona Adventure Co',
                'business_description' => 'Discover the best of Catalonia',
                'contact_email' => 'hello@bcnadventure.example',
                'contact_phone' => '+34 93 123 4567',
                'website' => 'https://bcnadventure.example',
                'business_address' => [
                    'street' => 'Passeig de Gràcia 45',
                    'city' => 'Barcelona',
                    'country' => 'ES',
                    'postal_code' => '08007',
                ],
            ]
        );

        // Partner 4: Paris Tours
        $parisPartner = $this->createPartner(
            'paris@bookly.test',
            'Paris Expert',
            [
                'company_name' => 'Paris Mystique Tours',
                'business_description' => 'Romance and history in the City of Light',
                'contact_email' => 'bonjour@parismystique.example',
                'contact_phone' => '+33 1 45 67 89 00',
                'website' => 'https://parismystique.example',
                'business_address' => [
                    'street' => '15 Rue de Rivoli',
                    'city' => 'Paris',
                    'country' => 'FR',
                    'postal_code' => '75004',
                ],
            ]
        );

        // Create tours for each partner
        $this->createVeniceTours($venicePartner);
        $this->createBarcelonaTours($barcelonaPartner);
        $this->createParisTours($parisPartner);
    }

    private function createPartner(string $email, string $name, array $profile): Partner
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'partner',
            ]
        );

        $partner = Partner::firstOrCreate(
            ['user_id' => $user->id],
            [
                'onboarding_status' => 'complete',
                'is_active' => true,
            ]
        );

        PartnerProfile::firstOrCreate(
            ['partner_id' => $partner->id],
            array_merge($profile, [
                'payout_holder_name' => explode(' ', $name)[0] . ' ' . substr($email, 0, strpos($email, '@')),
                'payout_bank_name' => 'European Bank',
            ])
        );

        PartnerSettings::firstOrCreate(
            ['partner_id' => $partner->id],
            [
                'notify_new_booking' => true,
                'notify_cancellation' => true,
                'notify_daily_summary' => false,
                'notify_review_received' => true,
                'notify_tour_status_change' => true,
                'locale' => 'en',
            ]
        );

        return $partner;
    }

    private function createVeniceTours(Partner $partner): void
    {
        $category = Category::where('slug', 'walking')->first();
        $waterCategory = Category::where('slug', 'beach-water')->first();

        // Venice Gondola Experience
        $this->createTourWithDetails($partner, $waterCategory, [
            'slug' => 'venice-gondola-ride-sunset',
            'location' => 'Venice, Italy',
            'location_slug' => 'venice-italy',
            'duration_minutes' => 60,
            'duration_label' => '1 hour',
            'group_size_min' => 1,
            'group_size_max' => 6,
            'price_amount' => 12000,
            'status' => 'published',
            'cover_image_url' => 'http://cdn.bookly.test/tours/venice-gondola.jpg',
            'is_featured' => true,
            'published_at' => now()->subDays(15),
        ], [
            'en' => [
                'title' => 'Romantic Venice Gondola Ride at Sunset',
                'description' => 'Experience the magic of Venice with a traditional gondola ride through the canals at sunset. Glide past historic palaces and under romantic bridges.',
                'highlights' => ['Traditional gondola', 'Sunset views', 'Grand Canal', 'Historic bridges'],
                'inclusions' => ['30-minute gondola ride', 'Professional gondolier', 'Route through major canals'],
                'exclusions' => ['Hotel pickup', 'Drinks', 'Serenade (available for extra cost)'],
                'meeting_point' => 'Rialto Bridge gondola station',
                'cancellation_policy' => 'Free cancellation up to 24 hours before the tour.',
            ],
        ]);

        // Venice Walking Tour
        $this->createTourWithDetails($partner, $category, [
            'slug' => 'venice-walking-tour-hidden-canals',
            'location' => 'Venice, Italy',
            'location_slug' => 'venice-italy',
            'duration_minutes' => 150,
            'duration_label' => '2.5 hours',
            'group_size_min' => 1,
            'group_size_max' => 15,
            'price_amount' => 5500,
            'status' => 'published',
            'cover_image_url' => 'http://cdn.bookly.test/tours/venice-walking.jpg',
            'is_featured' => false,
            'published_at' => now()->subDays(10),
        ], [
            'en' => [
                'title' => 'Venice Hidden Canals Walking Tour',
                'description' => 'Discover Venice beyond St. Mark\'s Square. Walk through quiet neighborhoods and hidden canals where locals live.',
                'highlights' => ['Hidden canals', 'Local neighborhoods', 'Venetian architecture', 'Small bridges'],
                'inclusions' => ['Expert guide', 'Small group', 'Walking through 5 districts'],
                'exclusions' => ['Hotel pickup', 'Food and drinks', 'Museum entries'],
                'meeting_point' => 'Campo Santa Maria Formosa',
                'cancellation_policy' => 'Free cancellation up to 24 hours before the tour.',
            ],
        ]);
    }

    private function createBarcelonaTours(Partner $partner): void
    {
        $category = Category::where('slug', 'walking')->first();
        $foodCategory = Category::where('slug', 'food')->first();

        // Barcelona Gaudi Tour
        $this->createTourWithDetails($partner, $category, [
            'slug' => 'barcelona-gaudi-architecture-tour',
            'location' => 'Barcelona, Spain',
            'location_slug' => 'barcelona-spain',
            'duration_minutes' => 240,
            'duration_label' => '4 hours',
            'group_size_min' => 1,
            'group_size_max' => 18,
            'price_amount' => 6500,
            'status' => 'published',
            'cover_image_url' => 'http://cdn.bookly.test/tours/barcelona-gaudi.jpg',
            'is_featured' => true,
            'published_at' => now()->subDays(20),
        ], [
            'en' => [
                'title' => 'Barcelona Gaudí Architecture Masterpieces Tour',
                'description' => 'Explore the incredible works of Antoni Gaudí including Sagrada Familia, Park Güell, and Casa Batlló with skip-the-line access.',
                'highlights' => ['Sagrada Familia interior', 'Park Güell mosaics', 'Casa Batlló facade', 'Modernisme architecture'],
                'inclusions' => ['Expert art historian guide', 'Skip-the-line tickets', 'Headset', 'Small group'],
                'exclusions' => ['Hotel pickup', 'Food and drinks', 'Gratuities'],
                'meeting_point' => 'Sagrada Familia main entrance',
                'cancellation_policy' => 'Free cancellation up to 48 hours before the tour.',
            ],
        ]);

        // Barcelona Tapas Tour
        $this->createTourWithDetails($partner, $foodCategory, [
            'slug' => 'barcelona-tapas-wine-evening-tour',
            'location' => 'Barcelona, Spain',
            'location_slug' => 'barcelona-spain',
            'duration_minutes' => 180,
            'duration_label' => '3 hours',
            'group_size_min' => 2,
            'group_size_max' => 12,
            'price_amount' => 7500,
            'status' => 'published',
            'cover_image_url' => 'http://cdn.bookly.test/tours/barcelona-tapas.jpg',
            'is_featured' => false,
            'published_at' => now()->subDays(8),
        ], [
            'en' => [
                'title' => 'Barcelona Evening Tapas & Wine Tour',
                'description' => 'Discover authentic Barcelona cuisine with a tapas crawl through the Gothic Quarter. Visit 4 traditional bars and taste local wines.',
                'highlights' => ['4 authentic tapas bars', 'Local wines and vermut', 'Gothic Quarter atmosphere', 'Traditional dishes'],
                'inclusions' => ['Food guide', '8-10 tapas dishes', '4 drinks', 'Small group experience'],
                'exclusions' => ['Hotel pickup', 'Additional drinks', 'Gratuities'],
                'meeting_point' => 'Plaça Reial fountain',
                'cancellation_policy' => 'Free cancellation up to 24 hours before the tour.',
            ],
        ]);
    }

    private function createParisTours(Partner $partner): void
    {
        $category = Category::where('slug', 'walking')->first();
        $museumCategory = Category::where('slug', 'museums-art')->first();

        // Paris Louvre Tour
        $this->createTourWithDetails($partner, $museumCategory, [
            'slug' => 'paris-louvre-museum-highlights',
            'location' => 'Paris, France',
            'location_slug' => 'paris-france',
            'duration_minutes' => 180,
            'duration_label' => '3 hours',
            'group_size_min' => 1,
            'group_size_max' => 20,
            'price_amount' => 8900,
            'status' => 'published',
            'cover_image_url' => 'http://cdn.bookly.test/tours/paris-louvre.jpg',
            'is_featured' => true,
            'published_at' => now()->subDays(25),
        ], [
            'en' => [
                'title' => 'Louvre Museum Masterpieces Tour',
                'description' => 'Skip the line and discover the Louvre\'s greatest treasures including Mona Lisa, Venus de Milo, and Winged Victory.',
                'highlights' => ['Mona Lisa', 'Venus de Milo', 'Winged Victory', 'Egyptian antiquities'],
                'inclusions' => ['Skip-the-line entry', 'Expert art guide', 'Headset', '3-hour guided tour'],
                'exclusions' => ['Hotel pickup', 'Food and drinks', 'Gratuities'],
                'meeting_point' => 'Louvre Pyramid entrance',
                'cancellation_policy' => 'Free cancellation up to 48 hours before the tour.',
            ],
        ]);

        // Paris Montmartre Tour
        $this->createTourWithDetails($partner, $category, [
            'slug' => 'paris-montmartre-artists-quarter',
            'location' => 'Paris, France',
            'location_slug' => 'paris-france',
            'duration_minutes' => 150,
            'duration_label' => '2.5 hours',
            'group_size_min' => 1,
            'group_size_max' => 15,
            'price_amount' => 4900,
            'status' => 'published',
            'cover_image_url' => 'http://cdn.bookly.test/tours/paris-montmartre.jpg',
            'is_featured' => false,
            'published_at' => now()->subDays(12),
        ], [
            'en' => [
                'title' => 'Montmartre Artists Quarter Walking Tour',
                'description' => 'Explore bohemian Montmartre, home to artists like Picasso and Van Gogh. Visit Sacré-Cœur, Place du Tertre, and hidden vineyards.',
                'highlights' => ['Sacré-Cœur Basilica', 'Place du Tertre artists', 'Moulin Rouge', 'Hidden vineyard'],
                'inclusions' => ['Local guide', 'Small group', 'Walking tour'],
                'exclusions' => ['Hotel pickup', 'Food and drinks', 'Basilica entry fee'],
                'meeting_point' => 'Abbesses Metro station',
                'cancellation_policy' => 'Free cancellation up to 24 hours before the tour.',
            ],
        ]);
    }

    private function createTourWithDetails(Partner $partner, ?Category $category, array $tourData, array $translations): Tour
    {
        if (!$category) {
            $category = Category::where('slug', 'walking')->first();
        }

        $tour = Tour::firstOrCreate(
            ['slug' => $tourData['slug']],
            array_merge($tourData, [
                'partner_id' => $partner->id,
                'category_id' => $category->id,
                'currency' => 'EUR',
                'price_from' => $tourData['price_amount'] / 100,
            ])
        );

        foreach ($translations as $locale => $translation) {
            TourTranslation::firstOrCreate(
                [
                    'tour_id' => $tour->id,
                    'locale' => $locale,
                ],
                $translation
            );
        }

        // Add basic availability rule
        AvailabilityRule::firstOrCreate(
            ['tour_id' => $tour->id],
            [
                'rule_type' => 'recurring',
                'days_of_week' => [1, 2, 3, 4, 5, 6, 0], // All days
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonths(6)->toDateString(),
                'start_time' => '09:00:00',
                'capacity' => $tourData['group_size_max'],
            ]
        );

        return $tour;
    }
}
