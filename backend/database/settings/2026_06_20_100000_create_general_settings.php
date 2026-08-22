<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.site_name', 'Bookly');
        $this->migrator->add('general.site_tagline', null);
        $this->migrator->add('general.support_email', 'support@bookly.test');
        $this->migrator->add('general.default_currency', 'USD');
        $this->migrator->add('general.timezone', 'UTC');
        $this->migrator->add('general.maintenance_mode', false);
    }
};
