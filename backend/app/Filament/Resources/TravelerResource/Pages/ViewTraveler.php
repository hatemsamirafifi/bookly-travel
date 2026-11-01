<?php

namespace App\Filament\Resources\TravelerResource\Pages;

use App\Domains\Admin\Services\TravelerModerationService;
use App\Filament\Resources\TravelerResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\IconPosition;

/**
 * Traveler moderation detail page (Admin 2.0, T-B02).
 *
 * Shows the account/booking/review/profile infolist; header actions expose
 * the suspend/reactivate moderation controls on the record view itself.
 */
class ViewTraveler extends ViewRecord
{
    protected static string $resource = TravelerResource::class;

    public function getTitle(): string
    {
        /** @var User $traveler */
        $traveler = $this->getRecord();

        return (string) ($traveler->name ?: $traveler->email);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        $actor = auth()->user();
        $authorized = $actor instanceof User && TravelerModerationService::isAuthorized($actor);

        return [
            Action::make('suspend')
                ->label('Suspend Traveler')
                ->icon('heroicon-o-pause-circle')
                ->color('danger')
                ->iconPosition(IconPosition::Before)
                ->requiresConfirmation()
                ->modalHeading('Suspend Traveler')
                ->modalDescription('Suspension immediately revokes every active API token for this traveler.')
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Suspension Reason')
                        ->required()
                        ->maxLength(500),
                ])
                ->visible(fn (): bool => $authorized
                    && $this->getRecord()->role === 'traveler'
                    && $this->getRecord()->status !== 'suspended')
                ->action(function (array $data) {
                    /** @var User $traveler */
                    $traveler = $this->getRecord();
                    app(TravelerModerationService::class)->suspend($this->currentActor(), $traveler, $data['reason']);
                }),
            Action::make('reactivate')
                ->label('Reactivate Traveler')
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Reactivate Traveler')
                ->visible(fn (): bool => $authorized
                    && $this->getRecord()->role === 'traveler'
                    && $this->getRecord()->status === 'suspended')
                ->action(function () {
                    /** @var User $traveler */
                    $traveler = $this->getRecord();
                    app(TravelerModerationService::class)->reactivate($this->currentActor(), $traveler);
                }),
        ];
    }

    private function currentActor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
