# Kajodo Laravel Guard

Block visitors from undesired countries with one middleware, powered by your
[Kajodo](https://github.com) IP-intelligence service.

The country policy lives in Kajodo (per-API-key `allowed_countries`). This
package asks Kajodo for each **new** visitor IP, stores the decision with the
visitor's country and city, and never asks twice for the same IP. Denied
visitors get a `403`.

## Requirements

- PHP `^8.3`
- Laravel `12` or `13`
- A running Kajodo service + an API key with the `lookup` ability, bound to
  your website

## Install

```sh
composer require kajodo/laravel-guard
```

Publish the config (optional — every key has a sane default):

```sh
php artisan vendor:publish --tag=kajodo-guard-config
```

Run the migration to create the `kajodo_visitors` table:

```sh
php artisan migrate
```

## Configure

Set these in your `.env`:

```dotenv
KAJODO_BASE_URL=https://intel.example.com   # your Kajodo service, no trailing slash
KAJODO_API_KEY=1|xxxxxxxxxxxxxxxx            # key with `lookup` ability
KAJODO_ORIGIN=https://shop.example.com      # must match a website bound to that key
```

Kajodo rejects keys without a matching `Origin`/`Referer` header, so
`KAJODO_ORIGIN` must be one of the websites bound to the key in the Kajodo
dashboard — otherwise every lookup answers `403` and (see below) visitors
are let through while the failure is logged.

All settings with defaults (`config/kajodo-guard.php`):

| Key | Env | Default | Meaning |
|---|---|---|---|
| `base_url` | `KAJODO_BASE_URL` | `http://localhost:8000` | Kajodo service URL |
| `api_key` | `KAJODO_API_KEY` | `''` | Sanctum key, `lookup` ability |
| `origin` | `KAJODO_ORIGIN` | `APP_URL` | Website bound to the key |
| `timeout` | `KAJODO_TIMEOUT` | `3` | API timeout, seconds |
| `cache_enabled` | `KAJODO_CACHE_ENABLED` | `true` | Fast in-memory cache (Kajodo throttles at 120 lookups/min) |
| `cache_ttl` | `KAJODO_CACHE_TTL` | `3600` | Fast-cache lifetime, seconds |
| `cache_store` | `KAJODO_CACHE_STORE` | default store | Cache store for lookups |
| `store_enabled` | `KAJODO_STORE_ENABLED` | `true` | Persist visitors in the database |
| `table` | `KAJODO_TABLE` | `kajodo_visitors` | Visitor table name |
| `retention_days` | `KAJODO_RETENTION_DAYS` | `90` | Prune rows not seen for this long |
| `fail_open` | `KAJODO_FAIL_OPEN` | `true` | Allow visitors when the API fails (`false` = deny) |
| `deny_message` | `KAJODO_DENY_MESSAGE` | `Not available in your region.` | 403 message |
| `deny_status` | `KAJODO_DENY_STATUS` | `403` | Deny HTTP status |
| `except` | — | `[]` | Route patterns that bypass the check, e.g. `['webhooks/*', 'health']` |

## Use

Attach the middleware to the routes you want to protect:

```php
Route::middleware('kajodo.guard')->group(function () {
    Route::get('/shop', [ShopController::class, 'index']);
});
```

Or globally in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('web', 'kajodo.guard');
})
```

Check a decision in code via the facade:

```php
use Kajodo\Guard\Facades\KajodoGuard;

if (! KajodoGuard::isAllowed($request->ip())) {
    abort(403, 'Not available in your region.');
}

$result = KajodoGuard::lookup($request->ip());
$result->decision;   // 'allow' | 'deny' | 'unknown'
$result->country;    // 'France'
$result->city;       // 'Paris'
```

Prune visitors not seen within the retention window (add to your schedule):

```sh
php artisan kajodo:prune-visitors
php artisan kajodo:prune-visitors --days=30
```

## How it works

For each request, in order:

1. **Bypass** — the path matches `except` → let through, no lookup.
2. **Fast cache** — IP looked up recently → reuse the cached decision.
3. **Visitor store** — IP exists in `kajodo_visitors` → reuse the stored
   decision and touch the row (last-seen marker). **Kajodo is never asked
   again for a stored IP.**
4. **Kajodo API** — `GET {base_url}/api/v1/lookup?ip={ip}` with
   `Authorization: Bearer {api_key}` + `Origin: {origin}`. Only Kajodo's
   `access.decision` is trusted: `allow` passes, anything else (including a
   country outside your key's `allowed_countries`) denies. The decision,
   reason, verdict, country codes, country and city are stored, then cached.

Edge cases:

- **API failure** (timeout, 5xx, bad key/origin): nothing is stored or
  cached; the visitor is allowed when `fail_open` is `true` (default) and
  the failure is logged. Set `fail_open` to `false` for fail-closed.
- **Invalid IP**: denied, never stored.
- **Store unavailable** (e.g. migration not run): the package logs a warning
  and degrades to API-only instead of crashing.
- **Response shape**: Kajodo wraps answers in `data`
  (`data.access.decision`, `data.located_country_code`, …) — the client
  handles both wrapped and bare payloads.
