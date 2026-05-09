<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole {
    /**
     * Usage in routes: ->middleware('role:admin') or ->middleware('role:staff,admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response {
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
            abort(403, 'Access denied: insufficient role permissions.');
        }

        return $next($request);
    }
}
