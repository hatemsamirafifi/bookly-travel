<?php

namespace App\Domains\CMS\Controllers\Public;

use App\Domains\Admin\Models\StaticPage;
use App\Domains\Blog\Services\PreviewTokenService;
use App\Domains\CMS\Services\HtmlSanitizerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Public static-page JSON API (Admin 2.0 Phase G, T-G05).
 *
 * `GET /api/public/pages/{slug}?locale=es` returns the published page in the
 * requested platform locale. Missing or partial translations fall back
 * atomically to English and are flagged in `translation_warning`. Meta fields
 * are omitted from the payload when no value is stored.
 *
 * A signed `?preview_token=` (validated against the requested slug via
 * `PreviewTokenService`) temporarily lifts the published-only filter so
 * draft/scheduled pages can be previewed; an invalid token never leaks
 * draft content — it falls back to the published-only behaviour (404).
 */
final class StaticPageController extends Controller
{
    public function __construct(
        private readonly HtmlSanitizerService $sanitizer,
        private readonly PreviewTokenService $previewTokens,
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

        $allowUnpublished = $this->previewTokenIsValid($request, $slug);

        $query = StaticPage::query()
            ->where('slug', $slug);

        if (! $allowUnpublished) {
            $query->where('status', StaticPage::STATUS_PUBLISHED);
        }

        if ($locale !== 'en') {
            $query->with('translationFieldStates');
        }

        $page = $query->first();

        if (! $page) {
            return response()->json(['message' => 'Page not found.'], 404);
        }

        $exactContent = $page->isLocaleEligible($locale);
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

    /**
     * Whether the request carries a signed preview token valid for this slug.
     * Invalid, expired, foreign-slug or missing tokens are all rejected here
     * (never surfaced as an error), so callers fall back to the strict
     * published-only query.
     */
    private function previewTokenIsValid(Request $request, string $slug): bool
    {
        $token = $request->query('preview_token');

        if (! is_string($token) || blank($token)) {
            return false;
        }

        try {
            $this->previewTokens->verify($slug, $token);

            return true;
        } catch (AccessDeniedHttpException) {
            return false;
        }
    }
}
