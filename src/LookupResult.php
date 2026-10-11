<?php

namespace Kajodo\Guard;

use Kajodo\Guard\Models\KajodoVisitor;

/**
 * Typed wrapper around one Kajodo lookup answer.
 *
 * Enforcement is deliberately thin: the country policy lives in Kajodo
 * (per-key allowed_countries) and this package only trusts
 * `access.decision` (`allow` passes, anything else denies).
 */
class LookupResult
{
    /**
     * @param  array<string, mixed>  $data  Raw `data` payload from the API.
     */
    public function __construct(
        public readonly string $ip,
        public readonly string $decision,
        public readonly string $reason,
        public readonly ?string $verdict = null,
        public readonly ?string $locatedCountryCode = null,
        public readonly ?string $countryCode = null,
        public readonly ?string $country = null,
        public readonly ?string $city = null,
        public readonly bool $failed = false,
        public readonly array $data = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApiData(string $ip, array $data): self
    {
        $access = is_array($data['access'] ?? null) ? $data['access'] : [];

        return new self(
            ip: $data['ip'] ?? $ip,
            decision: (string) ($access['decision'] ?? 'deny'),
            reason: (string) ($access['reason'] ?? 'No access decision returned.'),
            verdict: isset($data['verdict']) ? (string) $data['verdict'] : null,
            locatedCountryCode: isset($data['located_country_code']) ? (string) $data['located_country_code'] : null,
            countryCode: isset($data['country_code']) ? (string) $data['country_code'] : null,
            country: isset($data['country']) ? (string) $data['country'] : null,
            city: isset($data['city']) ? (string) $data['city'] : null,
            failed: false,
            data: $data,
        );
    }

    public static function fromVisitor(KajodoVisitor $visitor): self
    {
        return new self(
            ip: $visitor->ip,
            decision: $visitor->decision,
            reason: $visitor->reason ?? '',
            verdict: $visitor->verdict,
            locatedCountryCode: $visitor->located_country_code,
            countryCode: $visitor->country_code,
            country: $visitor->country,
            city: $visitor->city,
        );
    }

    public static function failed(string $ip): self
    {
        return new self(
            ip: $ip,
            decision: 'unknown',
            reason: 'Kajodo lookup failed.',
            failed: true,
        );
    }

    public function isAllowed(): bool
    {
        return $this->decision === 'allow';
    }

    /**
     * @return array<string, mixed>
     */
    public function toStoreAttributes(): array
    {
        return [
            'decision' => $this->decision,
            'reason' => $this->reason,
            'verdict' => $this->verdict,
            'country_code' => $this->countryCode,
            'located_country_code' => $this->locatedCountryCode,
            'country' => $this->country,
            'city' => $this->city,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toCachePayload(): array
    {
        return [
            'ip' => $this->ip,
            'decision' => $this->decision,
            'reason' => $this->reason,
            'verdict' => $this->verdict,
            'located_country_code' => $this->locatedCountryCode,
            'country_code' => $this->countryCode,
            'country' => $this->country,
            'city' => $this->city,
            'failed' => $this->failed,
            'data' => $this->data,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromCachePayload(array $payload): self
    {
        return new self(
            ip: (string) ($payload['ip'] ?? ''),
            decision: (string) ($payload['decision'] ?? 'deny'),
            reason: (string) ($payload['reason'] ?? ''),
            verdict: isset($payload['verdict']) ? (string) $payload['verdict'] : null,
            locatedCountryCode: isset($payload['located_country_code']) ? (string) $payload['located_country_code'] : null,
            countryCode: isset($payload['country_code']) ? (string) $payload['country_code'] : null,
            country: isset($payload['country']) ? (string) $payload['country'] : null,
            city: isset($payload['city']) ? (string) $payload['city'] : null,
            failed: (bool) ($payload['failed'] ?? false),
            data: is_array($payload['data'] ?? null) ? $payload['data'] : [],
        );
    }
}
