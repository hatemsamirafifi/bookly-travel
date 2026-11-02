<x-filament-panels::page>
    @php
        $overview = $this->overview;
    @endphp

    <div class="space-y-6" data-storage-analytics>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4" data-storage-kpis>
            @foreach ([
                'total_assets_count' => 'Total assets',
                'total_bytes' => 'Active storage',
                'trashed_assets_count' => 'Assets in trash',
                'unused_assets_count' => 'Unused assets',
            ] as $metric => $label)
                <div class="fi-widget rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-bold" data-kpi="{{ $metric }}">
                        {{ $metric === 'total_bytes'
                            ? $this->humanSize($overview[$metric] ?? 0)
                            : ($overview[$metric] ?? 0) }}
                    </p>
                </div>
            @endforeach
        </div>

        <div class="fi-widget rounded-xl bg-white p-6 shadow-sm dark:bg-gray-900" data-mime-breakdown>
            <h3 class="text-lg font-semibold">By media type</h3>
            @if (empty($overview['by_mime_type']))
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No active assets yet.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="px-3 pb-2 font-medium">MIME type</th>
                                <th class="px-3 pb-2 text-right font-medium">Assets</th>
                                <th class="px-3 pb-2 text-right font-medium">Bytes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($overview['by_mime_type'] as $row)
                                <tr data-mime-row="{{ $row['mime_type'] }}">
                                    <td class="px-3 py-2 font-mono text-xs">{{ $row['mime_type'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $row['count'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $this->humanSize($row['bytes']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="fi-widget rounded-xl bg-white p-6 shadow-sm dark:bg-gray-900" data-folder-breakdown>
            <h3 class="text-lg font-semibold">By folder</h3>
            @if (empty($overview['by_folder']))
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No active assets yet.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="px-3 pb-2 font-medium">Folder</th>
                                <th class="px-3 pb-2 text-right font-medium">Assets</th>
                                <th class="px-3 pb-2 text-right font-medium">Bytes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($overview['by_folder'] as $row)
                                <tr data-folder-row="{{ $row['folder_path'] }}">
                                    <td class="px-3 py-2 font-mono text-xs">{{ $row['folder_path'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $row['count'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $this->humanSize($row['bytes']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="fi-widget rounded-xl bg-white p-6 shadow-sm dark:bg-gray-900" data-duplicates>
            <h3 class="text-lg font-semibold">Duplicate files (same SHA-256)</h3>
            @if (empty($overview['duplicate_checksums']))
                <p class="mt-3 text-sm text-emerald-600 dark:text-emerald-400" data-no-duplicates>
                    No duplicated content detected.
                </p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="px-3 pb-2 font-medium">Checksum</th>
                                <th class="px-3 pb-2 text-right font-medium">Copies</th>
                                <th class="px-3 pb-2 text-right font-medium">Wasted</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($overview['duplicate_checksums'] as $row)
                                <tr data-duplicate-row="{{ $row['checksum_sha256'] }}">
                                    <td class="max-w-xs truncate px-3 py-2 font-mono text-xs">{{ $row['checksum_sha256'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $row['count'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $this->humanSize($row['wasted_bytes']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="fi-widget rounded-xl bg-white p-6 shadow-sm dark:bg-gray-900" data-largest-assets>
            <h3 class="text-lg font-semibold">Largest files</h3>
            @if (empty($overview['largest_assets']))
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No active assets yet.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="px-3 pb-2 font-medium">File</th>
                                <th class="px-3 pb-2 font-medium">Type</th>
                                <th class="px-3 pb-2 text-right font-medium">Size</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($overview['largest_assets'] as $row)
                                <tr data-largest-row="{{ $row['id'] }}">
                                    <td class="px-3 py-2">{{ $row['original_filename'] }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $row['mime_type'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $this->humanSize($row['size_bytes']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if (($overview['trashed_bytes'] ?? 0) > 0)
            <p class="text-sm text-gray-500 dark:text-gray-400" data-trashed-bytes>
                Trash currently holds {{ $this->humanSize($overview['trashed_bytes'] ?? 0) }} of soft-deleted assets.
            </p>
        @endif
    </div>
</x-filament-panels::page>
