<?php

namespace App\Domains\Search\Controllers\Public;

use App\Domains\Blog\Models\BlogCategory;
use App\Domains\Blog\Models\BlogPost;
use App\Models\Category;
use App\Models\Tour;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController
{
    /**
     * Sitemap cache TTL in seconds (1 hour). Generation walks the full
     * published-tour catalog, so the rendered XML is cached and reused
     * rather than rebuilt on every request.
     */
    protected const CACHE_TTL = 3600;

    protected const CACHE_KEY = 'bookly:sitemap:xml';

    public function index(): Response
    {
        $xml = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->renderXml());

        return response($xml, 200)
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Render the full sitemap XML. Tours are streamed via chunkById() so a
     * catalog of 5,000–10,000 tours (spec SC-010) never materializes as a
     * single Eloquent collection in memory; only `id` and `slug` are selected.
     */
    protected function renderXml(): string
    {
        $baseUrl = config('app.url', 'https://bookly.com');
        $locales = config('app.supported_locales', ['en', 'es', 'it']);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        // Homepage
        $xml .= $this->renderUrl(
            "{$baseUrl}/{$locales[0]}",
            $this->alternates(fn (string $lang) => "{$baseUrl}/{$lang}", $locales),
            'daily',
            '1.0'
        );

        // Blog Index Page
        $xml .= $this->renderUrl(
            "{$baseUrl}/{$locales[0]}/blog",
            $this->alternates(fn (string $lang) => "{$baseUrl}/{$lang}/blog", $locales),
            'daily',
            '0.8'
        );

        // Tour detail pages — stream in chunks to bound memory. chunkById()
        // paginates on the `id` column, so `id` MUST be in the select list or
        // it aborts (RuntimeException) the moment any row exists.
        Tour::published()
            ->select(['id', 'slug'])
            ->orderBy('id')
            ->chunkById(500, function ($tours) use (&$xml, $baseUrl, $locales) {
                foreach ($tours as $tour) {
                    $xml .= $this->renderUrl(
                        "{$baseUrl}/{$locales[0]}/tours/{$tour->slug}",
                        $this->alternates(fn (string $lang) => "{$baseUrl}/{$lang}/tours/{$tour->slug}", $locales),
                        'weekly',
                        '0.9'
                    );
                }
            });

        // Blog detail pages — stream published posts in chunks
        BlogPost::published()
            ->select(['id', 'slug', 'published_at'])
            ->orderBy('id')
            ->chunkById(500, function ($posts) use (&$xml, $baseUrl, $locales) {
                foreach ($posts as $post) {
                    $xml .= $this->renderUrl(
                        "{$baseUrl}/{$locales[0]}/blog/{$post->slug}",
                        $this->alternates(fn (string $lang) => "{$baseUrl}/{$lang}/blog/{$post->slug}", $locales),
                        'weekly',
                        '0.8',
                        $post->published_at?->toDateString()
                    );
                }
            });

        // Category pages — bounded taxonomy (30–50 per spec), select slug only.
        $categories = Category::where('is_active', true)->select('slug')->get();
        foreach ($categories as $category) {
            $xml .= $this->renderUrl(
                "{$baseUrl}/{$locales[0]}/categories/{$category->slug}",
                $this->alternates(fn (string $lang) => "{$baseUrl}/{$lang}/categories/{$category->slug}", $locales),
                'daily',
                '0.7'
            );
        }

        // Blog Category pages
        $blogCategories = BlogCategory::where('is_active', true)->select('slug')->get();
        foreach ($blogCategories as $bCat) {
            $xml .= $this->renderUrl(
                "{$baseUrl}/{$locales[0]}/blog/category/{$bCat->slug}",
                $this->alternates(fn (string $lang) => "{$baseUrl}/{$lang}/blog/category/{$bCat->slug}", $locales),
                'weekly',
                '0.6'
            );
        }

        // Destination pages — distinct locations, select slug only.
        $destinations = Tour::published()
            ->select('location_slug')
            ->distinct()
            ->get();
        foreach ($destinations as $dest) {
            $xml .= $this->renderUrl(
                "{$baseUrl}/{$locales[0]}/destinations/{$dest->location_slug}",
                $this->alternates(fn (string $lang) => "{$baseUrl}/{$lang}/destinations/{$dest->location_slug}", $locales),
                'weekly',
                '0.7'
            );
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Build the hreflang alternates map for a URL, one entry per supported
     * locale produced by `$urlFor(lang)`.
     */
    protected function alternates(callable $urlFor, array $locales): array
    {
        $map = [];
        foreach ($locales as $lang) {
            $map[$lang] = $urlFor($lang);
        }

        return $map;
    }

    protected function renderUrl(string $loc, array $alternates, string $changefreq, string $priority, ?string $lastmod = null): string
    {
        $xml = "  <url>\n";
        $xml .= '    <loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n";
        foreach ($alternates as $lang => $href) {
            $xml .= '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($lang, ENT_XML1, 'UTF-8') . '" href="' . htmlspecialchars($href, ENT_XML1, 'UTF-8') . "\"/>\n";
        }
        if ($lastmod) {
            $xml .= '    <lastmod>' . htmlspecialchars($lastmod, ENT_XML1, 'UTF-8') . "</lastmod>\n";
        }
        $xml .= '    <changefreq>' . htmlspecialchars($changefreq, ENT_XML1, 'UTF-8') . "</changefreq>\n";
        $xml .= '    <priority>' . htmlspecialchars($priority, ENT_XML1, 'UTF-8') . "</priority>\n";
        $xml .= "  </url>\n";

        return $xml;
    }
}
