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
     * Remember a value under the blog cache namespace. On stores that support
     * tags (Redis/memcached/array) the entry is cached under the given tags so
     * InvalidateBlogCacheJob can flush it. On a non-TaggableStore (database/file)
     * the value is returned uncached, because tag-based invalidation cannot
     * reach an untagged entry and Cache::tags() throws BadMethodCallException —
     * so caching there would serve stale content (or 500) on the public read
     * path of deployments that have not selected a taggable cache store.
     *
     * @param  list<string>  $tags
     * @param  Closure(): mixed  $callback
     */
    public static function remember(array $tags, string $key, DateTimeInterface|DateInterval|int $ttl, Closure $callback): mixed
    {
        if (Cache::getStore() instanceof TaggableStore) {
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        }

        return $callback();
    }
}