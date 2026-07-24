<?php

namespace App\Filament\Resources;

use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Filament\Resources\GovernanceAuditResource\Pages;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Read-only unified governance audit viewer (Spec 013, US6, FR-011/FR-012).
 * No create/edit/delete — the trail is append-only and tamper-evident.
 */
class GovernanceAuditResource extends Resource
{
    protected static ?string $model = GovernanceAuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'action';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('action')
                    ->label('Action')
                    ->colors([
                        'success' => fn ($state) => str_starts_with($state, 'tour.publish') || str_starts_with($state, 'partner.approve') || str_starts_with($state, 'review.reinstate') || str_starts_with($state, 'partner.reinstate'),
                        'danger' => fn ($state) => str_starts_with($state, 'tour.reject') || str_starts_with($state, 'partner.reject') || str_starts_with($state, 'partner.suspend') || str_starts_with($state, 'review.hide') || str_starts_with($state, 'tour.unpublish'),
                        'warning' => fn ($state) => str_starts_with($state, 'booking.transition'),
                        'gray' => fn ($state) => str_starts_with($state, 'cms.') || str_starts_with($state, 'settings.'),
                    ])
                    ->searchable(),
                Tables\Columns\TextColumn::make('actor_type')
                    ->label('Actor Type')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('actor_id')
                    ->label('Actor ID')
                    ->alignCenter()
                    ->placeholder('System'),
                Tables\Columns\TextColumn::make('target_type')
                    ->label('Target Type')
                    ->placeholder('—')
                    ->color('secondary'),
                Tables\Columns\TextColumn::make('target_id')
                    ->label('Target ID')
                    ->alignCenter()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('metadata.reason')
                    ->label('Reason')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options([
                        'tour.publish' => 'Tour Publish',
                        'tour.reject' => 'Tour Reject',
                        'tour.unpublish' => 'Tour Unpublish',
                        'partner.approve' => 'Partner Approve',
                        'partner.reject' => 'Partner Reject',
                        'partner.suspend' => 'Partner Suspend',
                        'partner.reinstate' => 'Partner Reinstate',
                        'review.hide' => 'Review Hide',
                        'review.reinstate' => 'Review Reinstate',
                        'booking.transition' => 'Booking Transition',
                        'settings.update' => 'Settings Update',
                        'cms.update' => 'CMS Update',
                        'cms.publish' => 'CMS Publish',
                    ]),
                Tables\Filters\SelectFilter::make('actor_type')
                    ->options([
                        'admin' => 'Admin',
                    ]),
                Tables\Filters\SelectFilter::make('target_type')
                    ->options([
                        'tour' => 'Tour',
                        'partner' => 'Partner',
                        'booking' => 'Booking',
                        'review' => 'Review',
                        'setting' => 'Setting',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        DatePicker::make('date_from')->label('From'),
                        DatePicker::make('date_to')->label('To'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['date_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['date_to'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Entry')
                    ->schema([
                        Infolists\Components\TextEntry::make('id')->label('ID'),
                        Infolists\Components\TextEntry::make('action')->label('Action')->badge(),
                        Infolists\Components\TextEntry::make('created_at')->label('When')->dateTime(),
                        Infolists\Components\TextEntry::make('actor_type')->label('Actor Type')->badge(),
                        Infolists\Components\TextEntry::make('actor_id')->label('Actor ID')->placeholder('System'),
                        Infolists\Components\TextEntry::make('target_type')->label('Target Type')->placeholder('—'),
                        Infolists\Components\TextEntry::make('target_id')->label('Target ID')->placeholder('—'),
                    ])
                    ->columns(4),
                Infolists\Components\Section::make('State Change')
                    ->schema([
                        Infolists\Components\TextEntry::make('before_state')
                            ->label('Before')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : '—'),
                        Infolists\Components\TextEntry::make('after_state')
                            ->label('After')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : '—'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Metadata')
                    ->schema([
                        Infolists\Components\TextEntry::make('metadata')
                            ->label('Metadata')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : '—'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGovernanceAudits::route('/'),
            'view' => Pages\ViewGovernanceAudit::route('/{record}'),
        ];
    }
}