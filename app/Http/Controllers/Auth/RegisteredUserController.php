<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\EmailTemplate;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response|RedirectResponse
    {
        // Check if registration is enabled
        $enableRegistration = admin_setting('enableRegistration');

        if ($enableRegistration !== 'on') {
            return redirect()->route('login');
        }

        $baseDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'gadaa.cloud';

        return Inertia::render('auth/register', [
            'baseDomain' => $baseDomain
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Check if registration is enabled
        $enableRegistration = admin_setting('enableRegistration');

        if ($enableRegistration !== 'on') {
            return redirect()->route('login');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'subdomain' => 'required|alpha_dash|max:50|unique:tenant_domains,subdomain',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        try {
            $enableEmailVerification = admin_setting('enableEmailVerification');

            $adminUser = User::where('type', 'superadmin')->first();

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'email_verified_at' => $enableEmailVerification === 'on' ? null : now(),
                'type' => 'company',
                'lang' => admin_setting('defaultLanguage') ?? 'en',
                'created_by' => $adminUser ? $adminUser->id : null,
            ]);

            // Save Subdomain
            \DB::table('tenant_domains')->insert([
                'user_id' => $user->id,
                'subdomain' => strtolower($request->subdomain),
                'custom_domain_status' => 'pending_dns',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            User::CompanySetting($user->id);
            User::MakeRole($user->id);
            $user->assignRole($user->type);

            Auth::login($user);

             // Send welcome email
            if(admin_setting('New User') == 'on') {
                $emailData = [
                    'name' => $user->name,
                    'email' => $user->email,
                    'password' => $request->password,
                ];

                EmailTemplate::sendEmailTemplate('New User', [$user->email], $emailData, $adminUser->id);
            }

            if ($enableEmailVerification === 'on') {
                // Apply dynamic mail configuration
                SetConfigEmail($adminUser->id);
                $user->sendEmailVerificationNotification();
                return redirect(route('verification.notice'))->with('status', 'verification-link-sent');
            }

            return redirect(route('dashboard', absolute: false));

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Registration failed. Please try again.']);
        }
    }

    /**
     * Check if a subdomain is available.
     */
    public function checkSubdomain(Request $request): \Illuminate\Http\JsonResponse
    {
        $subdomain = strtolower(trim($request->query('subdomain')));
        if (empty($subdomain)) {
            return response()->json(['available' => false, 'message' => __('Subdomain is required')]);
        }

        $reserved = ['admin', 'superadmin', 'www', 'mail', 'api', 'localhost', 'gadaa', 'gadaacloud'];
        if (in_array($subdomain, $reserved)) {
            return response()->json(['available' => false, 'message' => __('This subdomain is reserved')]);
        }

        $exists = \DB::table('tenant_domains')->where('subdomain', $subdomain)->exists();
        return response()->json(['available' => !$exists, 'message' => !$exists ? __('Subdomain is available') : __('Subdomain is already taken')]);
    }
}
