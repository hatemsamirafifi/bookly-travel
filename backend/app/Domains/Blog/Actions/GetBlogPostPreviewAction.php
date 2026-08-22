<?php

namespace App\Domains\Blog\Actions;

use App\Domains\Blog\Models\BlogPost;
use App\Domains\Blog\Transformers\BlogPostTransformer;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class GetBlogPostPreviewAction
{
    /**
     * Verify HMAC token and return article preview data.
     *
     * @param string $slug
     * @param string $token
     * @param string $locale
     * @return array<string, mixed>
     */
    public function execute(string $slug, string $token, string $locale = 'en'): array
    {
        $validLocales = ['en', 'es', 'it'];
        if (! in_array($locale, $validLocales, true)) {
            throw new UnprocessableEntityHttpException("Invalid locale '{$locale}'. Supported: en, es, it.");
        }

        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            throw new AccessDeniedHttpException('Invalid preview token signature.');
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 3) {
            throw new AccessDeniedHttpException('Invalid preview token structure.');
        }

        [$tokenSlug, $expiresAt, $signature] = $parts;

        // Anti-slug-rebinding: token slug must match requested slug
        if (! hash_equals($tokenSlug, $slug)) {
            throw new AccessDeniedHttpException('Preview token does not match requested article.');
        }

        // Verify signature
        $key = config('app.preview_key') ?: config('app.key');
        $expectedSignature = hash_hmac('sha256', "{$tokenSlug}|{$expiresAt}", (string) $key);

        if (! hash_equals($expectedSignature, $signature)) {
            throw new AccessDeniedHttpException('Preview token signature tampering detected.');
        }

        // Verify expiration
        if (Carbon::now()->timestamp > (int) $expiresAt) {
            throw new AccessDeniedHttpException('Preview token has expired.');
        }

        // Fetch post regardless of status
        $post = BlogPost::with(['author', 'categories', 'tours' => function ($q) {
            $q->where('tours.status', 'published')
              ->with(['partner', 'location', 'pricingRules', 'reviews'])
              ->orderBy('blog_post_tours.sort_order', 'asc');
        }])->where('slug', $slug)->first();

        if (! $post) {
            throw new NotFoundHttpException("Blog post '{$slug}' not found.");
        }

        $data = BlogPostTransformer::transformDetail($post, $locale, true);
        $data['is_preview'] = true;
        unset($data['seo']);

        return $data;
    }
}
