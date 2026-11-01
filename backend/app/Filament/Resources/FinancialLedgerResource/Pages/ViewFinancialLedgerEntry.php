<?php

namespace App\Filament\Resources\FinancialLedgerResource\Pages;

use App\Domains\Payment\Models\FinancialLedgerEntry;
use App\Filament\Resources\FinancialLedgerResource;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Read-only ledger entry detail view (Admin 2.0, T-C02).
 */
class ViewFinancialLedgerEntry extends ViewRecord
{
    protected static string $resource = FinancialLedgerResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var FinancialLedgerEntry $record */
        $record = $this->getRecord();

        return "Ledger Entry #{$record->id}";
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return FinancialLedgerResource::infolist($infolist);
    }
}
