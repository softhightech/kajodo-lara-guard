<?php

namespace Kajodo\Guard\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Kajodo\Guard\Facades\KajodoGuard;
use Kajodo\Guard\KajodoGuard as GuardService;

class KajodoGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
    public function test_allow_when_kajodo_decision_is_allow(): void
    {
        Http::fake([
            '*' => Http::response(['data' => [
                'ip' => '2.3.0.42',
                'verdict' => 'normal_user',
                'access' => ['decision' => 'allow', 'reason' => 'Identified human visitor.'],
            ]], 200),
        ]);

        $guard = $this->app->make(GuardService::class);

        $this->assertTrue($guard->isAllowed('2.3.0.42'));
    }

    public function test_deny_when_kajodo_decision_is_deny(): void
    {
        Http::fake([
            '*' => Http::response(['data' => [
                'ip' => '2.3.0.42',
                'verdict' => 'normal_user',
                'access' => ['decision' => 'deny', 'reason' => 'Country FR is not on the allowed list.'],
            ]], 200),
        ]);

        $guard = $this->app->make(GuardService::class);

        $this->assertFalse($guard->isAllowed('2.3.0.42'));
    }

    public function test_fail_open_when_api_errors(): void
    {
        Http::fake(['*' => Http::response(null, 500)]);

        $guard = $this->app->make(GuardService::class);

        $this->assertTrue($guard->isAllowed('2.3.0.42'));
    }

    public function test_fail_closed_when_configured(): void
    {
        config()->set('kajodo-guard.fail_open', false);
        Http::fake(['*' => Http::response(null, 500)]);

        $guard = $this->app->make(GuardService::class);

        $this->assertFalse($guard->isAllowed('2.3.0.42'));
    }

    public function test_invalid_ip_is_denied(): void
    {
        Http::preventStrayRequests();

        $guard = $this->app->make(GuardService::class);

        $this->assertFalse($guard->isAllowed('not-an-ip'));
    }

    public function test_result_is_cached_per_ip(): void
    {
        Http::fake([
            '*' => Http::response(['data' => [
                'ip' => '2.3.0.42',
                'verdict' => 'normal_user',
                'access' => ['decision' => 'allow', 'reason' => 'ok'],
            ]], 200),
        ]);

        $guard = $this->app->make(GuardService::class);

        $this->assertTrue($guard->isAllowed('2.3.0.42'));
        $this->assertTrue($guard->isAllowed('2.3.0.42'));

        Http::assertSentCount(1);
    }

    public function test_facade_resolves(): void
    {
        Http::fake([
            '*' => Http::response(['data' => [
                'ip' => '2.3.0.42',
                'access' => ['decision' => 'allow', 'reason' => 'ok'],
            ]], 200),
        ]);

        $this->assertTrue(KajodoGuard::isAllowed('2.3.0.42'));
    }
}
