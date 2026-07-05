<?php

namespace App\Domains\Admin\Settings;

use Spatie\LaravelSettings\Settings;

class ContactSettings extends Settings
{
    public string $contact_email = 'hello@bookly.test';
    public ?string $contact_phone = null;
    public ?string $contact_address = null;
    public ?string $business_hours = null;
    public array $social_links = [];

    public static function group(): string
    {
        return 'contact';
    }
}