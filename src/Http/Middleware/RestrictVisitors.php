<?php

namespace Kajodo\Guard\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kajodo\Guard\KajodoGuard;
use Symfony\Component\HttpFoundation\Response;

class RestrictVisitors
{
    public function __construct(protected KajodoGuard $guard) {}

    /**
     * Abort with 403 when Kajodo denies the visitor.
     */
    public function handle(Request $request, Closure $next): Response
    {
        foreach ((array) config('kajodo-guard.except', []) as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }

        $ip = $request->ip() ?? '';

        if ($this->guard->isAllowed($ip)) {
            return $next($request);
        }

        abort(
            (int) config('kajodo-guard.deny_status', 403),
            (string) config('kajodo-guard.deny_message', 'Not available in your region.')
        );
    }
}
