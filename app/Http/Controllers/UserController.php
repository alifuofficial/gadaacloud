<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LoginHistory;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Events\CreateUser;
use App\Models\EmailTemplate;
use App\Traits\ControllerHelpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Session;

class UserController extends Controller
{
    use ControllerHelpers;

    public function index()
    {
        if(Auth::user()->can('manage-users')){
            $users = User::query()
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-users')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-users')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('name'), fn($q) => $q->where('name', 'like', '%' . request('name') . '%'))
                ->when(request('email'), fn($q) => $q->where('email', 'like', '%' . request('email') . '%'))
                ->when(request('role'), fn($q) => $q->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.role_id', request('role'))
                    ->where('model_has_roles.model_type', User::class))
                ->when(request('is_enable_login') !== null, fn($q) => $q->where('is_enable_login', request('is_enable_login')))
                ->when(request('sort'), fn($q) => $this->applySafeSort($q, ['name', 'email', 'type', 'is_enable_login', 'created_at']), function($q) {
                    if (config('app.is_demo', false) && Auth::user()->type === 'superadmin') {
                        return $q->orderBy('id', 'asc');
                    }
                    return $q->latest();
                })
                ->select('users.*')
                ->paginate($this->safePerPage(10, 100))
                ->withQueryString();

            $roles = Role::where('created_by', creatorId())->pluck('label', 'id');

            $plans = [];
            if (Auth::user()->type === 'superadmin') {
                $plans = \App\Models\Plan::where('status', 1)->get(['id', 'name', 'package_price_monthly', 'package_price_yearly']);
            }

            return Inertia::render('users/index', [
                'users' => $users,
                'roles' => $roles,
                'plans' => $plans,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreUserRequest $request)
    {
        if(Auth::user()->can('create-users')){
            $checkUser = canCreateUser();
            if (!$checkUser['can_create']) {
                return redirect()->route('users.index')->with('error', $checkUser['message']);
            }

            $validated = $request->validated();
            $validated['is_enable_login'] = $request->boolean('is_enable_login', true);

            if (Auth::user()->type !== 'superadmin') {
                $role = Role::where('created_by', creatorId())->where('id', $validated['type'])->first();
                if (!$role) {
                    return redirect()->route('users.index')->with('error', __('Invalid role selected for your workspace.'));
                }
            } else {
                $role = Role::find($validated['type']);
            }
            $enableEmailVerification = admin_setting('enableEmailVerification');

            $user = DB::transaction(function () use ($request, $validated, $role, $enableEmailVerification) {
                $user = new User();
                $user->name = $validated['name'];
                $user->email = $validated['email'];
                $user->mobile_no = $validated['mobile_no'];
                $user->password = Hash::make($validated['password']);
                $user->type = Auth::user()->type == 'superadmin' ? 'company' : ($role->name ?? 'staff');
                $user->is_enable_login = $validated['is_enable_login'];
                $user->lang = company_setting('defaultLanguage') ?? 'en';
                $user->email_verified_at = $enableEmailVerification === 'on' ? null : now();
                $user->creator_id = Auth::id();
                $user->created_by = creatorId();
                $user->save();

                if(Auth::user()->type == 'superadmin')
                {
                    User::CompanySetting($user->id);
                    User::MakeRole($user->id);
                    $role = Role::findByName('company');

                    // Assign Subdomain & Custom Domain
                    $subdomain = $request->input('subdomain') ? strtolower(trim($request->input('subdomain'))) : null;
                    $customDomain = $request->input('custom_domain') ? strtolower(trim($request->input('custom_domain'))) : null;

                    if (empty($subdomain)) {
                        $subdomain = $user->slug ?? strtolower(preg_replace('/[^A-Za-z0-9]/', '', $user->name));
                    }

                    // Force unique subdomain
                    $originalSubdomain = $subdomain;
                    $counter = 1;
                    while (\DB::table('tenant_domains')->where('subdomain', $subdomain)->exists()) {
                        $subdomain = $originalSubdomain . $counter;
                        $counter++;
                    }

                    \DB::table('tenant_domains')->insert([
                        'user_id' => $user->id,
                        'subdomain' => $subdomain,
                        'custom_domain' => $customDomain,
                        'custom_domain_status' => $customDomain ? 'active' : 'pending_dns',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    // Assign Subscription Plan
                    if ($request->filled('plan_id')) {
                        $planId = $request->input('plan_id');
                        $duration = $request->input('plan_duration', 'month');
                        assignPlan($planId, $duration, null, null, $user->id);
                    } else {
                        $freePlan = \App\Models\Plan::where('free_plan', 1)->first();
                        if ($freePlan) {
                            assignPlan($freePlan->id, 'month', null, null, $user->id);
                        }
                    }
                }

                $user->assignRole($role);

                return $user;
            });

            // Dispatch event for packages to handle their fields
            try {
                CreateUser::dispatch($request, $user);
            } catch (\Throwable $e) {
                \Log::warning('CreateUser dispatch failed: ' . $e->getMessage());
            }

             // Send welcome email
            if(company_setting('New User') == 'on') {
                $emailData = [
                    'name' => $user->name,
                    'email' => $user->email,
                    'password' => $validated['password'],
                ];

                EmailTemplate::sendEmailTemplate('New User', [$user->email], $emailData);
            }

            if ($enableEmailVerification === 'on') {
                // Apply dynamic mail configuration
                SetConfigEmail(creatorId());
                $user->sendEmailVerificationNotification();
            }

            return redirect()->route('users.index')->with('success', __('The user has been created successfully.'));
        }
        else{
            return redirect()->route('users.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        if(Auth::user()->can('edit-users')){
            if (!Auth::user()->hasRole('superadmin') && $user->created_by !== creatorId()) {
                return redirect()->route('users.index')->with('error', __('Permission denied: You cannot edit users outside your workspace.'));
            }
            if ($user->type === 'superadmin' && !Auth::user()->hasRole('superadmin')) {
                return redirect()->route('users.index')->with('error', __('Permission denied.'));
            }

            $validated = $request->validated();
            $validated['is_enable_login'] = $request->boolean('is_enable_login', true);

            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->mobile_no = $validated['mobile_no'];
            $user->is_enable_login = $validated['is_enable_login'];
            $user->save();

            return back()->with('success', __('The user details are updated successfully.'));
        }
        else{
            return redirect()->route('users.index')->with('error', __('Permission denied'));
        }
    }

    public function changePassword(ChangePasswordRequest $request, User $user)
    {
        if(Auth::user()->can('change-password-users') && (Auth::user()->hasRole('superadmin') || $user->created_by == creatorId())){
            if ($user->type === 'superadmin' && !Auth::user()->hasRole('superadmin')) {
                return redirect()->route('users.index')->with('error', __('Permission denied.'));
            }
            $validated = $request->validated();
            $user->password = Hash::make($validated['password']);
            $user->save();

            return redirect()->route('users.index')->with('success', __('The password changed successfully.'));
        }
        else{
            return redirect()->route('users.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(User $user)
    {
        if(Auth::user()->can('delete-users')){
            if ($user->id === Auth::id()) {
                return back()->with('error', __('You cannot delete your own account.'));
            }
            if ($user->type === 'superadmin' || $user->hasRole('superadmin')) {
                return back()->with('error', __('Superadmin user cannot be deleted.'));
            }
            if (!Auth::user()->hasRole('superadmin') && $user->created_by !== creatorId()) {
                return back()->with('error', __('Permission denied: You cannot delete users outside your workspace.'));
            }

            $user->delete();

            return back()->with('success', __('The user has been deleted.'));
        }
        else{
            return redirect()->route('users.index')->with('error', __('Permission denied'));
        }
    }

    public function impersonate(User $user)
    {
        if (Auth::user()->can('impersonate-users'))
        {
            if ($user->id === Auth::id()) {
                return redirect()->route('users.index')->with('error', __('You cannot login as user yourself'));
            }

            if ($user->created_by !== creatorId()) {
                return redirect()->route('users.index')->with('error', __('Permission denied'));
            }

            // Store the original user ID in session
            Session::put('impersonator_id', Auth::id());

            // Login as the target user
            Auth::login($user);
        }
        else
        {
            return redirect()->route('users.index')->with('error', __('Permission denied'));
        }

        return redirect()->route('dashboard')->with('success', __('You are now login as user :name', ['name' => $user->name]));
    }

    public function leaveImpersonation()
    {
        if (!Session::has('impersonator_id')) {
            return redirect()->route('dashboard')->with('error', __('You are not login as user anyone'));
        }

        $originalUserId = Session::get('impersonator_id');
        $originalUser = User::find($originalUserId);

        if (!$originalUser) {
            Session::forget('impersonator_id');
            return redirect()->route('login')->with('error', __('Original user not found'));
        }

        Session::forget('impersonator_id');
        Auth::login($originalUser);

        return redirect()->route('users.index')->with('success', __('You have stopped login as user'));
    }

    public function loginHistory()
    {
        if(Auth::user()->can('view-login-history')){
            $loginHistories = LoginHistory::with('user')
                ->when(Auth::user()->type !== 'superadmin', fn($q) => $q->where('created_by', creatorId()))
                ->when(request('user_name'), fn($q) => $q->whereHas('user', fn($q) => $q->where('name', 'like', '%' . request('user_name') . '%')))
                ->when(request('ip'), fn($q) => $q->where('ip', 'like', '%' . request('ip') . '%'))
                ->when(request('role'), fn($q) => $q->whereHas('user', fn($q) => $q->where('type', request('role'))))
                ->tap(fn($q) => $this->applySafeSort($q, ['ip', 'created_at']))
                ->paginate($this->safePerPage(10, 100))
                ->withQueryString();

            $roles = Role::where('created_by', creatorId())->pluck('label', 'name');

            return Inertia::render('users/login-history', [
                'loginHistories' => $loginHistories,
                'roles' => $roles,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }
}
