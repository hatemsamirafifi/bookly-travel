<?php

namespace App\Filament\Resources;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\FinancialLedgerEntry;
use App\Filament\Resources\FinancialLedgerResource\Pages;
use App\Models\User;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only financial ledger viewer (Admin 2.0, T-C02).
 *
 * `financial_ledger_entries` is immutable at the model layer (updating and
 * deleting return false) and this resource mirrors that: view and CSV
 * export only, no create/edit/delete. The `export_ledger` header action on
 * the list page routes through FinancialOverviewService::exportLedgerCsv(),
 * which writes the `finance.export` governance audit entry.
 */
class FinancialLedgerResource extends Resource
{
    protected static ?string $model = FinancialLedgerEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Financial Ledger';

    protected static ?string $pluralLabel = 'Financial Ledger';

    protected static ?string $slug = 'financial-ledger-entries';

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return self::canViewFinancials(auth()->user());
    }

    /**
     * Finance readers hold `view_finances` OR the analytics-wide
     * `view_all_analytics` flag.
     */
    public static function canViewFinancials(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        $authz = app(AdminAuthorizationService::class);

        return $authz->can($user, 'view_finances')
            || $authz->can($user, 'view_all_analytics');
    }

    public static function canView(Model $record): bool
    {
        return self::canViewAny();
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('booking:id,reference');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('booking.reference')
                    ->label('Booking')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_id')
                    ->label('Payment ID'),
                Tables\Columns\BadgeColumn::make('entry_type')
                    ->label('Entry Type')
                    ->colors([
                        'success' => fn ($state) => $state === 'debit',
                        'primary' => fn ($state) => $state === 'commission',
                        'warning' => fn ($state) => $state === 'credit',
                        'gray' => fn ($state) => ! in_array($state, ['debit', 'credit', 'commission'], true),
                    ]),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn (FinancialLedgerEntry $record, int $state): string => Booking::formatPrice(
                        $state,
                        (string) $record->currency,
                    ))
                    ->sortable(),
                Tables\Columns\TextColumn::make('currency')
                    ->label('Currency')
                    ->badge()
                    ->color('secondary'),
                Tables\Columns\TextColumn::make('actor')
                    ->label('Actor')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(60)
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Recorded At')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('entry_type')
                    ->options([
                        'debit' => 'Debit',
                        'credit' => 'Credit',
                        'commission' => 'Commission',
                    ]),
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
                        Infolists\Components\TextEntry::make('booking.reference')->label('Booking Reference'),
                        Infolists\Components\TextEntry::make('payment_id')->label('Payment ID'),
                        Infolists\Components\TextEntry::make('entry_type')->label('Entry Type')->badge(),
                        Infolists\Components\TextEntry::make('amount')
                            ->formatStateUsing(fn (FinancialLedgerEntry $record, int $state): string => Booking::formatPrice(
                                $state,
                                (string) $record->currency,
                            )),
                        Infolists\Components\TextEntry::make('currency')->label('Currency')->badge(),
                        Infolists\Components\TextEntry::make('actor')->label('Actor')->badge(),
                        Infolists\Components\TextEntry::make('description')->label('Description'),
                        Infolists\Components\TextEntry::make('created_at')->label('Recorded At')->dateTime(),
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
            'index' => Pages\ListFinancialLedgerEntries::route('/'),
            'view' => Pages\ViewFinancialLedgerEntry::route('/{record}'),
        ];
    }
}
