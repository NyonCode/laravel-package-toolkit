<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TestAliasMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->headers->set('X-Test-Middleware', 'Alias Applied');

        return $response;
    }
}
