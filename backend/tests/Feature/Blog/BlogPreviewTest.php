<?php

namespace Tests\Feature\Blog;

use App\Domains\Blog\Actions\GeneratePreviewTokenAction;
use App\Domains\Blog\Models\BlogAuthorProfile;
use App\Domains\Blog\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected BlogAuthorProfile $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->author = BlogAuthorProfile::factory()->create(['is_active' => true]);
    }

    public function test_draft_article_accessible_with_valid_hmac_preview_token(): void
    {
        $draft = BlogPost::factory()->create([
            'status' => 'draft',
            'slug' => 'draft-hidden-destination',
            'author_profile_id' => $this->author->id,
            'title' => ['en' => 'Draft Hidden Destination', 'es' => 'Destino Oculto'],
            'body' => ['en' => 'Draft body content', 'es' => 'Contenido borrador'],
        ]);

        $tokenGen = app(GeneratePreviewTokenAction::class);
        $tokenData = $tokenGen->execute($draft->slug);

        $response = $this->getJson("/api/public/blog/{$draft->slug}/preview?token={$tokenData['token']}");

        $response->assertStatus(200)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.slug', $draft->slug)
            ->assertJsonPath('data.is_preview', true);
    }

    public function test_preview_rejected_with_expired_token(): void
    {
        $draft = BlogPost::factory()->create([
            'status' => 'draft',
            'slug' => 'draft-expired-test',
            'author_profile_id' => $this->author->id,
        ]);

        // Generate token with expired timestamp
        $expiredAt = now()->subMinutes(5)->timestamp;
        $appKey = config('app.key');
        if (str_starts_with($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7));
        }
        $payload = "{$draft->slug}|{$expiredAt}";
        $signature = hash_hmac('sha256', $payload, $appKey);
        $expiredToken = "{$payload}|{$signature}";

        $response = $this->getJson("/api/public/blog/{$draft->slug}/preview?token={$expiredToken}");

        $response->assertStatus(403)
            ->assertJson(['message' => 'Preview token has expired.']);
    }

    public function test_preview_rejected_with_tampered_signature_or_slug(): void
    {
        $draft = BlogPost::factory()->create([
            'status' => 'draft',
            'slug' => 'draft-tamper-test',
            'author_profile_id' => $this->author->id,
        ]);

        $tokenGen = app(GeneratePreviewTokenAction::class);
        $tokenData = $tokenGen->execute($draft->slug);

        // Tamper with slug inside token
        $parts = explode('|', $tokenData['token']);
        $tamperedToken = "different-slug|{$parts[1]}|{$parts[2]}";

        $response = $this->getJson("/api/public/blog/{$draft->slug}/preview?token={$tamperedToken}");

        $response->assertStatus(403);
    }

    public function test_preview_rejected_without_token(): void
    {
        $draft = BlogPost::factory()->create([
            'status' => 'draft',
            'slug' => 'draft-no-token',
            'author_profile_id' => $this->author->id,
        ]);

        $response = $this->getJson("/api/public/blog/{$draft->slug}/preview");

        $response->assertStatus(403);
    }
}
