<?php

namespace Tests\Feature\Blog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BlogSitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_sitemap_includes_blog_index_posts_and_categories(): void
    {
        $publishedPost = makeBlogPost([
            'status' => 'published',
            'slug' => 'hidden-gems-rome',
            'published_at' => now()->subDay(),
        ]);

        $draftPost = makeBlogPost([
            'status' => 'draft',
            'slug' => 'draft-post-ignore',
        ]);

        $category = makeBlogCategory([
            'slug' => 'destinations',
            'is_active' => true,
        ]);

        $response = $this->get('/api/public/sitemap.xml');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/xml');

        $xml = $response->getContent();

        // Check blog index is present
        $this->assertStringContainsString('/en/blog', $xml);
        $this->assertStringContainsString('/es/blog', $xml);
        $this->assertStringContainsString('/it/blog', $xml);

        // Check published post is present
        $this->assertStringContainsString("/en/blog/{$publishedPost->slug}", $xml);
        $this->assertStringContainsString("/es/blog/{$publishedPost->slug}", $xml);

        // Check draft post is excluded
        $this->assertStringNotContainsString("/blog/{$draftPost->slug}", $xml);

        // Check category is present
        $this->assertStringContainsString("/en/blog/category/{$category->slug}", $xml);
    }
}
