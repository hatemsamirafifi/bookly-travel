<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Admin\Settings\BookingSettings;
use App\Domains\Admin\Settings\ContactSettings;
use App\Domains\Admin\Settings\GeneralSettings;
use App\Domains\Admin\Settings\SeoSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Settings;

/**
 * Persist a platform settings group and audit the change (Spec 013, US9, FR-015).
 *
 * Resolves the matching spatie/laravel-settings class for the submitted
 * `group`, fills it with the validated payload, calls `save()`, and writes one
 * `settings.update` governance audit entry. The audit target is the plain
 * `setting` target_type with a null target_id (settings are not Eloquent
 * models — data-model.md §1/§4); the group and per-property before/after
 * snapshots are recorded in metadata.
 */
class UpdateSettingsAction
{
    /** @var array<string, class-string<Settings>> */
    public const GROUPS = [
        'general' => GeneralSettings::class,
        'seo' => SeoSettings::class,
        'contact' => ContactSettings::class,
        'booking' => BookingSettings::class,
    ];

    public function __construct(private readonly GovernanceAuditService $audit) {}

    /**
     * @param  string  $group  general|seo|contact|booking.
     * @param  array  $data  Validated property => value pairs for the group.
     */
    public function execute(User $actor, string $group, array $data): Settings
    {
        abort_unless(array_key_exists($group, self::GROUPS), 422, "Unknown settings group [{$group}].");

        $class = self::GROUPS[$group];

        return DB::transaction(function () use ($actor, $group, $data, $class) {
            /** @var Settings $settings */
            $settings = app($class);
            $before = $this->snapshot($settings);

            // Only fill keys that are real properties on the settings class so
            // unvalidated/group keys never leak into storage.
            $fillable = $this->fillableProperties($settings);
            $settings->fill(array_intersect_key($data, $fillable));
            $settings->save();

            $after = $this->snapshot($settings);
            $changed = $this->diff($before, $after);

            // A no-op save (no property changed) is not a governance event —
            // persist the settings but skip the audit row so the trail records
            // only real settings changes (FR-011).
            if (! empty($changed)) {
                $this->audit->log(
                    $actor,
                    'settings.update',
                    null,
                    $before,
                    $after,
                    ['group' => $group, 'properties' => $changed],
                    'setting',
                );
            }

            return $settings;
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(Settings $settings): array
    {
        $snapshot = [];
        foreach ($this->fillableProperties($settings) as $name => $_) {
            $snapshot[$name] = $settings->{$name};
        }

        return $snapshot;
    }

    /** @return array<string, \ReflectionProperty> */
    private function fillableProperties(Settings $settings): array
    {
        $props = [];
        $reflection = new \ReflectionClass($settings);
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $props[$property->getName()] = $property;
        }

        return $props;
    }

    /** @return array<string, array{before: mixed, after: mixed}> */
    private function diff(array $before, array $after): array
    {
        $changed = [];
        foreach ($after as $key => $value) {
            $previous = $before[$key] ?? null;
            if (json_encode($value) !== json_encode($previous)) {
                $changed[$key] = ['before' => $previous, 'after' => $value];
            }
        }

        return $changed;
    }
}
