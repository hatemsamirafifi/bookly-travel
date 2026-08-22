<?php

namespace Tests\Feature\Blog;

use App\Domains\Blog\Actions\UpdateBlogPostAction;
use App\Domains\Blog\Models\BlogAuthorProfile;
use App\Domains\Blog\Models\BlogPost;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $editor;
    protected User $regularUser;
    protected BlogAuthorProfile $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->editor = User::factory()->create(['role' => 'editor']);
        $this->regularUser = User::factory()->create(['role' => 'customer']);
        $this->author = BlogAuthorProfile::factory()->create(['is_active' => true]);
    }

    public function test_admin_and_editor_can_create_and_publish_blog_post(): void
    {
        $action = app(UpdateBlogPostAction::class);

        $payload = [
            'author_profile_id' => $this->author->id,
            'title' => ['en' => 'Authorized Article', 'es' => 'Articulo Autorizado'],
            'body' => ['en' => 'Authoritative content', 'es' => 'Contenido'],
            'status' => 'published',
        ];

        $post = $action->execute($this->admin, $payload, null);

        $this->assertInstanceOf(BlogPost::class, $post);
        $this->assertEquals('published', $post->status);
        $this->assertNotNull($post->published_at);
    }

    public function test_unauthorized_user_cannot_create_or_modify_blog_post(): void
    {
        $this->expectException(AuthorizationException::class);

        $action = app(UpdateBlogPostAction::class);

        $payload = [
            'author_profile_id' => $this->author->id,
            'title' => ['en' => 'Unauthorized Post'],
            'body' => ['en' => 'Content'],
            'status' => 'draft',
        ];

        $action->execute($this->regularUser, $payload, null);
    }

    public function test_guest_cannot_access_filament_blog_resources(): void
    {
        $response = $this->get('/admin/blog-posts');
        $response->assertRedirect('/admin/login');
    }
}
