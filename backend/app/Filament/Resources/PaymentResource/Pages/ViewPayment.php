<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Domains\Payment\Models\Payment;
use App\Filament\Resources\PaymentResource;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Read-only payment detail view (Admin 2.0, T-C02).
 */
class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Payment $record */
        $record = $this->getRecord();

        return "Payment #{$record->id}";
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return PaymentResource::infolist($infolist);
    }
}
