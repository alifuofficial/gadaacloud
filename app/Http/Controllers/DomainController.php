<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Services\DynadotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DomainController extends Controller
{
    /**
     * Domain Settings Dashboard (Company View)
     */
    public function index()
    {
        if (!Auth::user()->can('manage-settings')) {
            return redirect()->back()->with('error', __('Permission denied'));
        }

        $userId = creatorId();
        
        // Find or create tenant domain record
        $tenantDomain = DB::table('tenant_domains')->where('user_id', $userId)->first();
        
        if (!$tenantDomain) {
            $user = User::find($userId);
            $subdomain = $user->slug ?? strtolower(preg_replace('/[^A-Za-z0-9]/', '', $user->name));
            
            DB::table('tenant_domains')->insert([
                'user_id' => $userId,
                'subdomain' => $subdomain,
                'custom_domain_status' => 'pending_dns',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            $tenantDomain = DB::table('tenant_domains')->where('user_id', $userId)->first();
        }

        $baseDomain = parse_url(config('app.url'), PHP_URL_HOST);
        $serverIp = gethostbyname($baseDomain);
        
        $settings = getAdminAllSetting();
        $markup = floatval($settings['dynadot_markup_percentage'] ?? 0);
        
        // Baseline pricing
        $basePrices = [
            'com' => 12.00,
            'net' => 15.00,
            'org' => 14.00,
            'info' => 16.00,
            'et' => 25.00
        ];
        
        $tldPrices = [];
        foreach ($basePrices as $tld => $basePrice) {
            $tldPrices[$tld] = round($basePrice * (1 + $markup / 100), 2);
        }

        $orders = DB::table('tenant_domain_orders')
            ->where('user_id', $userId)
            ->orderBy('id', 'desc')
            ->get();

        return Inertia::render('settings/domains', [
            'tenantDomain' => $tenantDomain,
            'baseDomain' => $baseDomain,
            'serverIp' => $serverIp,
            'tldPrices' => $tldPrices,
            'orders' => $orders
        ]);
    }

    /**
     * Update Subdomain
     */
    public function saveSubdomain(Request $request)
    {
        $request->validate([
            'subdomain' => 'required|alpha_dash|max:50|unique:tenant_domains,subdomain,' . creatorId() . ',user_id'
        ]);

        $userId = creatorId();
        
        DB::table('tenant_domains')
            ->where('user_id', $userId)
            ->update([
                'subdomain' => strtolower($request->subdomain),
                'updated_at' => now()
            ]);

        return redirect()->back()->with('success', __('Subdomain updated successfully.'));
    }

    /**
     * Map Custom Domain & Check DNS
     */
    public function verifyCustomDomain(Request $request)
    {
        $request->validate([
            'custom_domain' => 'required|string|max:100'
        ]);

        $userId = creatorId();
        $customDomain = strtolower(trim($request->custom_domain));
        
        // Remove protocol (http/https) and path if entered
        $customDomain = preg_replace('/^https?:\/\//', '', $customDomain);
        $customDomain = explode('/', $customDomain)[0];

        // Unique validation excluding this user
        $existing = DB::table('tenant_domains')
            ->where('custom_domain', $customDomain)
            ->where('user_id', '!=', $userId)
            ->exists();

        if ($existing) {
            return redirect()->back()->with('error', __('This domain is already mapped to another workspace.'));
        }

        // Perform DNS lookup
        $baseDomain = parse_url(config('app.url'), PHP_URL_HOST);
        $serverIp = gethostbyname($baseDomain);
        
        $records = @dns_get_record($customDomain, DNS_A);
        $ips = collect($records)->pluck('ip')->toArray();
        
        $dnsVerified = in_array($serverIp, $ips);

        DB::table('tenant_domains')
            ->where('user_id', $userId)
            ->update([
                'custom_domain' => $customDomain,
                'custom_domain_status' => $dnsVerified ? 'active' : 'pending_dns',
                'updated_at' => now()
            ]);

        if ($dnsVerified) {
            return redirect()->back()->with('success', __('Domain mapped and DNS records verified successfully!'));
        } else {
            return redirect()->back()->with('warning', __('Domain saved but DNS records do not match GadaaCloud server IP yet.'));
        }
    }

    /**
     * Upload Custom SSL Certificate (Comodo/Sectigo)
     */
    public function saveCustomSsl(Request $request)
    {
        $request->validate([
            'custom_ssl_certificate' => 'required|string',
            'custom_ssl_private_key' => 'required|string'
        ]);

        DB::table('tenant_domains')
            ->where('user_id', creatorId())
            ->update([
                'custom_ssl_certificate' => $request->custom_ssl_certificate,
                'custom_ssl_private_key' => $request->custom_ssl_private_key,
                'updated_at' => now()
            ]);

        return redirect()->back()->with('success', __('SSL Certificate and Private Key uploaded successfully.'));
    }

    /**
     * Search Domain Availability (Company View)
     */
    public function search(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|max:100'
        ]);

        $domain = strtolower(trim($request->domain));
        
        // Basic domain validation regex
        if (!preg_match('/^[a-zA-Z0-9-]+\.[a-zA-Z]{2,}$/', $domain)) {
            return response()->json(['error' => __('Invalid domain format. Enter e.g. company.com')], 422);
        }

        $res = DynadotService::checkAvailability($domain);

        if ($res['error']) {
            return response()->json(['error' => $res['error']], 400);
        }

        $settings = getAdminAllSetting();
        $markup = floatval($settings['dynadot_markup_percentage'] ?? 0);
        
        // Calculate cost based on TLD
        $tld = pathinfo($domain, PATHINFO_EXTENSION);
        $basePrices = [
            'com' => 12.00,
            'net' => 15.00,
            'org' => 14.00,
            'info' => 16.00,
            'et' => 25.00
        ];
        
        $basePrice = $basePrices[$tld] ?? 15.00;
        $price = round($basePrice * (1 + $markup / 100), 2);

        return response()->json([
            'domain' => $domain,
            'available' => $res['available'],
            'price' => $price,
            'currency' => 'USD'
        ]);
    }

    /**
     * Purchase / Register Domain (Company view checkout helper)
     */
    public function purchase(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|max:100',
            'duration' => 'required|integer|min:1|max:10',
            'price' => 'required|numeric'
        ]);

        $userId = creatorId();
        $domain = strtolower(trim($request->domain));

        // Create domain order
        $orderId = DB::table('tenant_domain_orders')->insertGetId([
            'user_id' => $userId,
            'domain' => $domain,
            'payment_gateway' => 'mock_gateway',
            'payment_status' => 'paid', // Marked paid for simulation
            'transaction_id' => 'TXN_' . strtoupper(uniqid()),
            'amount' => $request->price,
            'currency' => 'USD',
            'dynadot_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Register domain via Dynadot API
        $reg = DynadotService::registerDomain($domain, intval($request->duration));

        if ($reg['success']) {
            DB::table('tenant_domain_orders')
                ->where('id', $orderId)
                ->update([
                    'dynadot_status' => 'completed',
                    'updated_at' => now()
                ]);

            DB::table('tenant_domains')
                ->where('user_id', $userId)
                ->update([
                    'custom_domain' => $domain,
                    'custom_domain_status' => 'active',
                    'is_registered_via_dynadot' => true,
                    'registration_expires_at' => $reg['expires_at'],
                    'updated_at' => now()
                ]);

            return redirect()->back()->with('success', __('Domain registered and mapped successfully!'));
        } else {
            DB::table('tenant_domain_orders')
                ->where('id', $orderId)
                ->update([
                    'dynadot_status' => 'failed',
                    'dynadot_error' => $reg['error'],
                    'updated_at' => now()
                ]);

            return redirect()->back()->with('error', __('Failed to register domain via Dynadot API: ') . $reg['error']);
        }
    }

    /**
     * Domain Management Control Panel (Superadmin View)
     */
    public function adminIndex()
    {
        if (!Auth::user()->hasRole('superadmin')) {
            return redirect()->back()->with('error', __('Permission denied'));
        }

        $domains = DB::table('tenant_domains')
            ->join('users', 'tenant_domains.user_id', '=', 'users.id')
            ->select('tenant_domains.*', 'users.name as company_name', 'users.email as company_email')
            ->orderBy('tenant_domains.id', 'desc')
            ->get();

        $settings = getAdminAllSetting();

        return Inertia::render('settings/admin_domains', [
            'domains' => $domains,
            'dynadot_api_key' => $settings['dynadot_api_key'] ?? '',
            'dynadot_mode' => $settings['dynadot_mode'] ?? 'sandbox',
            'dynadot_markup_percentage' => $settings['dynadot_markup_percentage'] ?? '0'
        ]);
    }

    /**
     * Save Dynadot API Reseller configuration (Superadmin only)
     */
    public function updateDynadotSettings(Request $request)
    {
        if (!Auth::user()->hasRole('superadmin')) {
            return redirect()->back()->with('error', __('Permission denied'));
        }

        $request->validate([
            'dynadot_api_key' => 'nullable|string|max:255',
            'dynadot_mode' => 'required|in:sandbox,production',
            'dynadot_markup_percentage' => 'required|numeric|min:0|max:500'
        ]);

        setSetting('dynadot_api_key', $request->dynadot_api_key);
        setSetting('dynadot_mode', $request->dynadot_mode);
        setSetting('dynadot_markup_percentage', $request->dynadot_markup_percentage);

        return redirect()->back()->with('success', __('Dynadot Domain Reseller settings saved successfully.'));
    }

    /**
     * Suspend / Activate Domain Mapping (Superadmin control option)
     */
    public function toggleStatus(Request $request, $id)
    {
        if (!Auth::user()->hasRole('superadmin')) {
            return redirect()->back()->with('error', __('Permission denied'));
        }

        $tenantDomain = DB::table('tenant_domains')->where('id', $id)->first();
        if (!$tenantDomain) {
            return redirect()->back()->with('error', __('Domain mapping not found.'));
        }

        $newStatus = $tenantDomain->custom_domain_status === 'active' ? 'suspended' : 'active';
        
        DB::table('tenant_domains')
            ->where('id', $id)
            ->update([
                'custom_domain_status' => $newStatus,
                'updated_at' => now()
            ]);

        return redirect()->back()->with('success', __('Domain status updated to ') . __($newStatus));
    }
}
