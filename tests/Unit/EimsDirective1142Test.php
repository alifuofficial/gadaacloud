<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\EIMS\Services\EimsPayloadBuilderService;
use Workdo\EIMS\Services\EimsQrCodeService;
use Workdo\EIMS\Services\EimsClientService;
use Workdo\EIMS\Models\EimsTenantSetting;
use Carbon\Carbon;

class EimsDirective1142Test extends TestCase
{
    public function test_eat_timestamp_format_directive_1142(): void
    {
        $service = new EimsPayloadBuilderService();
        $date = Carbon::parse('2026-11-22 12:00:00', 'UTC');
        $formatted = $service->formatEatTimestamp($date);

        // Must end with +03:00 and have millisecond precision
        $this->assertStringEndsWith('+03:00', $formatted);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{3}\+03:00$/', $formatted);
    }

    public function test_tin_validation(): void
    {
        $service = new EimsPayloadBuilderService();

        $this->assertTrue($service->isValidTin('0012345678'));
        $this->assertFalse($service->isValidTin('12345')); // too short
        $this->assertFalse($service->isValidTin('12345678901')); // too long
        $this->assertFalse($service->isValidTin('001234567A')); // non-numeric
        $this->assertFalse($service->isValidTin(null));
    }

    public function test_qr_code_payload_and_svg_generation(): void
    {
        $qrService = new EimsQrCodeService();
        $payload = $qrService->buildQrPayload(
            '0012345678',
            'INV-0001',
            '2026-11-22 15:30:00.000+03:00',
            1150.00,
            150.00,
            'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90'
        );

        $this->assertStringContainsString('TIN:0012345678', $payload);
        $this->assertStringContainsString('INV:INV-0001', $payload);
        $this->assertStringContainsString('TOT:1150.00', $payload);
        $this->assertStringContainsString('VAT:150.00', $payload);

        $svg = $qrService->generateSvg($payload, 150);
        $this->assertStringContainsString('<svg', $svg);

        $dataUri = $qrService->generateDataUri($payload, 150);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);
    }

    public function test_client_sandbox_clearance_returns_irn(): void
    {
        $client = new EimsClientService();
        $setting = new EimsTenantSetting([
            'tin' => '0098765432',
            'legal_name' => 'Test Company PLC',
            'branch_code' => 'MAIN',
            'device_id' => 'POS-01',
            'environment' => 'sandbox',
            'api_secret' => 'test-secret-key-123',
        ]);

        $payload = [
            'invoiceNumber' => 'INV-TEST-001',
            'invoiceDate' => '2026-11-22 15:00:00.000+03:00',
            'summary' => [
                'totalAmount' => 1150.00,
                'taxAmount' => 150.00,
            ]
        ];

        $response = $client->registerInvoice($payload, $setting);

        $this->assertTrue($response['success']);
        $this->assertEquals('CLEARED', $response['status']);
        $this->assertEquals(64, strlen($response['irn'])); // 64-char hex SHA256
        $this->assertNotEmpty($response['qrCodeData']);
    }
}
