<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContainerTrackingService
{
    /**
     * Known ISO 6346 container prefixes mapped to shipping lines
     */
    protected static array $carrierPrefixes = [
        'MSCU' => ['code' => 'msc', 'name' => 'MSC (Mediterranean Shipping Company)'],
        'MEDU' => ['code' => 'msc', 'name' => 'MSC (Mediterranean Shipping Company)'],
        'MAEU' => ['code' => 'maersk', 'name' => 'Maersk Line'],
        'MSKU' => ['code' => 'maersk', 'name' => 'Maersk Line'],
        'MRKU' => ['code' => 'maersk', 'name' => 'Maersk Line'],
        'APMU' => ['code' => 'maersk', 'name' => 'Maersk Line'],
        'CMAU' => ['code' => 'cma-cgm', 'name' => 'CMA CGM'],
        'CGMU' => ['code' => 'cma-cgm', 'name' => 'CMA CGM'],
        'COSU' => ['code' => 'cosco', 'name' => 'COSCO Shipping Lines'],
        'COHU' => ['code' => 'cosco', 'name' => 'COSCO Shipping Lines'],
        'CCLU' => ['code' => 'cosco', 'name' => 'COSCO Shipping Lines'],
        'HLCU' => ['code' => 'hapag-lloyd', 'name' => 'Hapag-Lloyd'],
        'HLXU' => ['code' => 'hapag-lloyd', 'name' => 'Hapag-Lloyd'],
        'EGLV' => ['code' => 'evergreen', 'name' => 'Evergreen Marine'],
        'EISU' => ['code' => 'evergreen', 'name' => 'Evergreen Marine'],
        'EMCU' => ['code' => 'evergreen', 'name' => 'Evergreen Marine'],
        'ONEY' => ['code' => 'one-line', 'name' => 'Ocean Network Express (ONE)'],
        'KKFU' => ['code' => 'one-line', 'name' => 'Ocean Network Express (ONE)'],
        'NYKU' => ['code' => 'one-line', 'name' => 'Ocean Network Express (ONE)'],
        'MOLU' => ['code' => 'one-line', 'name' => 'Ocean Network Express (ONE)'],
        'YMLU' => ['code' => 'yang-ming', 'name' => 'Yang Ming Marine Transport'],
        'ZIMU' => ['code' => 'zim', 'name' => 'ZIM Integrated Shipping'],
        'ESLU' => ['code' => 'eslse', 'name' => 'ESLSE (Ethiopian Shipping & Logistics)'],
    ];

    /**
     * Retrieve tracking details for a container
     */
    public function track(string $containerNumber, ?string $shippingLine = null): array
    {
        $containerNumber = strtoupper(trim($containerNumber));
        $carrier = $this->detectCarrier($containerNumber, $shippingLine);

        $settings = $this->getSettings();
        $provider = $settings['provider'] ?? 'trackingmore';
        $apiKey = $settings['apiKey'] ?? '';
        $isSandbox = (bool)($settings['sandboxMode'] ?? true);

        // If sandbox mode is explicitly enabled or no API key is provided, run simulation
        if ($isSandbox || empty($apiKey) || $provider === 'sandbox') {
            return $this->simulateTracking($containerNumber, $carrier, 'GadaaCloud Trade Simulator (Sandbox)');
        }

        try {
            if ($provider === 'trackingmore') {
                return $this->queryTrackingMore($containerNumber, $carrier, $apiKey);
            } elseif ($provider === 'shipsgo') {
                return $this->queryShipsGo($containerNumber, $carrier, $apiKey);
            }
        } catch (\Throwable $e) {
            Log::error("Container tracking API error [{$provider}]: " . $e->getMessage());
            // Fallback to simulation with notice
            $result = $this->simulateTracking($containerNumber, $carrier, "Fallback Simulator (Live API unreachable: {$e->getMessage()})");
            $result['api_error'] = $e->getMessage();
            return $result;
        }

        return $this->simulateTracking($containerNumber, $carrier, 'GadaaCloud Trade Simulator');
    }

    /**
     * Detect carrier code and display name from prefix or input
     */
    public function detectCarrier(string $containerNumber, ?string $shippingLine = null): array
    {
        $prefix = substr($containerNumber, 0, 4);

        if (isset(self::$carrierPrefixes[$prefix])) {
            return self::$carrierPrefixes[$prefix];
        }

        if (!empty($shippingLine)) {
            $lineLower = strtolower($shippingLine);
            if (str_contains($lineLower, 'msc')) {
                return ['code' => 'msc', 'name' => 'MSC (Mediterranean Shipping Company)'];
            }
            if (str_contains($lineLower, 'maersk')) {
                return ['code' => 'maersk', 'name' => 'Maersk Line'];
            }
            if (str_contains($lineLower, 'cma')) {
                return ['code' => 'cma-cgm', 'name' => 'CMA CGM'];
            }
            if (str_contains($lineLower, 'cosco')) {
                return ['code' => 'cosco', 'name' => 'COSCO Shipping Lines'];
            }
            if (str_contains($lineLower, 'eslse') || str_contains($lineLower, 'ethiopian')) {
                return ['code' => 'eslse', 'name' => 'ESLSE (Ethiopian Shipping & Logistics)'];
            }
        }

        return ['code' => 'general', 'name' => $shippingLine ?: 'Ocean Shipping Line'];
    }

    /**
     * Query TrackingMore Ocean Container Tracking API v4
     */
    protected function queryTrackingMore(string $containerNumber, array $carrier, string $apiKey): array
    {
        $carrierCode = $carrier['code'] !== 'general' ? $carrier['code'] : 'msc';

        // 1. First attempt to get existing tracking
        $response = Http::withHeaders([
            'Tracking-Api-Key' => $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(10)->get('https://api.trackingmore.com/v4/trackings/get', [
            'tracking_numbers' => $containerNumber,
        ]);

        $data = $response->json();

        // 2. If not found, register tracking number
        if ($response->successful() && (empty($data['data']) || $data['meta']['code'] === 4016)) {
            $createResponse = Http::withHeaders([
                'Tracking-Api-Key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post('https://api.trackingmore.com/v4/trackings/create', [
                'tracking_number' => $containerNumber,
                'carrier_code' => $carrierCode,
            ]);

            $createData = $createResponse->json();
            if ($createResponse->successful() && !empty($createData['data'])) {
                $data = ['data' => [$createData['data']]];
            }
        }

        if (!empty($data['data'][0])) {
            return $this->formatTrackingMoreResponse($data['data'][0], $containerNumber, $carrier);
        }

        throw new \Exception($data['meta']['message'] ?? 'No tracking records found on TrackingMore.');
    }

    /**
     * Format TrackingMore API payload to GadaaCloud standard tracking structure
     */
    protected function formatTrackingMoreResponse(array $raw, string $containerNumber, array $carrier): array
    {
        $status = strtolower($raw['delivery_status'] ?? 'transit');
        $milestones = [];

        if (!empty($raw['origin_info']['trackinfo'])) {
            foreach ($raw['origin_info']['trackinfo'] as $idx => $event) {
                $milestones[] = [
                    'date' => $event['Date'] ?? date('Y-m-d H:i'),
                    'location' => $event['Details'] ?? 'Sea Route',
                    'title' => $event['StatusDescription'] ?? 'Milestone update',
                    'description' => $event['checkpoint_status'] ?? '',
                    'completed' => true,
                    'current' => $idx === 0,
                ];
            }
        }

        $freeDays = intval(Setting::where('key', 'importexport_djibouti_free_days_threshold')->value('value') ?? 8);
        $dailyPenalty20ft = floatval(Setting::where('key', 'importexport_demurrage_daily_penalty_20ft')->value('value') ?? 45);

        return [
            'success' => true,
            'is_sandbox' => false,
            'provider' => 'TrackingMore (Live API)',
            'container_number' => $containerNumber,
            'carrier' => $carrier,
            'vessel' => [
                'name' => $raw['vessel_name'] ?? 'Ocean Carrier Vessel',
                'voyage' => $raw['voyage_number'] ?? 'V-2026',
                'imo' => $raw['vessel_imo'] ?? '9481234',
            ],
            'status' => $status,
            'status_label' => ucfirst($status),
            'route' => [
                'origin' => $raw['origin_info']['ItemReceived'] ?? 'Port of Loading',
                'transshipment' => 'Salalah / Jeddah Hub',
                'destination' => 'Port of Djibouti (Doraleh Container Terminal)',
                'final_destination' => 'Modjo Dry Port, Ethiopia',
            ],
            'timestamps' => [
                'etd' => $raw['origin_info']['Departure'] ?? null,
                'eta_djibouti' => $raw['destination_info']['Delivered'] ?? null,
            ],
            'demurrage' => [
                'free_days' => $freeDays,
                'days_in_port' => 2,
                'days_remaining' => max(0, $freeDays - 2),
                'is_overdue' => false,
                'daily_rate_usd' => $dailyPenalty20ft,
                'estimated_penalty_usd' => 0,
            ],
            'milestones' => $milestones,
            'raw' => $raw,
        ];
    }

    /**
     * Query ShipsGo Container Tracking API
     */
    protected function queryShipsGo(string $containerNumber, array $carrier, string $apiKey): array
    {
        $response = Http::withHeaders([
            'X-Api-Key' => $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(10)->post('https://shipsgo.com/api/v1.2/ContainerService/PostNormal', [
            'ContainerNumber' => $containerNumber,
            'ShippingLine' => $carrier['name'],
        ]);

        $data = $response->json();
        if ($response->successful() && !empty($data)) {
            return [
                'success' => true,
                'is_sandbox' => false,
                'provider' => 'ShipsGo (Live API)',
                'container_number' => $containerNumber,
                'carrier' => $carrier,
                'vessel' => [
                    'name' => $data['VesselName'] ?? 'Vessel',
                    'voyage' => $data['Voyage'] ?? 'V1',
                    'imo' => $data['VesselImo'] ?? '9321845',
                ],
                'status' => strtolower($data['Status'] ?? 'in_transit'),
                'status_label' => $data['Status'] ?? 'In Transit',
                'route' => [
                    'origin' => $data['FromPort'] ?? 'Port of Loading',
                    'transshipment' => 'Jeddah / Salalah',
                    'destination' => 'Port of Djibouti (DCT)',
                    'final_destination' => 'Modjo Dry Port, Ethiopia',
                ],
                'timestamps' => [
                    'eta_djibouti' => $data['DischargeDate'] ?? null,
                ],
                'demurrage' => [
                    'free_days' => 8,
                    'days_in_port' => 0,
                    'days_remaining' => 8,
                    'is_overdue' => false,
                    'estimated_penalty_usd' => 0,
                ],
                'milestones' => [],
                'raw' => $data,
            ];
        }

        throw new \Exception('ShipsGo API request failed or container not tracked.');
    }

    /**
     * High-fidelity Deterministic Container Tracking Simulator
     * Produces consistent, authentic container milestones tailored to the Ethiopia-Djibouti corridor
     */
    public function simulateTracking(string $containerNumber, array $carrier, string $engineLabel = 'GadaaCloud Simulation Engine'): array
    {
        // Use hash of container number to generate deterministic but realistic progress
        $hash = crc32($containerNumber);
        $stageIndex = abs($hash) % 4; // 0 = Loaded at Origin, 1 = Red Sea Transit, 2 = Arrived Djibouti Port, 3 = Discharged & Cleared

        $vessels = [
            'msc' => ['name' => 'MSC PALOMA', 'imo' => '9399002', 'voyage' => '245W'],
            'maersk' => ['name' => 'MAERSK MC-KINNEY MOLLER', 'imo' => '9619907', 'voyage' => '841E'],
            'cma-cgm' => ['name' => 'CMA CGM ANTOINE DE SAINT EXUPERY', 'imo' => '9776418', 'voyage' => '034W'],
            'cosco' => ['name' => 'COSCO SHIPPING SCORPIO', 'imo' => '9783540', 'voyage' => '089E'],
            'eslse' => ['name' => 'GIBE (ESLSE Vessel)', 'imo' => '9617349', 'voyage' => '012S'],
        ];

        $vessel = $vessels[$carrier['code']] ?? ['name' => 'MV RED SEA TRADER', 'imo' => '9448912', 'voyage' => '512S'];

        $originPorts = ['Ningbo-Zhoushan, China', 'Shanghai Port, China', 'Jebel Ali Port, UAE', 'Nhava Sheva, India', 'Antwerp, Belgium'];
        $originPort = $originPorts[abs($hash >> 2) % count($originPorts)];

        $freeDays = intval(Setting::where('key', 'importexport_djibouti_free_days_threshold')->value('value') ?? 8);
        $dailyPenalty20ft = floatval(Setting::where('key', 'importexport_demurrage_daily_penalty_20ft')->value('value') ?? 45);

        $now = time();
        $dateLoaded = date('Y-m-d H:i', $now - (22 * 86400));
        $dateDeparted = date('Y-m-d H:i', $now - (21 * 86400));
        $dateSalalah = date('Y-m-d H:i', $now - (8 * 86400));
        $dateRedSea = date('Y-m-d H:i', $now - (3 * 86400));
        $dateDjiboutiArrival = date('Y-m-d H:i', $now - (2 * 86400));
        $dateDischarge = date('Y-m-d H:i', $now - (1 * 86400));
        $etaDjibouti = date('Y-m-d H:i', $now + (3 * 86400));

        $milestones = [];
        $statusKey = 'transit';
        $statusLabel = 'In Transit';
        $daysInPort = 0;
        $daysRemaining = $freeDays;
        $isOverdue = false;
        $penaltyUsd = 0;

        if ($stageIndex === 0) {
            $statusKey = 'departed';
            $statusLabel = 'Vessel Departed Origin Port';
            $milestones = [
                ['date' => $dateDeparted, 'location' => $originPort, 'title' => 'Vessel Departed', 'description' => "Container sailing on {$vessel['name']} (Voyage: {$vessel['voyage']}).", 'completed' => true, 'current' => true],
                ['date' => $dateLoaded, 'location' => $originPort, 'title' => 'Container Loaded on Vessel', 'description' => 'Container sealed and gated into vessel bay.', 'completed' => true, 'current' => false],
                ['date' => date('Y-m-d H:i', $now - (24 * 86400)), 'location' => $originPort, 'title' => 'Gate-in at Origin Terminal', 'description' => 'Export customs cleared and verified gross mass (VGM) approved.', 'completed' => true, 'current' => false],
            ];
        } elseif ($stageIndex === 1) {
            $statusKey = 'in_transit';
            $statusLabel = 'In Transit - Bab-el-Mandeb / Red Sea';
            $milestones = [
                ['date' => $dateRedSea, 'location' => 'Gulf of Aden / Bab-el-Mandeb', 'title' => 'Approaching Djibouti Port Channel', 'description' => "Vessel {$vessel['name']} transiting coordinates 12.35°N 43.30°E. Pilot scheduled.", 'completed' => true, 'current' => true],
                ['date' => $dateSalalah, 'location' => 'Port of Salalah, Oman', 'title' => 'Transshipment Call', 'description' => 'Vessel bunkering and hub container transfer.', 'completed' => true, 'current' => false],
                ['date' => $dateDeparted, 'location' => $originPort, 'title' => 'Ocean Voyage Commenced', 'description' => 'Departed origin berth on scheduled ocean corridor.', 'completed' => true, 'current' => false],
            ];
        } elseif ($stageIndex === 2) {
            $statusKey = 'arrived_pod';
            $statusLabel = 'Berthed at Doraleh Container Terminal (Djibouti)';
            $daysInPort = 2;
            $daysRemaining = max(0, $freeDays - $daysInPort);
            $milestones = [
                ['date' => $dateDjiboutiArrival, 'location' => 'Doraleh Container Terminal (DCT), Djibouti', 'title' => 'Vessel Berthed at POD', 'description' => "Vessel {$vessel['name']} securely moored at Berth 2. Discharge operations underway.", 'completed' => true, 'current' => true],
                ['date' => $dateRedSea, 'location' => 'Red Sea', 'title' => 'Djibouti Harbor Master Clearance', 'description' => 'Pilot boarded and navigation channel cleared.', 'completed' => true, 'current' => false],
                ['date' => $dateSalalah, 'location' => 'Port of Salalah, Oman', 'title' => 'Feeder Vessel Transit', 'description' => 'Departed transshipment hub.', 'completed' => true, 'current' => false],
            ];
        } else {
            $statusKey = 'discharged';
            $statusLabel = 'Discharged at Djibouti Container Yard (Free Time Active)';
            $daysInPort = 3;
            $daysRemaining = max(0, $freeDays - $daysInPort);
            $isOverdue = $daysRemaining === 0;
            $penaltyUsd = $isOverdue ? ($daysInPort - $freeDays) * $dailyPenalty20ft : 0;
            $milestones = [
                ['date' => $dateDischarge, 'location' => 'Doraleh Container Terminal Yard, Djibouti', 'title' => 'Container Discharged to Yard', 'description' => "Container offloaded to yard stack 14-B. Official 8-day free demurrage period commenced.", 'completed' => true, 'current' => true],
                ['date' => $dateDjiboutiArrival, 'location' => 'Doraleh Container Terminal (DCT), Djibouti', 'title' => 'Vessel Berthed', 'description' => "Vessel {$vessel['name']} berthed.", 'completed' => true, 'current' => false],
                ['date' => $dateRedSea, 'location' => 'Bab-el-Mandeb', 'title' => 'Strait Transit', 'description' => 'Passed Bab-el-Mandeb corridor into Gulf of Tadjoura.', 'completed' => true, 'current' => false],
            ];
        }

        return [
            'success' => true,
            'is_sandbox' => true,
            'provider' => $engineLabel,
            'container_number' => $containerNumber,
            'carrier' => $carrier,
            'vessel' => $vessel,
            'status' => $statusKey,
            'status_label' => $statusLabel,
            'route' => [
                'origin' => $originPort,
                'transshipment' => 'Port of Salalah / Jeddah Hub',
                'destination' => 'Port of Djibouti (Doraleh Container Terminal)',
                'final_destination' => 'Modjo Dry Port, Ethiopia',
            ],
            'timestamps' => [
                'etd' => $dateDeparted,
                'atd' => $dateDeparted,
                'eta_djibouti' => ($stageIndex < 2) ? $etaDjibouti : $dateDjiboutiArrival,
                'ata_djibouti' => ($stageIndex >= 2) ? $dateDjiboutiArrival : null,
                'discharged_at' => ($stageIndex >= 3) ? $dateDischarge : null,
            ],
            'demurrage' => [
                'free_days' => $freeDays,
                'days_in_port' => $daysInPort,
                'days_remaining' => $daysRemaining,
                'is_overdue' => $isOverdue,
                'daily_rate_usd' => $dailyPenalty20ft,
                'estimated_penalty_usd' => $penaltyUsd,
            ],
            'milestones' => $milestones,
        ];
    }

    /**
     * Retrieve global ImportExport container API configuration
     */
    public function getSettings(): array
    {
        $settings = Setting::whereIn('key', [
            'importexport_container_api_provider',
            'importexport_container_api_key',
            'importexport_container_sandbox_mode',
            'importexport_container_webhook_secret',
            'importexport_djibouti_free_days_threshold',
            'importexport_demurrage_daily_penalty_20ft',
            'importexport_demurrage_daily_penalty_40ft',
        ])->pluck('value', 'key')->toArray();

        return [
            'provider' => $settings['importexport_container_api_provider'] ?? 'trackingmore',
            'apiKey' => $settings['importexport_container_api_key'] ?? '',
            'sandboxMode' => ($settings['importexport_container_sandbox_mode'] ?? '1') === '1',
            'webhookSecret' => $settings['importexport_container_webhook_secret'] ?? '',
            'freeDays' => intval($settings['importexport_djibouti_free_days_threshold'] ?? 8),
            'dailyPenalty20ft' => floatval($settings['importexport_demurrage_daily_penalty_20ft'] ?? 45),
            'dailyPenalty40ft' => floatval($settings['importexport_demurrage_daily_penalty_40ft'] ?? 85),
        ];
    }
}
