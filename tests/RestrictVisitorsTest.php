<?php

namespace Kajodo\Guard\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class RestrictVisitorsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('kajodo-guard.deny_message', 'Not available in your region.');
        $app['config']->set('kajodo-guard.deny_status', 403);
    }

    protected function defineRoutes($router): void
    {
        Route::get('/protected', fn () => 'ok')->middleware('kajodo.guard');
        Route::get('/health', fn () => 'ok')->middleware('kajodo.guard');
    }

    public function test_denied_visitor_gets_403(): void
    {
        Http::fake([
            '*' => Http::response(['data' => [
                'ip' => '127.0.0.1',
                'verdict' => 'normal_user',
                'access' => ['decision' => 'deny', 'reason' => 'Country FR is not on the allowed list.'],
            ]], 200),
        ]);

        $this->get('/protected')->assertForbidden();
    }

    public function test_allowed_visitor_passes(): void
    {
        Http::fake([
            '*' => Http::response(['data' => [
                'ip' => '127.0.0.1',
                'verdict' => 'normal_user',
                'access' => ['decision' => 'allow', 'reason' => 'Identified human visitor.'],
            ]], 200),
        ]);

        $this->get('/protected')->assertOk()->assertSee('ok');
    }

    public function test_except_paths_bypass_the_check(): void
    {
        config()->set('kajodo-guard.except', ['health']);
        Http::preventStrayRequests();

        $this->get('/health')->assertOk();
    }
}
