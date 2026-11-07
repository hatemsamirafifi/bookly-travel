<x-filament-panels::page>
    @php
        $snapshot = $this->snapshot;
        $counts = $snapshot['counts'] ?? [];
        $kpis = [
            'failed_translations' => 'Failed translations',
            'failed_media_jobs' => 'Failed media jobs',
            'failed_payments' => 'Failed payments',
            'failed_webhook_events' => 'Failed webhook events',
            'expired_bookings_24h' => 'Expired bookings (24h)',
            'failed_queue_jobs' => 'Failed queue jobs',
        ];
    @endphp

    <div class="space-y-6" data-operational-monitoring>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6" data-health-kpis>
            @foreach ($kpis as $key => $label)
                <div class="fi-widget rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ $label }}
                    </p>
                    <p class="mt-1 text-2xl font-bold" data-kpi="{{ $key }}">
                        {{ $counts[$key] ?? 0 }}
                    </p>
                </div>
            @endforeach
        </div>

        <div class="fi-widget rounded-xl bg-white p-6 shadow-sm dark:bg-gray-900" data-recent-failed-jobs>
            <h3 class="text-lg font-semibold">Recent failed queue jobs</h3>
            @if (empty($snapshot['recent_failed_jobs'] ?? []))
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400" data-no-failed-jobs>
                    No failed jobs.
                </p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="pb-2 pr-4">#</th>
                                <th class="pb-2 pr-4">Queue</th>
                                <th class="pb-2 pr-4">Failed at</th>
                                <th class="pb-2">Exception</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($snapshot['recent_failed_jobs'] ?? [] as $job)
                                <tr class="border-t border-gray-100 dark:border-gray-800" data-failed-job="{{ $job['id'] }}">
                                    <td class="py-2 pr-4 font-mono">{{ $job['id'] }}</td>
                                    <td class="py-2 pr-4">{{ $job['queue'] ?? '-' }}</td>
                                    <td class="py-2 pr-4">{{ $job['failed_at'] }}</td>
                                    <td class="max-w-md truncate py-2">{{ $job['exception'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <p class="text-xs text-gray-400" data-generated-at>{{ $snapshot['generated_at'] ?? now()->toIso8601String() }}</p>
    </div>
</x-filament-panels::page>
