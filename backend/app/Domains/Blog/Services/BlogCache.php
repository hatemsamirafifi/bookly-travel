<?php

declare(strict_types=1);

namespace App\Domains\Blog\Services;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

final class BlogCache
{
    /**
     * Remember a value under the blog cache namespace, using tag-based
     * caching when the configured store supports tags (Redis/memcached/array)
     * and falling back to an untagged Cache::remember() on stores that do not
     * (database/file). Cache::tags() throws BadMethodCallException on a
     * non-TaggableStore, which would 500 the public blog read path on any
     * deployment that has not selected a taggable cache store.
     *
     * @param  list<string>  $tags
     * @param  Closure(): mixed  $callback
     */
    public static function remember(array $tags, string $key, DateTimeInterface|DateInterval|int $ttl, Closure $callback): mixed
    {
        if (Cache::getStore() instanceof TaggableStore) {
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        }

        return Cache::remember($key, $ttl, $callback);
    }
}