<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class UpdateUserActiveStatus
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Force logout mid-session if account was deactivated (resigned, terminated, or disabled)
            if (!Session::has('impersonator_id') && ((int) ($user->is_disable ?? 0) === 1 || (int) ($user->is_enable_login ?? 1) === 0)) {
                Cache::forget("user_online_{$user->id}");
                Auth::guard('web')->logout();
                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }

                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'message' => __('Your account has been deactivated. Please contact your administrator.'),
                    ], 403);
                }

                return redirect()->route('login')->withErrors([
                    'email' => __('Your account has been deactivated. Please contact your administrator.'),
                ]);
            }

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