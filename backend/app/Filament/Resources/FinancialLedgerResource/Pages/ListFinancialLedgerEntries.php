<?php

namespace App\Filament\Resources\FinancialLedgerResource\Pages;

use App\Domains\Admin\Services\FinancialOverviewService;
use App\Filament\Resources\FinancialLedgerResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Arr;

/**
 * Financial ledger list page (Admin 2.0, T-C02).
 *
 * Adds the `export_ledger` header action on top of the read-only list: the
 * CSV stream routes through FinancialOverviewService::exportLedgerCsv(),
 * which enforces the export authorization and writes the `finance.export`
 * governance audit entry.
 */
class ListFinancialLedgerEntries extends ListRecords
{
    protected static string $resource = FinancialLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_ledger')
                ->label('Export Ledger CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->modalHeading('Export Financial Ledger CSV')
                ->modalDescription('Exports the ledger entries matching the chosen filters as CSV. The export scope is recorded in the governance audit log.')
                ->form([
                    Select::make('entry_type')
                        ->label('Entry Type')
                        ->options([
                            'debit' => 'Debit',
                            'credit' => 'Credit',
                            'commission' => 'Commission',
                        ])
                        ->nullable(),
                    DatePicker::make('date_from')
                        ->label('From'),
                    DatePicker::make('date_to')
                        ->label('To'),
                ])
                ->visible(fn (): bool => auth()->user() instanceof User
                    && app(FinancialOverviewService::class)->canExport(auth()->user()))
                ->action(function (array $data) {
                    /** @var User $actor */
                    $actor = auth()->user();
                    $filters = Arr::whereNotNull([
                        'entry_type' => $data['entry_type'] ?? null,
                        'date_from' => $data['date_from'] ?? null,
                        'date_to' => $data['date_to'] ?? null,
                    ]);

                    return app(FinancialOverviewService::class)->downloadLedgerCsv($actor, $filters);
                }),
        ];
    }
}
