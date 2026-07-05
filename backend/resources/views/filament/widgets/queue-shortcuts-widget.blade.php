@php
    $queues = $this->queues();
@endphp

<x-filament-widgets::widget class="fi-wp-queue-shortcuts">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($queues as $key => $queue)
            <a
                href="{{ $queue['url'] }}"
                data-queue-key="{{ $key }}"
                class="fi-queue-tile block rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
                        {{ $queue['label'] }}
                    </span>
                    <span
                        class="fi-queue-badge inline-flex h-7 min-w-[1.75rem] items-center justify-center rounded-full px-2 text-xs font-bold text-white"
                        style="background-color: var(--color-{{ $queue['color'] }}-600, #6b7280);"
                    >
                        {{ $queue['count'] }}
                    </span>
                </div>
            </a>
        @endforeach
    </div>
</x-filament-widgets::widget>