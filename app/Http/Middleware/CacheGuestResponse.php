<?php

namespace App\Http\Middleware;

use App\Services\Cache\CacheKeyManager;
use App\Services\Cache\CacheService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CacheGuestResponse
{
    public function __construct(
        protected CacheService $cache,
        protected CacheKeyManager $keys,
    ) {
    }

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        if (! $this->shouldAttemptCaching($request)) {
            return $next($request);
        }

        $cacheKey = $this->keys->make('guest:page', $this->cacheContext($request));

        if ($cached = $this->cache->getGuestPage($cacheKey)) {
            return $this->hydrateResponse($cached)->header('X-Cache', 'HIT');
        }

        /** @var SymfonyResponse $response */
        $response = $next($request);

        if (! $response instanceof Response) {
            $response = response($response);
        }

        if (! $this->isCacheableResponse($request, $response)) {
            return $response->header('X-Cache', 'SKIP');
        }

        $payload = $this->dehydrateResponse($response);
        $this->cache->putGuestPage($cacheKey, $payload, $payload['ttl']);

        return $response->header('X-Cache', 'MISS');
    }

    protected function shouldAttemptCaching(Request $request): bool
    {
        if (! config('performance.full_page_cache.enabled')) {
            return false;
        }

        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($request->user()) {
            return false;
        }

        if ($request->ajax() || $request->expectsJson() || $request->header('X-Inertia')) {
            return false;
        }

        if ($request->headers->has('X-Skip-Cache') || $request->query->has('skip_cache')) {
            return false;
        }

        if ($request->routeIs('horizon.*') || $request->routeIs('admin.*')) {
            return false;
        }

        if ($request->hasSession() && $request->session()->isStarted()) {
            $flash = $request->session()->get('_flash.new', []);

            if (! empty($flash)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function cacheContext(Request $request): array
    {
        $extra = [];

        if (config('performance.full_page_cache.vary_on_locale')) {
            $extra['locale'] = app()->getLocale();
        }

        return $this->keys->contextFromRequest($request, $extra);
    }

    protected function isCacheableResponse(Request $request, Response $response): bool
    {
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        if (! Str::contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }

        if ($response->headers->has('Set-Cookie')) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function dehydrateResponse(Response $response): array
    {
        $ttl = (int) config('performance.full_page_cache.ttl', 300);
        $browserTtl = max(0, min(60, $ttl));
        $staleTtl = max(30, (int) floor($ttl / 2));

        $content = $this->stripPersonalizedTokens($response->getContent());

        $headers = collect($response->headers->all())
            ->reject(fn ($values, $name) => strtolower($name) === 'set-cookie')
            ->map(fn ($values) => (array) $values)
            ->all();

        $headers['Cache-Control'] = [
            sprintf('public, max-age=%d, s-maxage=%d, stale-while-revalidate=%d', $browserTtl, $ttl, $staleTtl),
        ];

        $headers['Vary'] = ['Accept-Encoding, Accept-Language'];

        return [
            'status' => $response->getStatusCode(),
            'headers' => $headers,
            'content' => $content,
            'ttl' => $ttl,
            'stored_at' => Carbon::now()->getTimestamp(),
        ];
    }

    protected function hydrateResponse(array $payload): Response
    {
        $response = new Response(
            $this->restorePersonalizedTokens($payload['content'] ?? ''),
            $payload['status'] ?? 200,
        );

        foreach (($payload['headers'] ?? []) as $name => $values) {
            foreach ((array) $values as $value) {
                $response->headers->set($name, $value, false);
            }
        }

        if (isset($payload['stored_at'])) {
            $age = max(0, Carbon::now()->getTimestamp() - (int) $payload['stored_at']);
            $response->headers->set('Age', (string) $age);
        }

        return $response;
    }

    protected function stripPersonalizedTokens(?string $content): string
    {
        $content ??= '';

        $csrf = csrf_token();

        if (! empty($csrf)) {
            $content = str_replace($csrf, '{__CSRF_TOKEN__}', $content);
        }

        return $content;
    }

    protected function restorePersonalizedTokens(string $content): string
    {
        $content = str_replace('{__CSRF_TOKEN__}', csrf_token(), $content);

        return $content;
    }
}
