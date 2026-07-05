<?php

namespace App\Domains\Admin\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $site_name = 'Bookly';
    public ?string $site_tagline = null;
    public string $support_email = 'support@bookly.test';
    public string $default_currency = 'USD';
    public string $timezone = 'UTC';
    public bool $maintenance_mode = false;

    public static function group(): string
    {
        return 'general';
    }
}