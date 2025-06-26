<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TestGlobalMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('X-Test-Middleware', 'Global Applied');

        return $response;
    }
}
