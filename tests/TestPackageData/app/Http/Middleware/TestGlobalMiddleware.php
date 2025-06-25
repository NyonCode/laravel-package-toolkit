<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TestGlobalMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $response->setContent('Global middleware see - ' . $response->getContent());
        return $response;
    }
}
