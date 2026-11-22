<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class UpdateUserActiveStatus
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $cacheKey = "user_online_{$user->id}";
            
            // Only write to the database if the online status is not already cached
            if (!Cache::has($cacheKey)) {
                $user->update([
                    'active_status' => 1,
                    'last_seen_at' => now(),
                ]);
                Cache::put($cacheKey, true, now()->addMinutes(5));
            }
        }

        return $next($request);
    }
}