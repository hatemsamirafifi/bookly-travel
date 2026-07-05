<?php

use App\Domains\Partner\Models\Notification;
use App\Domains\Partner\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->partnerUser = User::factory()->partner()->create();
    $this->partner = Partner::create([
        'user_id' => $this->partnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);

    $this->token = $this->partnerUser->createToken('test', ['partner'])->plainTextToken;
});

it('returns notifications for partner', function () {
    Notification::create([
        'partner_id' => $this->partner->id,
        'type' => 'new_booking',
        'title' => 'New Booking',
        'body' => 'You have a new booking for your tour.',
        'data' => ['booking_id' => 1],
    ]);

    Notification::create([
        'partner_id' => $this->partner->id,
        'type' => 'review_received',
        'title' => 'New Review',
        'body' => 'A traveler left a review on your tour.',
        'data' => ['review_id' => 2],
    ]);

    $response = getJson('/api/partner/notifications', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data', 'meta']);
});

it('can filter unread_only notifications', function () {
    $readNotification = Notification::create([
        'partner_id' => $this->partner->id,
        'type' => 'new_booking',
        'title' => 'Read Notification',
        'body' => 'This notification has been read.',
        'read_at' => now(),
    ]);

    $unreadNotification = Notification::create([
        'partner_id' => $this->partner->id,
        'type' => 'review_received',
        'title' => 'Unread Notification',
        'body' => 'This notification is unread.',
        'read_at' => null,
    ]);

    $response = getJson('/api/partner/notifications?unread_only=true', [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data');

    expect($response->json('data')[0]['title'])->toBe('Unread Notification');
});

it('marks a notification as read', function () {
    $notification = Notification::create([
        'partner_id' => $this->partner->id,
        'type' => 'new_booking',
        'title' => 'New Booking',
        'body' => 'You have a new booking.',
        'read_at' => null,
    ]);

    $response = postJson('/api/partner/notifications/' . $notification->id . '/read', [], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Marked as read.');

    $notification->refresh();
    expect($notification->read_at)->not->toBeNull();
});

it('marks all notifications as read', function () {
    Notification::create([
        'partner_id' => $this->partner->id,
        'type' => 'new_booking',
        'title' => 'Notification 1',
        'body' => 'First notification.',
        'read_at' => null,
    ]);

    Notification::create([
        'partner_id' => $this->partner->id,
        'type' => 'review_received',
        'title' => 'Notification 2',
        'body' => 'Second notification.',
        'read_at' => null,
    ]);

    $response = postJson('/api/partner/notifications/read-all', [], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('message', 'All notifications marked as read.');

    $unreadCount = Notification::where('partner_id', $this->partner->id)
        ->whereNull('read_at')
        ->count();

    expect($unreadCount)->toBe(0);
});

it('partner cannot read another partner notifications', function () {
    $otherPartnerUser = User::factory()->partner()->create();
    $otherPartner = Partner::create([
        'user_id' => $otherPartnerUser->id,
        'role' => 'partner',
        'onboarding_status' => 'complete',
        'is_active' => true,
    ]);
    $otherToken = $otherPartnerUser->createToken('test', ['partner'])->plainTextToken;

    $notification = Notification::create([
        'partner_id' => $this->partner->id,
        'type' => 'new_booking',
        'title' => 'Private Notification',
        'body' => 'This belongs to another partner.',
        'read_at' => null,
    ]);

    // Other partner should not see this partner's notifications
    $response = getJson('/api/partner/notifications', [
        'Authorization' => 'Bearer ' . $otherToken,
    ]);

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');

    // Other partner cannot mark this notification as read
    $markResponse = postJson('/api/partner/notifications/' . $notification->id . '/read', [], [
        'Authorization' => 'Bearer ' . $otherToken,
    ]);

    $markResponse->assertStatus(404);
});

it('returns 401 for unauthenticated request', function () {
    getJson('/api/partner/notifications')
        ->assertStatus(401);
});

it('returns 403 for non-partner role', function () {
    $traveler = User::factory()->traveler()->create();
    $travelerToken = $traveler->createToken('test')->plainTextToken;

    getJson('/api/partner/notifications', [
        'Authorization' => 'Bearer ' . $travelerToken,
    ])->assertStatus(404);
});
