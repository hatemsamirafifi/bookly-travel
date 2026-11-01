<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Read-only payments list (Admin 2.0, T-C02).
 */
class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;
}
