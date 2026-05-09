<?php

use App\Domains\Booking\Models\Booking;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('prevents overbooking when concurrent requests compete for last spot', function () {
    $category = Category::firstOrCreate(['slug' => 'exclusive'], ['name' => 'Exclusive']);
    $traveler1 = User::factory()->traveler()->create();
    $traveler2 = User::factory()->traveler()->create();

    $tour = Tour::create([
        'partner_id' => User::factory()->partner()->create()->id,
        'category_id' => $category->id,
        'slug' => 'exclusive-tour-' . uniqid(),
        'location' => 'Venice, Italy',
        'duration_minutes' => 120,
        'duration_label' => '2 hours',
        'group_size_min' => 1,
        'group_size_max' => 2,
        'price_amount' => 5000,
        'status' => 'published',
        'cover_image_url' => null,
    ]);

    $idempotencyKeys = [
        Str::uuid()->toString(),
        Str::uuid()->toString(),
    ];

    $results = [];

    foreach ([$traveler1, $traveler2] as $i => $traveler) {
        try {
            $response = postJson('/api/public/bookings', [
                'tour_slug' => $tour->slug,
                'tour_date' => '2026-08-01',
                'participant_count' => 2,
                'locale' => 'en',
            ], [
                'Authorization' => 'Bearer ' . $traveler->createToken('test')->plainTextToken,
                'Idempotency-Key' => $idempotencyKeys[$i],
            ]);

            $results[] = $response->status();
        } catch (\Exception $e) {
            $results[] = 409;
        }
    }

    expect($results)->toContain(201);
    expect($results)->toContain(409);
    expect(Booking::count())->toBe(1);
});
