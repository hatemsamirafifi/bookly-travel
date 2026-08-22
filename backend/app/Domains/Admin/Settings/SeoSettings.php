<?php

namespace App\Domains\Admin\Settings;

use Spatie\LaravelSettings\Settings;

class SeoSettings extends Settings
{
    public string $default_meta_title = 'Bookly — Tours Marketplace';

    public ?string $default_meta_description = null;

    public ?string $default_og_image = null;

    public ?string $twitter_handle = null;

    public ?string $default_canonical_base = null;

    public bool $sitemap_enabled = true;

    public static function group(): string
    {
        return 'seo';
    }
}
