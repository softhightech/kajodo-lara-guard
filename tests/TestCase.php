<?php

namespace Kajodo\Guard\Tests;

use Kajodo\Guard\KajodoGuardServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [KajodoGuardServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('kajodo-guard.base_url', 'https://kajodo.example.com');
        $app['config']->set('kajodo-guard.api_key', 'test-key');
        $app['config']->set('kajodo-guard.origin', 'https://shop.example.com');
        $app['config']->set('kajodo-guard.cache_enabled', true);
        $app['config']->set('kajodo-guard.cache_store', 'array');
        $app['config']->set('kajodo-guard.fail_open', true);
        $app['config']->set('kajodo-guard.store_enabled', true);
        $app['config']->set('kajodo-guard.retention_days', 90);
    }
}
