<?php

namespace Kajodo\Guard\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Kajodo\Guard\KajodoGuard as GuardService;
use Kajodo\Guard\Models\KajodoVisitor;

class KajodoVisitorStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    public function test_first_lookup_stores_visitor_with_decision_country_and_city(): void
    {
        // Cache off so this test proves the database layer, not the cache.
        config()->set('kajodo-guard.cache_enabled', false);

        Http::fake([
            '*' => Http::response(['data' => [
                'ip' => '2.3.0.42',
                'verdict' => 'normal_user',
                'country_code' => 'FR',
                'located_country_code' => 'FR',
                'country' => 'France',
                'city' => 'Paris',
                'access' => ['decision' => 'allow', 'reason' => 'Identified human visitor.'],
            ]], 200),
        ]);

        $guard = $this->app->make(GuardService::class);

        $this->assertTrue($guard->isAllowed('2.3.0.42'));

        $this->assertDatabaseHas('kajodo_visitors', [
            'ip' => '2.3.0.42',
            'decision' => 'allow',
            'verdict' => 'normal_user',
            'country_code' => 'FR',
            'located_country_code' => 'FR',
            'country' => 'France',
            'city' => 'Paris',
        ]);
    }

    public function test_stored_ip_is_served_without_any_api_call(): void
    {
        config()->set('kajodo-guard.cache_enabled', false);

        KajodoVisitor::query()->create([
            'ip' => '2.3.0.42',
            'decision' => 'deny',
            'reason' => 'Country FR is not on the allowed list.',
            'verdict' => 'normal_user',
            'country_code' => 'FR',
            'located_country_code' => 'FR',
            'country' => 'France',
            'city' => 'Paris',
        ]);

        Http::preventStrayRequests();

        $guard = $this->app->make(GuardService::class);

        $this->assertFalse($guard->isAllowed('2.3.0.42'));
    }

    public function test_repeat_visit_makes_no_second_api_call(): void
    {
        Http::fake([
            '*' => Http::response(['data' => [
                'ip' => '2.3.0.42',
                'country' => 'France',
                'city' => 'Paris',
                'access' => ['decision' => 'allow', 'reason' => 'ok'],
            ]], 200),
        ]);

        $guard = $this->app->make(GuardService::class);

        $this->assertTrue($guard->isAllowed('2.3.0.42'));

        // Drop the fast cache: the database alone must answer now.
        $this->app['cache']->flush();
        config()->set('kajodo-guard.cache_enabled', false);

        $this->assertTrue($guard->isAllowed('2.3.0.42'));

        Http::assertSentCount(1);
    }

    public function test_failed_lookup_is_not_stored(): void
    {
        config()->set('kajodo-guard.cache_enabled', false);

        Http::fake(['*' => Http::response(null, 500)]);

        $guard = $this->app->make(GuardService::class);

        // Fail-open allows, but nothing is written to the table.
        $this->assertTrue($guard->isAllowed('2.3.0.42'));
        $this->assertDatabaseCount('kajodo_visitors', 0);
    }

    public function test_failed_store_write_does_not_poison_the_cache(): void
    {
        config()->set('kajodo-guard.cache_enabled', true);
        config()->set('kajodo-guard.cache_store', 'array');
        // Point the store at a table that does not exist: the write throws.
        config()->set('kajodo-guard.table', 'kajodo_visitors_missing');

        Http::fake([
            '*' => Http::response(['data' => [
                'ip' => '2.3.0.42',
                'access' => ['decision' => 'allow', 'reason' => 'ok'],
            ]], 200),
        ]);

        $guard = $this->app->make(GuardService::class);

        $this->assertTrue($guard->isAllowed('2.3.0.42'));

        // A cached decision would short-circuit every later request before
        // it reaches the table, leaving the visitor unrecorded until the
        // cache expired — so nothing may be cached here.
        $this->assertNull($this->app['cache']->store('array')->get($guard->cacheKey('2.3.0.42')));
    }

    public function test_invalid_ip_is_not_stored(): void
    {
        Http::preventStrayRequests();

        $guard = $this->app->make(GuardService::class);

        $this->assertFalse($guard->isAllowed('not-an-ip'));
        $this->assertDatabaseCount('kajodo_visitors', 0);
    }

    public function test_prune_command_deletes_rows_not_seen_within_retention(): void
    {
        config()->set('kajodo-guard.retention_days', 30);

        KajodoVisitor::query()->create([
            'ip' => '2.3.0.42',
            'decision' => 'allow',
            'reason' => 'ok',
        ]);

        KajodoVisitor::query()->create([
            'ip' => '5.9.0.10',
            'decision' => 'deny',
            'reason' => 'old',
        ]);
        KajodoVisitor::query()->where('ip', '5.9.0.10')->update([
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);

        $this->artisan('kajodo:prune-visitors')->assertSuccessful();

        $this->assertDatabaseHas('kajodo_visitors', ['ip' => '2.3.0.42']);
        $this->assertDatabaseMissing('kajodo_visitors', ['ip' => '5.9.0.10']);
    }
}
