<?php

namespace App\Domains\Search\Controllers\Public;

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

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        // Homepage
        $xml .= $this->renderUrl("{$baseUrl}/en", [
            'en' => "{$baseUrl}/en",
            'es' => "{$baseUrl}/es",
            'it' => "{$baseUrl}/it",
        ], 'daily', '1.0');

        // Tour detail pages — stream in chunks to bound memory. chunkById()
        // paginates on the `id` column, so `id` MUST be in the select list or
        // it aborts (RuntimeException) the moment any row exists.
        Tour::where('status', 'published')
            ->select(['id', 'slug'])
            ->orderBy('id')
            ->chunkById(500, function ($tours) use (&$xml, $baseUrl) {
                foreach ($tours as $tour) {
                    $xml .= $this->renderUrl(
                        "{$baseUrl}/en/tours/{$tour->slug}",
                        [
                            'en' => "{$baseUrl}/en/tours/{$tour->slug}",
                            'es' => "{$baseUrl}/es/tours/{$tour->slug}",
                            'it' => "{$baseUrl}/it/tours/{$tour->slug}",
                        ],
                        'weekly',
                        '0.9'
                    );
                }
            });

        // Category pages — bounded taxonomy (30–50 per spec), select slug only.
        $categories = Category::where('is_active', true)->select('slug')->get();
        foreach ($categories as $category) {
            $xml .= $this->renderUrl(
                "{$baseUrl}/en/categories/{$category->slug}",
                [
                    'en' => "{$baseUrl}/en/categories/{$category->slug}",
                    'es' => "{$baseUrl}/es/categories/{$category->slug}",
                    'it' => "{$baseUrl}/it/categories/{$category->slug}",
                ],
                'daily',
                '0.7'
            );
        }

        // Destination pages — distinct locations, select slug only.
        $destinations = Tour::where('status', 'published')
            ->select('location_slug')
            ->distinct()
            ->get();
        foreach ($destinations as $dest) {
            $xml .= $this->renderUrl(
                "{$baseUrl}/en/destinations/{$dest->location_slug}",
                [
                    'en' => "{$baseUrl}/en/destinations/{$dest->location_slug}",
                    'es' => "{$baseUrl}/es/destinations/{$dest->location_slug}",
                    'it' => "{$baseUrl}/it/destinations/{$dest->location_slug}",
                ],
                'weekly',
                '0.7'
            );
        }

        $xml .= '</urlset>';

        return $xml;
    }

    protected function renderUrl(string $loc, array $alternates, string $changefreq, string $priority): string
    {
        $xml = "  <url>\n";
        $xml .= '    <loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n";
        foreach ($alternates as $lang => $href) {
            $xml .= '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($lang, ENT_XML1, 'UTF-8') . '" href="' . htmlspecialchars($href, ENT_XML1, 'UTF-8') . "\"/>\n";
        }
        $xml .= '    <changefreq>' . htmlspecialchars($changefreq, ENT_XML1, 'UTF-8') . "</changefreq>\n";
        $xml .= '    <priority>' . htmlspecialchars($priority, ENT_XML1, 'UTF-8') . "</priority>\n";
        $xml .= "  </url>\n";

        return $xml;
    }
}
