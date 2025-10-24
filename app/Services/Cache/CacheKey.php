<?php

namespace App\Services\Cache;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

class CacheKey
{
    /**
     * @param  array<string, string>  $context
     * @param  array<int, string>  $tags
     */
    public function __construct(
        private readonly string $prefix,
        private readonly string $namespace,
        private readonly array $context = [],
        private readonly ?Authenticatable $user = null,
        private readonly array $tags = []
    ) {
    }

    public function key(): string
    {
        $payload = $this->context;

        if ($this->user !== null) {
            $payload['user_id'] = (string) $this->user->getAuthIdentifier();
        }

        try {
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (\JsonException $exception) {
            $hash = hash('sha256', serialize($payload));
        }

        return implode(':', array_filter([
            $this->prefix,
            Str::slug($this->namespace, ':'),
            $hash,
        ]));
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        $tags = [Str::slug($this->namespace, ':')];

        if ($this->user !== null) {
            $tags[] = 'user:'.$this->user->getAuthIdentifier();
        }

        foreach ($this->tags as $tag) {
            $tags[] = Str::slug($tag, ':');
        }

        foreach ($this->context as $key => $value) {
            $tags[] = Str::slug($this->namespace.':'.$key.':'.$value, ':');
        }

        return array_values(array_unique(array_filter($tags)));
    }

    public function __toString(): string
    {
        return $this->key();
    }
}
