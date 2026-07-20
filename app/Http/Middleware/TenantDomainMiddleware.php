<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TenantDomainMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();
        $mainHost = parse_url(config('app.url'), PHP_URL_HOST);

        // Check if the host is a tenant subdomain or custom domain
        $tenantDomain = null;

        if ($host !== $mainHost) {
            if (str_ends_with($host, '.' . $mainHost)) {
                $subdomain = str_replace('.' . $mainHost, '', $host);
                $tenantDomain = DB::table('tenant_domains')
                    ->where('subdomain', $subdomain)
                    ->first();
            } else {
                $tenantDomain = DB::table('tenant_domains')
                    ->where('custom_domain', $host)
                    ->first();

                // If expired or suspended, fallback to subdomain
                if ($tenantDomain && $tenantDomain->custom_domain_status !== 'active') {
                    $fallbackHost = $tenantDomain->subdomain . '.' . $mainHost;
                    return redirect()->away($request->getScheme() . '://' . $fallbackHost . $request->getRequestUri())
                        ->with('error', __('Your custom domain is currently suspended or expired. Accessing via subdomain fallback.'));
                }
            }

            if (!$tenantDomain) {
                abort(404, __('Workspace Not Found'));
            }

            // Share the current resolved tenant ID globally
            app()->instance('current_tenant_id', $tenantDomain->user_id);

            // Security: If logged in, verify the user belongs to this tenant
            if (Auth::check()) {
                $user = Auth::user();
                $belongsToTenant = false;

                if ($user->type === 'super admin') {
                    // Superadmin can access any domain for support
                    $belongsToTenant = true;
                } elseif ($user->id == $tenantDomain->user_id) {
                    $belongsToTenant = true;
                } elseif ($user->created_by == $tenantDomain->user_id) {
                    $belongsToTenant = true;
                }

                if (!$belongsToTenant) {
                    Auth::logout();
                    return redirect()->route('login')->with('error', __('Access denied for this domain. Please log in with a valid account.'));
                }
            }
        }

        return $next($request);
    }
}
