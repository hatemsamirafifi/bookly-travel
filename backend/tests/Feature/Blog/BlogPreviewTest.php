<?php

namespace Tests\Feature\Blog;

use App\Domains\Blog\Actions\GeneratePreviewTokenAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = makeAdmin();
    }

    public function test_draft_article_accessible_with_valid_hmac_preview_token(): void
    {
        $draft = makeBlogPost([
            'status' => 'draft',
            'slug' => 'draft-hidden-destination',
            'author_id' => $this->admin->id,
            'title' => ['en' => 'Draft Hidden Destination', 'es' => 'Destino Oculto'],
            'body' => ['en' => '<p>Draft body content</p>', 'es' => '<p>Contenido borrador</p>'],
        ]);

        $tokenGen = app(GeneratePreviewTokenAction::class);
        $tokenData = $tokenGen->execute($draft->slug);

        $response = $this->getJson("/api/public/blog/{$draft->slug}/preview?token=" . urlencode($tokenData['token']) . '&locale=en');

        $response->assertStatus(200)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private')
            ->assertJsonPath('data.slug', $draft->slug)
            ->assertJsonPath('data.is_preview', true);
    }

    public function test_preview_rejected_with_expired_token(): void
    {
        $draft = makeBlogPost([
            'status' => 'draft',
            'slug' => 'draft-expired-test',
            'author_id' => $this->admin->id,
        ]);

        $tokenGen = app(GeneratePreviewTokenAction::class);
        $tokenData = $tokenGen->execute($draft->slug);

        // Travel 31 minutes into future past 30-min TTL
        $this->travel(31)->minutes();

        $response = $this->getJson("/api/public/blog/{$draft->slug}/preview?token=" . urlencode($tokenData['token']) . '&locale=en');

        $response->assertStatus(403)
            ->assertJson(['message' => 'Preview token has expired.']);
    }

    public function test_preview_rejected_with_tampered_signature_or_slug(): void
    {
        $draft = makeBlogPost([
            'status' => 'draft',
            'slug' => 'draft-tamper-test',
            'author_id' => $this->admin->id,
        ]);

        $tokenGen = app(GeneratePreviewTokenAction::class);
        $tokenData = $tokenGen->execute($draft->slug);

        // Tamper with slug inside token
        $decoded = base64_decode($tokenData['token']);
        $parts = explode('|', $decoded);
        $tamperedPayload = "different-slug|{$parts[1]}|{$parts[2]}";
        $tamperedToken = base64_encode($tamperedPayload);

        $response = $this->getJson("/api/public/blog/{$draft->slug}/preview?token=" . urlencode($tamperedToken) . '&locale=en');

        $response->assertStatus(403);
    }

    public function test_preview_rejected_without_token(): void
    {
        $draft = makeBlogPost([
            'status' => 'draft',
            'slug' => 'draft-no-token',
            'author_id' => $this->admin->id,
        ]);

        $response = $this->getJson("/api/public/blog/{$draft->slug}/preview?locale=en");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }
}
