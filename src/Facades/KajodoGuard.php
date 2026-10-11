<?php

namespace Kajodo\Guard\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Kajodo\Guard\LookupResult lookup(string $ip)
 * @method static bool isAllowed(string $ip)
 *
 * @see \Kajodo\Guard\KajodoGuard
 */
class KajodoGuard extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Kajodo\Guard\KajodoGuard::class;
    }
}
