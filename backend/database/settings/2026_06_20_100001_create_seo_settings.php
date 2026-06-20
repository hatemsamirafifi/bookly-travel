<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('seo.default_meta_title', 'Bookly — Tours Marketplace');
        $this->migrator->add('seo.default_meta_description', null);
        $this->migrator->add('seo.default_og_image', null);
        $this->migrator->add('seo.twitter_handle', null);
        $this->migrator->add('seo.default_canonical_base', null);
        $this->migrator->add('seo.sitemap_enabled', true);
    }
};