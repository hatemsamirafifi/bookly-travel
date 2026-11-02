<?php

namespace App\Domains\CMS\Controllers\Public;

use App\Domains\Admin\Models\StaticPage;
use App\Domains\CMS\Services\HtmlSanitizerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public static-page JSON API (Admin 2.0 Phase G, T-G05).
 *
 * `GET /api/public/pages/{slug}?locale=es` returns the published page in the
 * requested platform locale. Missing or partial translations fall back
 * atomically to English and are flagged in `translation_warning`. Meta fields
 * are omitted from the payload when no value is stored.
 */
final class StaticPageController extends Controller
{
    public function __construct(
        private readonly HtmlSanitizerService $sanitizer,
    ) {}

    public function show(Request $request, string $slug): JsonResponse
    {
        $locale = $request->query('locale', 'en');
        $locale = is_string($locale) ? $locale : 'en';

        if (! in_array($locale, StaticPage::LOCALES, true)) {
            return response()->json([
                'message' => 'Unsupported locale. Supported locales: en, es, it.',
            ], 422);
        }

        $page = StaticPage::query()
            ->where('slug', $slug)
            ->where('status', StaticPage::STATUS_PUBLISHED)
            ->first();

        if (! $page) {
            return response()->json(['message' => 'Page not found.'], 404);
        }

        $exactContent = $page->hasCompleteLocale($locale);
        $effectiveLocale = $exactContent ? $locale : 'en';
        $content = $page->contentFor($effectiveLocale);
        $sanitizedBody = $content->body !== null
            ? $this->sanitizer->sanitize($content->body)
            : null;

        $payload = [
            'slug' => $page->slug,
            'locale' => $locale,
            'title' => $content->title,
            'body' => $sanitizedBody,
            'published_at' => $page->published_at?->toIso8601String(),
        ];

        $metaTitle = $page->rawContentFor('meta_title', $effectiveLocale)
            ?? $page->rawContentFor('meta_title', 'en');
        if ($metaTitle !== null) {
            $payload['meta_title'] = $metaTitle;
        }

        $metaDescription = $page->rawContentFor('meta_description', $effectiveLocale)
            ?? $page->rawContentFor('meta_description', 'en');
        if ($metaDescription !== null) {
            $payload['meta_description'] = $metaDescription;
        }

        if (filled($page->canonical_url)) {
            $payload['canonical_url'] = $page->canonical_url;
        }

        $ogImageUrl = $page->ogImage?->publicUrl();
        if (filled($ogImageUrl)) {
            $payload['og_image'] = $ogImageUrl;
        }

        if (! $exactContent) {
            if ($page->rawContentFor('title', 'en') !== null || $page->rawContentFor('body', 'en') !== null) {
                $payload['translation_warning'] = "Static page has no {$locale} content; falling back to English.";
            } else {
                $payload['translation_warning'] = 'Static page has no content for this locale; falling back.';
            }
        }

        return response()->json(['data' => $payload]);
    }
}
