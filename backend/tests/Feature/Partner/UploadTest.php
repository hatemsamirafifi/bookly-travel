<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->partner = makePartner();
    $this->token = $this->partner->user->createToken('test', ['partner'])->plainTextToken;
});

it('returns a signed upload URL with a server-generated UUID filename for an authorized partner', function () {
    $response = postJson('/api/partner/uploads/signed-url', [
        'file_type' => 'image/jpeg',
        'file_size' => 102400,
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['signed_url', 'public_url', 'expires_at']);

    $publicUrl = $response->json('public_url');

    // Filename is generated server-side: UUID + extension matched to the MIME type.
    expect($publicUrl)->toMatch('#^https://cdn\.bookly\.test/uploads/[0-9a-fA-F-]{36}\.jpg$#');
    expect($response->json('signed_url'))->toContain('/uploads/');
});

it('maps image/png to a .png extension', function () {
    $response = postJson('/api/partner/uploads/signed-url', [
        'file_type' => 'image/png',
        'file_size' => 204800,
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200);
    expect($response->json('public_url'))->toMatch('#\.png$#');
});

it('rejects unauthenticated requests with 401', function () {
    postJson('/api/partner/uploads/signed-url', [
        'file_type' => 'image/jpeg',
        'file_size' => 102400,
    ])->assertStatus(401);
});

it('does not disclose the endpoint to non-partners with 404', function () {
    $traveler = User::factory()->traveler()->create();
    $travelerToken = $traveler->createToken('test')->plainTextToken;

    // PartnerRoleMiddleware intentionally returns 404 (not 403) for
    // non-partner roles so travelers cannot enumerate partner routes.
    postJson('/api/partner/uploads/signed-url', [
        'file_type' => 'image/jpeg',
        'file_size' => 102400,
    ], [
        'Authorization' => 'Bearer ' . $travelerToken,
    ])->assertStatus(404);
});

it('rejects files exceeding 5,242,880 bytes with 422', function () {
    postJson('/api/partner/uploads/signed-url', [
        'file_type' => 'image/jpeg',
        'file_size' => 5242881,
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['file_size']);
});

it('rejects disallowed MIME types with 422', function ($mime) {
    postJson('/api/partner/uploads/signed-url', [
        'file_type' => $mime,
        'file_size' => 1024,
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['file_type']);
})->with([
    'php script' => ['text/php'],
    'php application' => ['application/x-php'],
    'svg with script potential' => ['image/svg+xml'],
    'html' => ['text/html'],
    'octet-stream' => ['application/octet-stream'],
]);

it('ignores client-supplied paths and filenames when generating the upload URL', function () {
    $response = postJson('/api/partner/uploads/signed-url', [
        'file_type' => 'image/jpeg',
        'file_size' => 102400,
        // Traversal / spoof attempts: no such inputs exist on the contract,
        // so they must be ignored — path and filename stay server-generated.
        'path' => '../../etc/passwd',
        'filename' => '../../etc/passwd.jpg',
        'key' => '../../etc/passwd',
    ], [
        'Authorization' => 'Bearer ' . $this->token,
    ]);

    $response->assertStatus(200);

    foreach (['signed_url', 'public_url'] as $field) {
        $url = $response->json($field);
        expect($url)->not->toContain('..')
            ->and($url)->not->toContain('etc/passwd')
            ->and($url)->toMatch('#/uploads/[0-9a-fA-F-]{36}\.jpg#');
    }
});
