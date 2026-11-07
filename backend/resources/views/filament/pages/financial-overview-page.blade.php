<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" data-financial-overview>
            @foreach ([
                'gross_booking_value' => 'Gross Booking Value',
                'captured_payments' => 'Captured Payments',
                'refunded_amount' => 'Refunded Amount',
                'platform_commission' => 'Platform Commission',
                'partner_payout_liability' => 'Partner Payout Liability',
            ] as $metric => $label)
                <div class="fi-widget rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-bold" data-overview-metric="{{ $metric }}">
                        {{ $this->summary[$metric] ?? '—' }}
                    </p>
                </div>
            @endforeach

            <div class="fi-widget rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Failed Payments</p>
                <p class="mt-1 text-2xl font-bold" data-overview-metric="failed_payments_count">
                    {{ $this->summary['failed_payments_count'] ?? 0 }}
                </p>
            </div>
        </div>

        <div class="fi-widget rounded-xl bg-white p-6 shadow-sm dark:bg-gray-900" data-reconciliation-report>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-lg font-semibold">Reconciliation</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    <span data-reconciliation-summary>
                        {{ $this->report['matched_count'] ?? 0 }} / {{ $this->report['total_checked'] ?? 0 }} bookings reconciled
                    </span>
                </span>
            </div>

            @if (empty($this->report['discrepancies']))
                <p class="mt-4 text-sm text-emerald-600 dark:text-emerald-400" data-reconciliation-clean>
                    All examined bookings reconcile cleanly against payments and the financial ledger.
                </p>
            @else
                <div class="mt-4 overflow-x-auto" data-reconciliation-discrepancies>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="px-3 pb-2 font-medium">Booking</th>
                                <th class="px-3 pb-2 font-medium">Issue</th>
                                <th class="px-3 pb-2 text-right font-medium">Booking Total</th>
                                <th class="px-3 pb-2 text-right font-medium">Payment Total</th>
                                <th class="px-3 pb-2 text-right font-medium">Ledger Net</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($this->report['discrepancies'] as $discrepancy)
                                <tr>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $discrepancy['booking_reference'] }}</td>
                                    <td class="px-3 py-2">
                                        <span class="fi-badge fi-badge-color-danger bg-danger-100 text-danger-800 dark:bg-danger-900/30">
                                            {{ $this->issueLabel($discrepancy['issue']) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $discrepancy['booking_total_cents'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $discrepancy['payment_total_cents'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $discrepancy['ledger_net_cents'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
