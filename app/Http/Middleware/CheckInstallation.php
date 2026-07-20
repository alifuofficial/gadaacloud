<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class CheckInstallation
{
    public function handle(Request $request, Closure $next)
    {
        if (!$this->isInstalled() && !$request->is('install*')) {
            return redirect()->route('installer.welcome');
        }

        if ($this->isInstalled() && $request->is('install*')) {
            return redirect('/dashboard');
        }

        return $next($request);
    }

    private function isInstalled(): bool
    {
        if (File::exists(storage_path('installed')) || env('APP_INSTALLED') === true || env('APP_INSTALLED') === 'true') {
            return true;
        }

        try {
            // Auto-detect installation: if database is connected and 'users' table exists, the app is installed.
            // This prevents Docker container restarts from resetting installation state when storage/installed is lost.
            return \Illuminate\Support\Facades\Schema::hasTable('users');
        } catch (\Exception $e) {
            return false;
        }
    }
}