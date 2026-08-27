<?php

namespace Tests\Feature\Blog;

use App\Domains\Blog\Actions\UpdateBlogPostAction;
use App\Domains\Blog\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = makeAdmin();
        $this->regularUser = User::factory()->traveler()->create();
    }

    public function test_admin_can_create_and_publish_blog_post(): void
    {
        $action = app(UpdateBlogPostAction::class);
        $category = makeBlogCategory();

        $payload = [
            'author_id' => $this->admin->id,
            'blog_category_id' => $category->id,
            'title' => ['en' => 'Authorized Article', 'es' => 'Articulo Autorizado'],
            'body' => ['en' => '<p>Authoritative content</p>', 'es' => '<p>Contenido</p>'],
            'status' => 'published',
        ];

        $post = $action->execute($this->admin, $payload, null);

        $this->assertInstanceOf(BlogPost::class, $post);
        $this->assertEquals('published', $post->status);
        $this->assertNotNull($post->published_at);
    }

    public function test_guest_cannot_access_filament_blog_resources(): void
    {
        $response = $this->get('/admin/blog-posts');
        $response->assertRedirect('/admin/login');
    }
}
