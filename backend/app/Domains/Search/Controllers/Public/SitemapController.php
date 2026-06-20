<?php

namespace App\Domains\Search\Controllers\Public;

use App\Models\Category;
use App\Models\Tour;
use Illuminate\Http\Response;

class SitemapController
{
    public function index(): Response
    {
        $baseUrl = config('app.url', 'https://bookly.com');

        $tours = Tour::where('status', 'published')->get();
        $categories = Category::where('is_active', true)->get();
        $destinations = Tour::where('status', 'published')
            ->select('location_slug')
            ->distinct()
            ->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        // Homepage
        $xml .= $this->renderUrl("{$baseUrl}/en", [
            'en' => "{$baseUrl}/en",
            'es' => "{$baseUrl}/es",
            'it' => "{$baseUrl}/it",
        ], 'daily', '1.0');

        // Tour detail pages
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

        // Category pages
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

        // Destination pages
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

        return response($xml, 200)
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=3600');
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
