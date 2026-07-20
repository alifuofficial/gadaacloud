<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DynadotService
{
    /**
     * Get the base API url based on admin settings
     */
    private static function getBaseUrl(array $settings): string
    {
        $mode = $settings['dynadot_mode'] ?? 'sandbox';
        return $mode === 'production' 
            ? 'https://api.dynadot.com/api3.json' 
            : 'https://api-sandbox.dynadot.com/api3.json';
    }

    /**
     * Get Dynadot API Key from settings
     */
    private static function getApiKey(array $settings): ?string
    {
        return $settings['dynadot_api_key'] ?? null;
    }

    /**
     * Check domain availability
     * Returns ['available' => boolean, 'domain' => string, 'error' => ?string]
     */
    public static function checkAvailability(string $domain): array
    {
        try {
            $settings = getAdminAllSetting();
            $apiKey = self::getApiKey($settings);

            if (empty($apiKey)) {
                return [
                    'available' => false,
                    'domain' => $domain,
                    'error' => __('Dynadot API is not configured. Contact admin.')
                ];
            }

            $baseUrl = self::getBaseUrl($settings);

            $response = Http::get($baseUrl, [
                'key' => $apiKey,
                'command' => 'search',
                'domain0' => $domain
            ]);

            if ($response->failed()) {
                return [
                    'available' => false,
                    'domain' => $domain,
                    'error' => __('Failed to connect to Dynadot API.')
                ];
            }

            $data = $response->json();
            
            if (!isset($data['SearchResponse'])) {
                return [
                    'available' => false,
                    'domain' => $domain,
                    'error' => __('Invalid API response structure.')
                ];
            }

            $header = $data['SearchResponse']['SearchHeader'] ?? [];
            $successCode = $header['SuccessCode'] ?? -1;

            if ($successCode != 0) {
                return [
                    'available' => false,
                    'domain' => $domain,
                    'error' => $header['Error'] ?? __('Unknown Dynadot API error.')
                ];
            }

            $results = $data['SearchResponse']['SearchContent']['Results'] ?? [];
            $domainResult = collect($results)->firstWhere('Domain', $domain);

            if (!$domainResult) {
                return [
                    'available' => false,
                    'domain' => $domain,
                    'error' => __('Domain search result not found.')
                ];
            }

            return [
                'available' => strtolower($domainResult['Available'] ?? '') === 'yes',
                'domain' => $domain,
                'error' => null
            ];

        } catch (\Exception $e) {
            Log::error('Dynadot search error', ['error' => $e->getMessage()]);
            return [
                'available' => false,
                'domain' => $domain,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Register domain
     * Returns ['success' => boolean, 'expires_at' => ?string, 'error' => ?string]
     */
    public static function registerDomain(string $domain, int $duration = 1): array
    {
        try {
            $settings = getAdminAllSetting();
            $apiKey = self::getApiKey($settings);

            if (empty($apiKey)) {
                return [
                    'success' => false,
                    'expires_at' => null,
                    'error' => __('Dynadot API is not configured. Contact admin.')
                ];
            }

            $baseUrl = self::getBaseUrl($settings);

            $response = Http::get($baseUrl, [
                'key' => $apiKey,
                'command' => 'register',
                'domain' => $domain,
                'duration' => $duration
            ]);

            if ($response->failed()) {
                return [
                    'success' => false,
                    'expires_at' => null,
                    'error' => __('Failed to connect to Dynadot API.')
                ];
            }

            $data = $response->json();

            if (!isset($data['RegisterResponse'])) {
                return [
                    'success' => false,
                    'expires_at' => null,
                    'error' => __('Invalid API response structure.')
                ];
            }

            $header = $data['RegisterResponse']['RegisterHeader'] ?? [];
            $successCode = $header['SuccessCode'] ?? -1;

            if ($successCode != 0) {
                return [
                    'success' => false,
                    'expires_at' => null,
                    'error' => $header['Error'] ?? __('Unknown Dynadot API registration error.')
                ];
            }

            $content = $data['RegisterResponse']['RegisterContent'] ?? [];
            $expirationMs = $content['Expiration'] ?? null;
            $expiresAt = null;

            if ($expirationMs) {
                // Expiration in epoch ms
                $expiresAt = date('Y-m-d H:i:s', intval($expirationMs / 1000));
            } else {
                $expiresAt = date('Y-m-d H:i:s', strtotime("+{$duration} years"));
            }

            return [
                'success' => true,
                'expires_at' => $expiresAt,
                'error' => null
            ];

        } catch (\Exception $e) {
            Log::error('Dynadot registration error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'expires_at' => null,
                'error' => $e->getMessage()
            ];
        }
    }
}
