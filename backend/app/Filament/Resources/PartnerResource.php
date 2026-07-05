<?php

namespace App\Filament\Resources;

use App\Domains\Admin\Actions\ApprovePartnerAction;
use App\Domains\Admin\Actions\RejectPartnerAction;
use App\Domains\Admin\Actions\ReinstatePartnerAction;
use App\Domains\Admin\Actions\SuspendPartnerAction;
use App\Domains\Partner\Models\Partner;
use App\Filament\Resources\PartnerResource\Pages;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PartnerResource extends Resource
{
    protected static ?string $model = Partner::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('onboarding_status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Contact Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('profile.company_name')
                    ->label('Business Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('profile.payout_country')
                    ->label('Country')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('onboarding_status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'gray' => 'suspended',
                    ]),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('tours_count')
                    ->label('Tours')
                    ->counts('tours')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('onboarding_status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'suspended' => 'Suspended',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Partner')
                    ->modalDescription('This will activate the partner account and allow them to create tours.')
                    ->visible(fn (Partner $record) => auth()->user()?->can('approve', $record) && $record->onboarding_status === 'pending')
                    ->action(function (Partner $record) {
                        app(ApprovePartnerAction::class)->execute(auth()->user(), $record);
                        Notification::make()->title('Partner approved')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reject Partner')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->maxLength(500)
                            ->placeholder('Explain why this partner application is being rejected...'),
                    ])
                    ->visible(fn (Partner $record) => auth()->user()?->can('reject', $record) && $record->onboarding_status === 'pending')
                    ->action(function (Partner $record, array $data) {
                        app(RejectPartnerAction::class)->execute(auth()->user(), $record, $data);
                        Notification::make()->title('Partner rejected')->warning()->send();
                    }),
                Tables\Actions\Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Suspend Partner')
                    ->modalDescription('This will deactivate the partner account. Their tours will be hidden from search.')
                    ->visible(fn (Partner $record) => auth()->user()?->can('suspend', $record) && $record->onboarding_status === 'approved' && $record->is_active)
                    ->action(function (Partner $record) {
                        app(SuspendPartnerAction::class)->execute(auth()->user(), $record);
                        Notification::make()->title('Partner suspended')->warning()->send();
                    }),
                Tables\Actions\Action::make('unsuspend')
                    ->label('Reactivate')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Partner $record) => auth()->user()?->can('unsuspend', $record) && $record->onboarding_status === 'suspended')
                    ->action(function (Partner $record) {
                        app(ReinstatePartnerAction::class)->execute(auth()->user(), $record);
                        Notification::make()->title('Partner reactivated')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Partner Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Contact Name'),
                        Infolists\Components\TextEntry::make('user.email')
                            ->label('Email')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('profile.company_name')
                            ->label('Business Name'),
                        Infolists\Components\TextEntry::make('profile.payout_country')
                            ->label('Country'),
                        Infolists\Components\TextEntry::make('profile.contact_phone')
                            ->label('Phone'),
                        Infolists\Components\TextEntry::make('profile.tax_id')
                            ->label('Tax ID'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Status')
                    ->schema([
                        Infolists\Components\TextEntry::make('onboarding_status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                'suspended' => 'gray',
                                default => 'gray',
                            }),
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Registered')
                            ->dateTime(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartners::route('/'),
            'view' => Pages\ViewPartner::route('/{record}'),
        ];
    }
}
