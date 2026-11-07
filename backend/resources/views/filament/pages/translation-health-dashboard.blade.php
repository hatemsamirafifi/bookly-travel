<x-filament-panels::page>
    @php
        $summary = $this->summary;
        $counts = $summary['counts'] ?? [];
        $visible = \App\Filament\Pages\TranslationHealthDashboard::statusesVisibleInTables();
    @endphp

    <div class="space-y-6" data-translation-health>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5" data-health-kpis>
            @foreach ($visible as $status)
                <div class="fi-widget rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ str($status)->replace('_', ' ')->title() }}
                    </p>
                    <p class="mt-1 text-2xl font-bold" data-kpi="{{ $status }}">
                        {{ $counts[$status] ?? 0 }}
                    </p>
                </div>
            @endforeach
        </div>

        <div class="fi-widget rounded-xl bg-white p-6 shadow-sm dark:bg-gray-900" data-health-failure-rate
            data-rate="{{ $summary['failure_rate'] ?? 0 }}">
            <h3 class="text-lg font-semibold">Failure rate</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $summary['total_fields'] ?? 0 }} tracked fields —
                {{ number_format(($summary['failure_rate'] ?? 0) * 100, 1) }}% failed
                ({{ $counts[App\Domains\Translation\Models\TranslationFieldState::STATUS_FAILED] ?? 0 }} fields).
            </p>
        </div>

        <div class="fi-widget rounded-xl bg-white p-6 shadow-sm dark:bg-gray-900" data-model-breakdown>
            <h3 class="text-lg font-semibold">By model type</h3>
            @if (empty($summary['by_model_type']))
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400" data-no-models>
                    No tracked translation fields yet.
                </p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="px-3 pb-2 font-medium">Model</th>
                                <th class="px-3 pb-2 text-right font-medium">Fields</th>
                                @foreach ($visible as $status)
                                    <th class="px-3 pb-2 text-right font-medium">
                                        {{ str($status)->replace('_', ' ')->title() }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($summary['by_model_type'] as $row)
                                <tr data-model-row="{{ $row['model_type'] }}">
                                    <td class="px-3 py-2 font-mono text-xs">{{ $row['model_type'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $row['total_fields'] }}</td>
                                    @foreach ($visible as $status)
                                        <td class="px-3 py-2 text-right tabular-nums" data-cell="{{ $status }}">
                                            {{ $row['counts'][$status] ?? 0 }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
