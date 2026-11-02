<?php

namespace App\Filament\Pages;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Admin\Services\OperationalHealthService;
use App\Domains\Translation\Services\TranslationHealthCheckService;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Operational Monitoring page (Admin 2.0 Phase I, T-I02).
 *
 * Renders OperationalHealthService::snapshot(): failed translation fields,
 * failed media jobs, failed payments, failed webhook events, expired
 * bookings in the last 24h, and the latest failed queue jobs — plus a
 * retry action that re-enqueues every failed translation field.
 * Access requires `view_system_health`, `view_audit_log` OR `manage_settings`.
 */
class OperationalMonitoringPage extends Page
{
    protected static string $view = 'filament.pages.operational-monitoring-page';

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'Administration & Governance';

    protected static ?string $navigationLabel = 'Operational Monitoring';

    protected static ?string $title = 'Operational Monitoring';

    protected static ?string $slug = 'operational-monitoring';

    protected static ?int $navigationSort = 7;

    /** @var array<string, mixed> */
    public ?array $snapshot = null;

    public function mount(): void
    {
        $this->loadSnapshot();
    }

    public function getHeading(): string|Htmlable
    {
        return 'Operational Monitoring';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $authz = app(AdminAuthorizationService::class);

        return $authz->can($user, 'view_system_health')
            || $authz->can($user, 'view_audit_log')
            || $authz->can($user, 'manage_settings');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retry_failed_translations')
                ->label('Retry failed translations')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->retryFailedTranslations();
                }),
            Action::make('refresh')
                ->label('Refresh')
                ->action(fn () => $this->loadSnapshot()),
        ];
    }

    public function loadSnapshot(): void
    {
        $this->snapshot = app(OperationalHealthService::class)->snapshot();
    }

    public function retryFailedTranslations(): int
    {
        return (int) (app(TranslationHealthCheckService::class)->retryAllFailures()['queued'] ?? 0);
    }
}
