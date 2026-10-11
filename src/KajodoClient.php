<?php

namespace Kajodo\Guard;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class KajodoClient
{
    public function __construct(
        public readonly string $baseUrl,
        public readonly string $apiKey,
        public readonly string $origin,
        public readonly int $timeout = 3,
    ) {}

    /**
     * Classify one IP via GET /api/v1/lookup?ip=.
     *
     * @throws RequestException
     */
    public function lookup(string $ip): LookupResult
    {
        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->withToken($this->apiKey)
            ->withHeaders(['Origin' => $this->origin])
            ->get(rtrim($this->baseUrl, '/').'/api/v1/lookup', ['ip' => $ip]);

        $response->throw();

        /** @var array<string, mixed> $json */
        $json = $response->json();

        /** @var array<string, mixed> $data */
        $data = is_array($json['data'] ?? null) ? $json['data'] : $json;

        return LookupResult::fromApiData($ip, $data);
    }
}
