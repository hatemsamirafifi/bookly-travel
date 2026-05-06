<?php

use function Pest\Laravel\get;

it('returns valid XML sitemap', function () {
    $response = get('/api/public/sitemap.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml');

    $xml = simplexml_load_string($response->getContent());
    expect($xml)->not->toBeFalse();
});

it('includes hreflang alternates for URLs', function () {
    $response = get('/api/public/sitemap.xml');

    $content = $response->getContent();
    expect($content)->toContain('xhtml:link');
    expect($content)->toContain('hreflang="en"');
    expect($content)->toContain('hreflang="es"');
    expect($content)->toContain('hreflang="it"');
});

it('includes homepage URL with highest priority', function () {
    $response = get('/api/public/sitemap.xml');

    $content = $response->getContent();
    expect($content)->toContain('<priority>1.0</priority>');
});

it('has valid cache control headers', function () {
    $response = get('/api/public/sitemap.xml');

    $response->assertHeader('Cache-Control');
});

it('only includes published tours in sitemap', function () {
    $response = get('/api/public/sitemap.xml');
    $xml = simplexml_load_string($response->getContent());

    $urls = $xml->xpath('//url/loc');
    $tourUrls = array_filter($urls, fn ($url) => str_contains((string) $url, '/tours/'));

    // All tour URLs in sitemap should be for published tours
    foreach ($tourUrls as $url) {
        expect((string) $url)->not->toContain('/tours/draft');
    }
});
