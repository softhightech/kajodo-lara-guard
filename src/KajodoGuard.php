<?php

namespace Kajodo\Guard;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;
use Kajodo\Guard\Models\KajodoVisitor;
use Throwable;

class KajodoGuard
{
    public function __construct(
        protected KajodoClient $client,
        protected CacheRepository $cache,
        protected bool $cacheEnabled = true,
        protected int $cacheTtl = 3600,
        protected bool $failOpen = true,
        protected bool $storeEnabled = true,
    ) {}

    public function lookup(string $ip): LookupResult
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return LookupResult::fromApiData($ip, [
                'ip' => $ip,
                'verdict' => 'unknown',
                'access' => ['decision' => 'deny', 'reason' => 'Invalid IP address.'],
            ]);
        }

        if ($this->cacheEnabled) {
            $cached = $this->cache->get($this->cacheKey($ip));

            if (is_array($cached)) {
                return LookupResult::fromCachePayload($cached);
            }
        }

        // The table is the long-term memory: a stored IP is never asked
        // to Kajodo again. Rows disappear only via the prune command.
        if ($stored = $this->storedLookup($ip)) {
            $this->rememberInCache($stored);

            return $stored;
        }

        $result = $this->lookupFresh($ip);

        // Never store transport failures: a blip must not pin visitors
        // to an allow/deny, and failures are re-tried on the next visit.
        if ($result->failed) {
            return $result;
        }

        $this->storeResult($result);
        $this->rememberInCache($result);

        return $result;
    }

    public function isAllowed(string $ip): bool
    {
        $result = $this->lookup($ip);

        if ($result->failed) {
            return $this->failOpen;
        }

        return $result->isAllowed();
    }

    public function cacheKey(string $ip): string
    {
        return 'kajodo-guard:'.sha1($ip);
    }

    protected function storedLookup(string $ip): ?LookupResult
    {
        if (! $this->storeEnabled) {
            return null;
        }

        try {
            $visitor = KajodoVisitor::query()->where('ip', $ip)->first();

            if ($visitor === null) {
                return null;
            }

            // Last-seen marker: the prune command keeps rows seen
            // within the retention window.
            $visitor->touch();

            return LookupResult::fromVisitor($visitor);
        } catch (Throwable $e) {
            Log::warning('Kajodo visitor store read failed; falling back to API.', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function storeResult(LookupResult $result): void
    {
        if (! $this->storeEnabled) {
            return;
        }

        try {
            KajodoVisitor::query()->updateOrCreate(
                ['ip' => $result->ip],
                $result->toStoreAttributes(),
            );
        } catch (Throwable $e) {
            Log::warning('Kajodo visitor store write failed.', [
                'ip' => $result->ip,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function rememberInCache(LookupResult $result): void
    {
        if (! $this->cacheEnabled || $result->failed) {
            return;
        }

        $this->cache->put($this->cacheKey($result->ip), $result->toCachePayload(), $this->cacheTtl);
    }

    protected function lookupFresh(string $ip): LookupResult
    {
        try {
            return $this->client->lookup($ip);
        } catch (Throwable $e) {
            Log::warning('Kajodo lookup failed; applying fail-open policy.', [
                'ip' => $ip,
                'fail_open' => $this->failOpen,
                'error' => $e->getMessage(),
            ]);

            return LookupResult::failed($ip);
        }
    }
}
