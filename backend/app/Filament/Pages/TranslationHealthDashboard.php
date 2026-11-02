<?php

namespace App\Filament\Pages;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Translation\Models\TranslationFieldState;
use App\Domains\Translation\Services\TranslationHealthCheckService;
use App\Models\User;
use Filament\Pages\Page;

/**
 * Translation Health dashboard (Admin 2.0 Phase H, T-H06).
 *
 * Surfaces TranslationHealthCheckService::platformSummary(): totals per
 * pipeline status, the failure rate, and the per-model-type breakdown.
 * Access requires `manage_translations`, `manage_blog` OR `manage_cms`.
 */
class TranslationHealthDashboard extends Page
{
    protected static string $view = 'filament.pages.translation-health-dashboard';

    protected static ?string $navigationIcon = 'heroicon-o-language';

    protected static ?string $navigationGroup = 'Content & CMS';

    protected static ?string $navigationLabel = 'Translation Health';

    protected static ?string $slug = 'translation-health';

    protected static ?int $navigationSort = 5;

    /** @var array<string, mixed> */
    public array $summary = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $authz = app(AdminAuthorizationService::class);

        return $authz->can($user, 'manage_translations')
            || $authz->can($user, 'manage_blog')
            || $authz->can($user, 'manage_cms');
    }

    public function mount(): void
    {
        $this->loadSummary();
    }

    public function loadSummary(): void
    {
        $this->summary = app(TranslationHealthCheckService::class)->platformSummary();
    }

    public static function statusesVisibleInTables(): array
    {
        return [
            TranslationFieldState::STATUS_COMPLETED,
            TranslationFieldState::STATUS_OUTDATED,
            TranslationFieldState::STATUS_NEEDS_REVIEW,
            TranslationFieldState::STATUS_MANUALLY_EDITED,
            TranslationFieldState::STATUS_FAILED,
        ];
    }
}
