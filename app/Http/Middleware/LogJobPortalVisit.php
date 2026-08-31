<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogJobPortalVisit
{
    public function handle(Request $request, Closure $next)
    {
        ActivityLog::create([
            'user_id'    => auth()->id(),
            'event'      => 'job_portal_view',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url'        => $request->fullUrl(),
        ]);

        return $next($request);
    }
}
