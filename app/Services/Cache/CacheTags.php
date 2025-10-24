<?php

namespace App\Services\Cache;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

class CacheTags
{
    public const GUEST_PAGES = 'guest-pages';
    public const HOME = 'home';
    public const MODS = 'mods';
    public const CATEGORIES = 'categories';
    public const USERS = 'users';
    public const COMMENTS = 'comments';
    public const NEWS = 'news';
    public const FORUM_THREADS = 'forum-threads';

    /**
     * @return array<int, string>
     */
    public static function guestPages(): array
    {
        return [self::GUEST_PAGES];
    }

    /**
     * @return array<int, string>
     */
    public static function home(): array
    {
        return [self::HOME];
    }

    /**
     * @param  iterable<int, mixed>|mixed  $mods
     * @return array<int, string>
     */
    public static function mods(mixed $mods = []): array
    {
        return self::normalizeTags(self::MODS, 'mod', $mods);
    }

    /**
     * @param  iterable<int, mixed>|mixed  $categories
     * @return array<int, string>
     */
    public static function categories(mixed $categories = []): array
    {
        return self::normalizeTags(self::CATEGORIES, 'category', $categories);
    }

    /**
     * @param  iterable<int, mixed>|mixed  $users
     * @return array<int, string>
     */
    public static function users(mixed $users = []): array
    {
        return self::normalizeTags(self::USERS, 'user', $users);
    }

    /**
     * @param  iterable<int, mixed>|mixed  $comments
     * @return array<int, string>
     */
    public static function comments(mixed $comments = []): array
    {
        return self::normalizeTags(self::COMMENTS, 'comment', $comments);
    }

    /**
     * @param  iterable<int, mixed>|mixed  $news
     * @return array<int, string>
     */
    public static function news(mixed $news = []): array
    {
        return self::normalizeTags(self::NEWS, 'article', $news);
    }

    /**
     * @param  iterable<int, mixed>|mixed  $threads
     * @return array<int, string>
     */
    public static function forumThreads(mixed $threads = []): array
    {
        return self::normalizeTags(self::FORUM_THREADS, 'thread', $threads);
    }

    /**
     * @param  iterable<int, mixed>|mixed  $items
     * @return array<int, string>
     */
    protected static function normalizeTags(string $rootTag, string $prefix, mixed $items): array
    {
        $identifiers = Collection::wrap($items instanceof Arrayable ? $items->toArray() : $items)
            ->flatten()
            ->map(function ($value) {
                if (is_object($value) && method_exists($value, 'getKey')) {
                    return $value->getKey();
                }

                return is_scalar($value) ? $value : null;
            })
            ->filter()
            ->map(static fn ($id) => $prefix.':'.$id)
            ->unique()
            ->values();

        return Collection::make([$rootTag])->merge($identifiers)->all();
    }
}
