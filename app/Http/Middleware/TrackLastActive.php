<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TrackLastActive
{
    /**
     * For signed-in users: kick out anyone who has been suspended, otherwise
     * record a throttled "last active" timestamp used for the online count.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user !== null) {
            if ($user->isSuspended() && ! $user->isAdmin()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('suspended', 'Your account has been suspended. Please contact the administrator.');
            }

            // Throttle the write so it runs at most once a minute per user.
            if ($user->last_active_at === null || $user->last_active_at->lt(now()->subMinute())) {
                $user->forceFill(['last_active_at' => now()])->saveQuietly();
            }
        }

        return $next($request);
    }
}
