<?php

use App\Domains\Partner\Models\Partner;
use App\Mail\PartnerRejectedMail;
use App\Mail\PartnerSuspendedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('escapes html entities in PartnerRejectedMail for all locales', function (string $locale) {
    $user = User::factory()->partner()->create(['locale' => $locale]);
    $partner = Partner::create([
        'user_id' => $user->id,
        'role' => 'partner',
        'onboarding_status' => 'rejected',
        'is_active' => false,
    ]);
    $partner->profile()->create(['company_name' => 'Acme Inc', 'contact_email' => 'acme@example.com']);

    $xssPayload = '<script>alert("xss")</script><b>Dangerous Reason</b>';
    $mailable = new PartnerRejectedMail($partner, $xssPayload);

    $rendered = $mailable->render();

    expect($rendered)->not->toContain('<script>alert("xss")</script>')
        ->and($rendered)->not->toContain('<b>Dangerous Reason</b>')
        ->and($rendered)->toContain(e($xssPayload));
})->with(['en', 'es', 'it']);

it('escapes html entities in PartnerSuspendedMail for all locales', function (string $locale) {
    $user = User::factory()->partner()->create(['locale' => $locale]);
    $partner = Partner::create([
        'user_id' => $user->id,
        'role' => 'partner',
        'onboarding_status' => 'suspended',
        'is_active' => false,
    ]);
    $partner->profile()->create(['company_name' => 'Acme Inc', 'contact_email' => 'acme@example.com']);

    $xssPayload = '<img src=x onerror=alert(1)><em>Suspension</em>';
    $mailable = new PartnerSuspendedMail($partner, $xssPayload);

    $rendered = $mailable->render();

    expect($rendered)->not->toContain('<img src=x onerror=alert(1)>')
        ->and($rendered)->not->toContain('<em>Suspension</em>')
        ->and($rendered)->toContain(e($xssPayload));
})->with(['en', 'es', 'it']);
