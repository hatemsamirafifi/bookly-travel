<x-filament-panels::page>
    <div x-data="{ tab: 'tours' }" class="space-y-6">

        {{-- Stat Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Demo Tours</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $stats['tours_count'] }}</p>
                </div>
                <div class="p-3 bg-blue-50 dark:bg-blue-900/40 rounded-lg text-blue-600 dark:text-blue-400">
                    <x-heroicon-o-map class="w-6 h-6" />
                </div>
            </div>

            <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Blog Posts</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $stats['posts_count'] }}</p>
                </div>
                <div class="p-3 bg-emerald-50 dark:bg-emerald-900/40 rounded-lg text-emerald-600 dark:text-emerald-400">
                    <x-heroicon-o-newspaper class="w-6 h-6" />
                </div>
            </div>

            <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Categories</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $stats['categories_count'] }}</p>
                </div>
                <div class="p-3 bg-amber-50 dark:bg-amber-900/40 rounded-lg text-amber-600 dark:text-amber-400">
                    <x-heroicon-o-squares-2x2 class="w-6 h-6" />
                </div>
            </div>

            <div class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Visualized Images</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $stats['images_count'] }}</p>
                </div>
                <div class="p-3 bg-purple-50 dark:bg-purple-900/40 rounded-lg text-purple-600 dark:text-purple-400">
                    <x-heroicon-o-photo class="w-6 h-6" />
                </div>
            </div>
        </div>

        {{-- Tabs Navigation --}}
        <div class="border-b border-gray-200 dark:border-gray-700 flex space-x-4">
            <button @click="tab = 'tours'" :class="{ 'border-primary-600 text-primary-600 font-bold border-b-2': tab === 'tours', 'text-gray-500 hover:text-gray-700': tab !== 'tours' }" class="pb-3 px-2 text-sm flex items-center gap-2 transition-colors">
                <x-heroicon-o-map class="w-4 h-4" />
                Tours ({{ $tours->count() }})
            </button>
            <button @click="tab = 'posts'" :class="{ 'border-primary-600 text-primary-600 font-bold border-b-2': tab === 'posts', 'text-gray-500 hover:text-gray-700': tab !== 'posts' }" class="pb-3 px-2 text-sm flex items-center gap-2 transition-colors">
                <x-heroicon-o-newspaper class="w-4 h-4" />
                Blog Posts ({{ $blogPosts->count() }})
            </button>
            <button @click="tab = 'tour_categories'" :class="{ 'border-primary-600 text-primary-600 font-bold border-b-2': tab === 'tour_categories', 'text-gray-500 hover:text-gray-700': tab !== 'tour_categories' }" class="pb-3 px-2 text-sm flex items-center gap-2 transition-colors">
                <x-heroicon-o-squares-2x2 class="w-4 h-4" />
                Tour Categories ({{ $categories->count() }})
            </button>
            <button @click="tab = 'blog_categories'" :class="{ 'border-primary-600 text-primary-600 font-bold border-b-2': tab === 'blog_categories', 'text-gray-500 hover:text-gray-700': tab !== 'blog_categories' }" class="pb-3 px-2 text-sm flex items-center gap-2 transition-colors">
                <x-heroicon-o-tag class="w-4 h-4" />
                Blog Categories ({{ $blogCategories->count() }})
            </button>
        </div>

        {{-- Tab 1: Tours --}}
        <div x-show="tab === 'tours'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($tours as $tour)
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="relative h-44 w-full bg-gray-100 dark:bg-gray-700">
                            @if ($tour['cover_image_url'])
                                <img src="{{ $tour['cover_image_url'] }}" alt="{{ $tour['title'] }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-400">
                                    <x-heroicon-o-photo class="w-12 h-12" />
                                </div>
                            @endif
                            <span class="absolute top-2 right-2 px-2.5 py-1 text-xs font-semibold rounded-full shadow {{ $tour['status'] === 'published' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ ucfirst($tour['status']) }}
                            </span>
                        </div>
                        <div class="p-4 space-y-2">
                            <span class="text-xs font-medium text-blue-600 dark:text-blue-400">{{ $tour['category'] }}</span>
                            <h3 class="font-bold text-gray-900 dark:text-white line-clamp-1">{{ $tour['title'] }}</h3>
                            <p class="text-xs text-gray-500 flex items-center gap-1">
                                <x-heroicon-o-map-pin class="w-3.5 h-3.5" />
                                {{ $tour['location'] }}
                            </p>
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Price: {{ $tour['price'] }}</p>
                        </div>
                    </div>
                    <div class="p-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
                        <a href="{{ $tour['edit_url'] }}" class="text-xs font-medium text-primary-600 hover:text-primary-800 flex items-center gap-1">
                            <x-heroicon-o-pencil class="w-3.5 h-3.5" />
                            Edit Tour
                        </a>
                        <a href="{{ $tour['site_url'] }}" target="_blank" class="text-xs text-gray-500 hover:text-gray-700 flex items-center gap-1">
                            <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5" />
                            View on Site
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Tab 2: Blog Posts --}}
        <div x-show="tab === 'posts'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" style="display: none;">
            @foreach ($blogPosts as $post)
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="relative h-44 w-full bg-gray-100 dark:bg-gray-700">
                            @if ($post['cover_image_url'])
                                <img src="{{ $post['cover_image_url'] }}" alt="{{ $post['title'] }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-400">
                                    <x-heroicon-o-photo class="w-12 h-12" />
                                </div>
                            @endif
                            <span class="absolute top-2 right-2 px-2.5 py-1 text-xs font-semibold rounded-full shadow {{ $post['status'] === 'published' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ ucfirst($post['status']) }}
                            </span>
                        </div>
                        <div class="p-4 space-y-2">
                            <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">{{ $post['category'] }}</span>
                            <h3 class="font-bold text-gray-900 dark:text-white line-clamp-1">{{ $post['title'] }}</h3>
                            <p class="text-xs text-gray-500 flex items-center gap-1">
                                <x-heroicon-o-user class="w-3.5 h-3.5" />
                                {{ $post['author'] }}
                            </p>
                        </div>
                    </div>
                    <div class="p-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
                        <a href="{{ $post['edit_url'] }}" class="text-xs font-medium text-primary-600 hover:text-primary-800 flex items-center gap-1">
                            <x-heroicon-o-pencil class="w-3.5 h-3.5" />
                            Edit Article
                        </a>
                        <a href="{{ $post['site_url'] }}" target="_blank" class="text-xs text-gray-500 hover:text-gray-700 flex items-center gap-1">
                            <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5" />
                            View on Site
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Tab 3: Tour Categories --}}
        <div x-show="tab === 'tour_categories'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6" style="display: none;">
            @foreach ($categories as $cat)
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="relative h-36 w-full bg-gray-100 dark:bg-gray-700">
                            @if ($cat['image_url'])
                                <img src="{{ $cat['image_url'] }}" alt="{{ $cat['name'] }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-400">
                                    <x-heroicon-o-photo class="w-10 h-10" />
                                </div>
                            @endif
                        </div>
                        <div class="p-4 space-y-1">
                            <h3 class="font-bold text-gray-900 dark:text-white">{{ $cat['name'] }}</h3>
                            <p class="text-xs text-gray-500">{{ $cat['items_count'] }} Tours Available</p>
                        </div>
                    </div>
                    <div class="p-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
                        <a href="{{ $cat['edit_url'] }}" class="text-xs font-medium text-primary-600 hover:text-primary-800 flex items-center gap-1">
                            <x-heroicon-o-pencil class="w-3.5 h-3.5" />
                            Edit
                        </a>
                        <a href="{{ $cat['site_url'] }}" target="_blank" class="text-xs text-gray-500 hover:text-gray-700 flex items-center gap-1">
                            <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5" />
                            View
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Tab 4: Blog Categories --}}
        <div x-show="tab === 'blog_categories'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6" style="display: none;">
            @foreach ($blogCategories as $bcat)
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="relative h-36 w-full bg-gray-100 dark:bg-gray-700">
                            @if ($bcat['image_url'])
                                <img src="{{ $bcat['image_url'] }}" alt="{{ $bcat['name'] }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-400">
                                    <x-heroicon-o-photo class="w-10 h-10" />
                                </div>
                            @endif
                        </div>
                        <div class="p-4 space-y-1">
                            <h3 class="font-bold text-gray-900 dark:text-white">{{ $bcat['name'] }}</h3>
                            <p class="text-xs text-gray-500">{{ $bcat['items_count'] }} Articles Published</p>
                        </div>
                    </div>
                    <div class="p-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
                        <a href="{{ $bcat['edit_url'] }}" class="text-xs font-medium text-primary-600 hover:text-primary-800 flex items-center gap-1">
                            <x-heroicon-o-pencil class="w-3.5 h-3.5" />
                            Edit
                        </a>
                        <a href="{{ $bcat['site_url'] }}" target="_blank" class="text-xs text-gray-500 hover:text-gray-700 flex items-center gap-1">
                            <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5" />
                            View
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</x-filament-panels::page>
