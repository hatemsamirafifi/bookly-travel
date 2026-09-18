<?php

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

/*
 * H-01 — API auth error consistency. Unauthenticated requests to protected
 * API surfaces (traveler, partner, admin) must receive a JSON 401 with the
 * canonical body `{"message":"Unauthenticated."}` — both when the client
 * sends Accept: application/json and when it sends no Accept header at all
 * (previously the latter fell through to Laravel's redirect-to-login,
 * yielding a text/html 301 instead of 401 JSON).
 */

it('returns 401 json for unauthenticated public booking request with accept json', function () {
    $response = getJson('/api/public/traveler/bookings');

    $response->assertStatus(401)
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('returns 401 json for unauthenticated public booking request without accept header', function () {
    $response = get('/api/public/traveler/bookings');

    $response->assertStatus(401)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('returns 401 json for unauthenticated partner tours request with accept json', function () {
    $response = getJson('/api/partner/tours');

    $response->assertStatus(401)
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('returns 401 json for unauthenticated partner tours request without accept header', function () {
    $response = get('/api/partner/tours');

    $response->assertStatus(401)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('returns 401 json for unauthenticated admin audit request with accept json', function () {
    $response = getJson('/api/admin/audit/bookings');

    $response->assertStatus(401)
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('returns 401 json for unauthenticated admin audit request without accept header', function () {
    $response = get('/api/admin/audit/bookings');

    $response->assertStatus(401)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson(['message' => 'Unauthenticated.']);
});
