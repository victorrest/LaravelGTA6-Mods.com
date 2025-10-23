<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserActivity
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Only update if last activity was more than 5 minutes ago to reduce DB writes
            if (!$user->last_activity_at || $user->last_activity_at->diffInMinutes(now()) >= 5) {
                $user->updateLastActivity();
            }
        }

        return $next($request);
    }
}
