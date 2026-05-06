<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Must be active
        if (!$user->is_active) {
            auth()->logout();
            return redirect()->route('login')
                ->withErrors(['email' => 'Your account has been deactivated.']);
        }

        // Check if user role slug matches any of the allowed roles
        if (!in_array($user->role?->slug, $roles)) {
            // abort(403, 'Access denied: insufficient role permissions.');
            dd(auth()->user());
        }

        return $next($request);
    }
}
