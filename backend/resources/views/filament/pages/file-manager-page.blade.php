<x-filament-panels::page>
    <div class="space-y-6" data-file-manager>
        @php $breadcrumbTrail = $this->breadcrumbs(); @endphp

        <div class="flex flex-wrap items-center gap-2 text-sm" data-breadcrumbs>
            <a href="{{ static::getUrl(['folderId' => null]) }}"
               class="font-medium {{ $breadcrumbTrail === [] ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400 mt-0' }}">
                Root
            </a>
            @foreach ($breadcrumbTrail as $crumb)
                <span class="text-gray-400">/</span>
                @if ($crumb->getKey() === ($breadcrumbTrail[count($breadcrumbTrail) - 1]?->getKey() ?? null))
                    <span class="font-semibold" data-current-folder>{{ $crumb->name }}</span>
                @else
                    <a href="{{ static::getUrl(['folderId' => $crumb->getKey()]) }}" class="text-gray-500 dark:text-gray-400">
                        {{ $crumb->name }}
                    </a>
                @endif
            @endforeach
        </div>

        <div class="flex flex-wrap gap-2 text-xs" data-storage-kpis>
            <span class="fi-badge">{{ $this->kpis()['total_assets'] }} assets</span>
            <span class="fi-badge">{{ $this->kpis()['total_folders'] }} folders</span>
            <span class="fi-badge" data-trashed-count>{{ $this->kpis()['trashed_assets'] }} in trash</span>
        </div>

        <div class="fi-widget rounded-xl bg-white p-6 shadow-sm dark:bg-gray-900" data-folder-grid>
            <h3 class="text-lg font-semibold">Subfolders</h3>

            @if ($this->folders()->isEmpty())
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No subfolders here.</p>
            @else
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($this->folders() as $folder)
                        <a href="{{ static::getUrl(['folderId' => $folder->getKey()]) }}"
                           class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 text-sm hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                           data-folder-link="{{ $folder->path }}">
                            <x-filament::icon icon="heroicon-o-folder" class="h-5 w-5" />
                            <span class="font-medium">{{ $folder->name }}</span>
                            <span class="ml-auto text-xs text-gray-400">{{ $folder->assets_count }} assets</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="fi-widget rounded-xl bg-white p-6 shadow-sm dark:bg-gray-900" data-asset-list>
            <h3 class="text-lg font-semibold">Assets</h3>

            @if ($this->assets()->isEmpty())
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No assets in this folder.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="px-3 pb-2 font-medium">File</th>
                                <th class="px-3 pb-2 font-medium">Use</th>
                                <th class="px-3 pb-2 font-medium">Usages</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($this->assets() as $asset)
                                <tr data-asset-row="{{ $asset->storage_key }}">
                                    <td class="px-3 py-2">{{ $asset->original_filename }}</td>
                                    <td class="px-3 py-2 text-xs">
                                        {{ $asset->disk }}:{{ $asset->storage_key }}
                                    </td>
                                    <td class="px-3 py-2 text-xs">{{ $this->assetUsageLabel($asset) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="fi-widget rounded-xl bg-white p-6 shadow-sm dark:bg-gray-900" data-trash-list>
            <h3 class="text-lg font-semibold">Trash</h3>

            @if ($this->trashedAssets()->isEmpty())
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Trash is empty.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="px-3 pb-2 font-medium">File</th>
                                <th class="px-3 pb-2 font-medium">Deleted</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($this->trashedAssets() as $asset)
                                <tr data-trashed-row="{{ $asset->id }}">
                                    <td class="px-3 py-2">{{ $asset->original_filename }}</td>
                                    <td class="px-3 py-2 text-xs">{{ $asset->deleted_at?->toIso8601String() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
