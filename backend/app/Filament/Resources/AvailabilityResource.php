<?php

namespace App\Filament\Resources;

use App\Domains\Admin\Policies\AvailabilityPolicy;
use App\Filament\Resources\AvailabilityResource\Pages;
use App\Models\Tour;
use App\Models\User;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only availability oversight (Spec 013, US8, FR-014, ST-013-010).
 *
 * Lists tours and, on the view page, shows the derived per-date slot states
 * (empty / partially_booked / full / unavailable). Availability is
 * partner-owned; this resource never mutates rules, exceptions, or bookings.
 *
 * Authorization is delegated to AvailabilityPolicy (manage_bookings, view-only),
 * bypassing the Tour model's own policy.
 */
class AvailabilityResource extends Resource
{
    protected static ?string $model = Tour::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'availability';

    protected static ?string $recordTitleAttribute = 'slug';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && app(AvailabilityPolicy::class)->viewAny($user);
    }

    public static function canView(Model $record): bool
    {
        $user = auth()->user();

        return $user instanceof User && $record instanceof Tour && app(AvailabilityPolicy::class)->view($user, $record);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->label('Tour')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('partnerRecord.profile.company_name')
                    ->label('Partner')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('group_size_max')
                    ->label('Default Capacity')
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('availability_rules_count')
                    ->label('Rules')
                    ->counts('availabilityRules')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Tour Status')
                    ->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Tour Status')
                    ->options([
                        'published' => 'Published',
                        'pending_review' => 'Pending Review',
                        'draft' => 'Draft',
                        'rejected' => 'Rejected',
                        'archived' => 'Archived',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('slug', 'asc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Tour')
                    ->schema([
                        Infolists\Components\TextEntry::make('slug')->label('Tour'),
                        Infolists\Components\TextEntry::make('group_size_max')->label('Default Capacity'),
                        Infolists\Components\TextEntry::make('availabilityRules_count')
                            ->label('Rules')
                            ->state(fn (Tour $record) => $record->availabilityRules()->count()),
                        Infolists\Components\TextEntry::make('status')->label('Tour Status')->badge(),
                    ])
                    ->columns(4),
                Infolists\Components\Section::make('Availability — next 14 days (read-only)')
                    ->schema([
                        Infolists\Components\ViewEntry::make('availability_slots')
                            ->label('Slots')
                            ->hiddenLabel()
                            ->view('filament.infolists.availability-slots'),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAvailability::route('/'),
            'view' => Pages\ViewAvailability::route('/{record}'),
        ];
    }
}