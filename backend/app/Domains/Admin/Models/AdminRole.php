<?php

namespace App\Domains\Admin\Models;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Admin role template (Spec 035, T-A01).
 *
 * A role bundles permission flags that are unioned with a user's direct
 * `admin_permissions.flags` by AdminAuthorizationService. System roles are
 * seeded from `systemRoleTemplates()` and cannot be deleted.
 */
class AdminRole extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'permissions',
        'is_system',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_system' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'admin_role_user', 'admin_role_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Canonical system role templates keyed by slug.
     *
     * @return array<string, array{name: string, description: string, permissions: array<string, bool>}>
     */
    public static function systemRoleTemplates(): array
    {
        return [
            'super_admin' => [
                'name' => 'Super Admin',
                'description' => 'Unrestricted platform access, including admin user and role management.',
                'permissions' => array_fill_keys(AdminAuthorizationService::FLAGS, true),
            ],
            'operations_admin' => [
                'name' => 'Operations Admin',
                'description' => 'Day-to-day marketplace operations: tours, partners, bookings, reviews, travelers.',
                'permissions' => array_fill_keys([
                    'manage_tours',
                    'manage_partners',
                    'manage_bookings',
                    'moderate_reviews',
                    'manage_travelers',
                ], true),
            ],
            'content_manager' => [
                'name' => 'Content Manager',
                'description' => 'CMS, blog, media, translation and revision management.',
                'permissions' => array_fill_keys([
                    'manage_cms',
                    'manage_blog',
                    'manage_media',
                    'manage_files',
                    'manage_translations',
                    'manage_revisions',
                ], true),
            ],
            'finance_viewer' => [
                'name' => 'Finance Viewer',
                'description' => 'Read-only access to finances, financial reports and analytics.',
                'permissions' => array_fill_keys([
                    'view_finances',
                    'export_financial_reports',
                    'view_all_analytics',
                ], true),
            ],
            'support_agent' => [
                'name' => 'Support Agent',
                'description' => 'Customer support: bookings, review moderation and traveler records.',
                'permissions' => array_fill_keys([
                    'manage_bookings',
                    'moderate_reviews',
                    'manage_travelers',
                ], true),
            ],
            'media_manager' => [
                'name' => 'Media Manager',
                'description' => 'Media library and file storage management, including media deletion.',
                'permissions' => array_fill_keys([
                    'manage_media',
                    'manage_files',
                    'delete_media',
                ], true),
            ],
            'translator_editor' => [
                'name' => 'Translator Editor',
                'description' => 'Translation, CMS and revision editing across all locales.',
                'permissions' => array_fill_keys([
                    'manage_translations',
                    'manage_cms',
                    'manage_revisions',
                ], true),
            ],
        ];
    }

    /**
     * Idempotently create or refresh the system role templates.
     */
    public static function ensureSystemRoles(): void
    {
        foreach (self::systemRoleTemplates() as $slug => $template) {
            self::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $template['name'],
                    'description' => $template['description'],
                    'permissions' => $template['permissions'],
                    'is_system' => true,
                ],
            );
        }
    }

    public function grants(string $permission): bool
    {
        return (bool) ($this->permissions[$permission] ?? false);
    }
}
