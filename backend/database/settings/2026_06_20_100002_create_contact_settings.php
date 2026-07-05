<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('contact.contact_email', 'hello@bookly.test');
        $this->migrator->add('contact.contact_phone', null);
        $this->migrator->add('contact.contact_address', null);
        $this->migrator->add('contact.business_hours', null);
        $this->migrator->add('contact.social_links', []);
    }
};