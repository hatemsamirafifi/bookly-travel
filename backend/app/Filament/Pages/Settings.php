<?php

namespace App\Filament\Pages;

use App\Domains\Admin\Actions\UpdateSettingsAction;
use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Admin\Settings\BookingSettings;
use App\Domains\Admin\Settings\ContactSettings;
use App\Domains\Admin\Settings\GeneralSettings;
use App\Domains\Admin\Settings\SeoSettings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Platform settings page (Spec 013, US9, FR-015, ST-013-011).
 *
 * Renders one Filament section per spatie/laravel-settings class
 * (General/SEO/Contact/Booking), each field mapping directly to a typed
 * settings-class property. On submit, each group is routed through
 * UpdateSettingsAction, which persists and writes the audited
 * `settings.update` governance entry. Gated by the `manage_settings` flag.
 */
class Settings extends Page
{
    protected static string $view = 'filament.pages.settings';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $slug = 'settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && app(AdminAuthorizationService::class)->can($user, 'manage_settings');
    }

    public function mount(): void
    {
        $this->form->fill($this->loadSettings());
    }

    protected function getFormStatePath(): string
    {
        return 'data';
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make('General')
                    ->schema([
                        TextInput::make('general.site_name')->required()->maxLength(120),
                        TextInput::make('general.site_tagline')->nullable()->maxLength(160),
                        TextInput::make('general.support_email')->email()->required(),
                        TextInput::make('general.default_currency')->required()->length(3),
                        TextInput::make('general.timezone')->required()->maxLength(64),
                        Toggle::make('general.maintenance_mode'),
                    ])->columns(2),
                Section::make('SEO')
                    ->schema([
                        TextInput::make('seo.default_meta_title')->required()->maxLength(120),
                        Textarea::make('seo.default_meta_description')->nullable()->maxLength(255)->rows(2),
                        TextInput::make('seo.default_og_image')->nullable()->maxLength(255),
                        TextInput::make('seo.twitter_handle')->nullable()->maxLength(64),
                        TextInput::make('seo.default_canonical_base')->nullable()->maxLength(255),
                        Toggle::make('seo.sitemap_enabled'),
                    ])->columns(2),
                Section::make('Contact')
                    ->schema([
                        TextInput::make('contact.contact_email')->email()->required(),
                        TextInput::make('contact.contact_phone')->nullable()->maxLength(64),
                        TextInput::make('contact.contact_address')->nullable()->maxLength(255),
                        TextInput::make('contact.business_hours')->nullable()->maxLength(255),
                        Textarea::make('contact.social_links')
                            ->nullable()
                            ->rows(2)
                            ->helperText('One "key=url" per line, e.g. facebook=https://…'),
                    ])->columns(2),
                Section::make('Booking')
                    ->schema([
                        Toggle::make('booking.allow_guest_checkout'),
                        TextInput::make('booking.min_advance_booking_hours')->integer()->required()->minValue(0),
                        TextInput::make('booking.default_booking_window_days')->integer()->required()->minValue(1),
                        TextInput::make('booking.max_guests_per_booking')->integer()->required()->minValue(1),
                        TextInput::make('booking.cancellation_cutoff_hours')->integer()->required()->minValue(0),
                        TextInput::make('booking.auto_complete_after_days')->integer()->required()->minValue(0),
                    ])->columns(2),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $actor = auth()->user();
        $action = app(UpdateSettingsAction::class);

        foreach (array_keys(UpdateSettingsAction::GROUPS) as $group) {
            $groupData = $this->extractGroup($data, $group);
            if ($groupData === []) {
                continue;
            }
            $action->execute($actor, $group, $this->normalizeGroup($group, $groupData));
        }

        Notification::make()->success()->title('Settings saved.')->send();
        $this->form->fill($this->loadSettings());
    }

    /**
     * Load every settings group's properties into a flat `{group}.{property}`
     * form-state array.
     *
     * @return array<string, mixed>
     */
    private function loadSettings(): array
    {
        return [
            'general' => app(GeneralSettings::class)->toArray(),
            'seo' => app(SeoSettings::class)->toArray(),
            'contact' => $this->contactToArray(),
            'booking' => app(BookingSettings::class)->toArray(),
        ];
    }

    /**
     * spatie serializes the contact `social_links` array to JSON; flatten it
     * into the one-per-line text the form field expects.
     *
     * @return array<string, mixed>
     */
    private function contactToArray(): array
    {
        $contact = app(ContactSettings::class);
        $social = $contact->social_links ?? [];
        $lines = [];
        foreach ($social as $key => $url) {
            $lines[] = "{$key}={$url}";
        }

        return array_merge($contact->toArray(), [
            'social_links' => implode("\n", $lines),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractGroup(array $data, string $group): array
    {
        return is_array($data[$group] ?? null) ? $data[$group] : [];
    }

    /**
     * Normalize the contact `social_links` text back into an associative
     * array; other groups pass through unchanged.
     *
     * @param  array<string, mixed>  $groupData
     * @return array<string, mixed>
     */
    private function normalizeGroup(string $group, array $groupData): array
    {
        if ($group === 'contact' && array_key_exists('social_links', $groupData)) {
            $links = [];
            foreach (preg_split('/\r\n|\r|\n/', (string) $groupData['social_links']) as $line) {
                $line = trim($line);
                if ($line === '' || ! str_contains($line, '=')) {
                    continue;
                }
                [$key, $url] = explode('=', $line, 2);
                $links[trim($key)] = trim($url);
            }
            $groupData['social_links'] = $links;
        }

        return $groupData;
    }
}
