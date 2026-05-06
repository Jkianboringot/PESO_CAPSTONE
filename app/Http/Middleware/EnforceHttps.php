<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceHttps {
    public function handle(Request $request, Closure $next): Response {
        // Only enforce in production (APP_ENV=production in .env)
        if (!$request->secure() && app()->environment('production')) {
            return redirect()->secure($request->getRequestUri(), 301);
        }
        return $next($request);
    }
}
