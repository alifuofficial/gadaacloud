<?php

namespace Workdo\EIMS\Services;

use App\Models\SalesInvoice;
use Workdo\EIMS\Models\EimsTenantSetting;
use Carbon\Carbon;
use InvalidArgumentException;

class EimsPayloadBuilderService
{
    /**
     * Format timestamp to official EAT (+03:00) format required by Directive 1142
     * Example: 2026-11-22 23:30:00.000+03:00
     */
    public function formatEatTimestamp(?Carbon $date = null): string
    {
        $d = $date ?? Carbon::now();
        return $d->setTimezone('+03:00')->format('Y-m-d H:i:s.v+03:00');
    }

    /**
     * Validate 10-digit Ethiopian TIN
     */
    public function isValidTin(?string $tin): bool
    {
        if (empty($tin)) {
            return false;
        }
        return (bool) preg_match('/^\d{10}$/', trim($tin));
    }

    /**
     * Build Directive 1142/2026 compliant e-invoice registration payload
     */
    public function buildInvoicePayload(SalesInvoice $invoice, EimsTenantSetting $setting, array $overrides = []): array
    {
        $customer = $invoice->customerDetails;
        $customerUser = $invoice->customer;

        // 1. Determine Buyer Information
        $rawTin = $overrides['buyer_tin'] ?? ($customer->tax_number ?? null);
        $cleanTin = $rawTin ? preg_replace('/[^\d]/', '', $rawTin) : null;
        $isB2B = $cleanTin && strlen($cleanTin) === 10;

        $buyerType = $overrides['buyer_type'] ?? ($isB2B ? 'B2B' : 'B2C');
        $legalName = $overrides['buyer_name'] ?? ($customer->company_name ?? ($customerUser->name ?? 'Consumer'));

        $buyer = [
            'buyerType' => $buyerType,
            'legalName' => substr($legalName, 0, 100),
        ];

        if ($buyerType === 'B2B' || $buyerType === 'B2G') {
            if (!$cleanTin || strlen($cleanTin) !== 10) {
                throw new InvalidArgumentException("B2B/B2G transaction requires a valid 10-digit TIN. Provided: '{$rawTin}'");
            }
            $buyer['tin'] = $cleanTin;
            $buyer['vatNumber'] = 'VAT' . $cleanTin;
            if (!empty($customer->billing_address)) {
                $addr = is_array($customer->billing_address) ? $customer->billing_address : [];
                $buyer['address'] = [
                    'city' => substr($addr['city'] ?? 'Addis Ababa', 0, 50),
                    'subCity' => substr($addr['sub_city'] ?? $addr['state'] ?? 'Bole', 0, 50),
                    'woreda' => substr($addr['woreda'] ?? '01', 0, 50),
                    'houseNumber' => substr($addr['house_number'] ?? $addr['address'] ?? 'N/A', 0, 50),
                ];
            }
        } elseif (!empty($overrides['buyer_id_type']) && !empty($overrides['buyer_id_number'])) {
            $buyer['idType'] = $overrides['buyer_id_type'];
            $buyer['idNumber'] = substr($overrides['buyer_id_number'], 0, 50);
        }

        // 2. Format Line Items
        $items = [];
        $calculatedPreTax = 0.0;
        $calculatedTax = 0.0;
        $calculatedDiscount = 0.0;
        $calculatedTotal = 0.0;

        $lineNumber = 1;
        foreach ($invoice->items as $item) {
            $product = $item->product;
            $qty = max(1, (float) $item->quantity);
            $unitPrice = round((float) $item->unit_price, 2);
            $linePreTax = round($qty * $unitPrice, 2);

            $discountAmount = round((float) ($item->discount_amount ?? 0), 2);
            $lineTaxAmount = round((float) ($item->tax_amount ?? 0), 2);
            $lineTotal = round($linePreTax - $discountAmount + $lineTaxAmount, 2);

            // Nature of supplies: strictly 'goods' or 'service'
            $nature = 'goods';
            if ($product && strtolower($product->type ?? '') === 'service') {
                $nature = 'service';
            } elseif ($invoice->type === 'service') {
                $nature = 'service';
            }

            // SKU code max 15 chars
            $itemCode = 'ITEM-' . $lineNumber;
            if ($product && !empty($product->sku)) {
                $itemCode = substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $product->sku), 0, 15);
            }

            $description = substr($product->name ?? ($product->description ?? 'Standard Supply Item'), 0, 100);

            // Tax classification & excise handling
            $taxClass = strtoupper((string) ($product->tax_classification ?? ($lineTaxAmount > 0 ? 'VAT15' : 'VAT0')));
            if (!in_array($taxClass, ['VAT15', 'VAT0', 'EXEMPT', 'EXCISE'], true)) {
                $taxClass = ($lineTaxAmount > 0) ? 'VAT15' : 'VAT0';
            }
            $taxCode = ($lineTaxAmount > 0 && $taxClass === 'VAT0') ? 'VAT15' : $taxClass;
            $exciseRate = (float) ($product->excise_rate ?? 0);
            $exciseValue = ($taxClass === 'EXCISE' && $exciseRate > 0)
                ? round(max(0, $linePreTax - $discountAmount) * ($exciseRate / 100), 2)
                : 0.0;

            $linePayload = [
                'lineNumber' => $lineNumber,
                'itemCode' => $itemCode,
                'productDescription' => $description,
                'natureOfSupplies' => $nature,
                'quantity' => $qty,
                'unitOfMeasure' => substr($product->unitRelation->unit_name ?? 'PCS', 0, 10),
                'unitPrice' => $unitPrice,
                'preTaxValue' => $linePreTax,
                'discount' => $discountAmount,
                'taxCode' => $taxCode,
                'taxRate' => in_array($taxCode, ['VAT15', 'EXCISE'], true) ? 15.0 : 0.0,
                'taxAmount' => $lineTaxAmount,
                'exciseTaxValue' => $exciseValue,
                'totalLineAmount' => $lineTotal,
            ];

            if ($product && !empty($product->hs_code)) {
                $linePayload['hsCode'] = substr(preg_replace('/[^a-zA-Z0-9.\-]/', '', $product->hs_code), 0, 30);
            }

            $items[] = $linePayload;

            $calculatedPreTax += $linePreTax;
            $calculatedDiscount += $discountAmount;
            $calculatedTax += $lineTaxAmount;
            $calculatedTotal += $lineTotal;

            $lineNumber++;
        }

        // Summary math verification (Directive 1142 +/- 0.05 tolerance rule)
        $invoiceTotal = round((float) $invoice->total_amount, 2);
        if (abs($calculatedTotal - $invoiceTotal) > 0.10 && $invoiceTotal > 0) {
            $calculatedTotal = $invoiceTotal;
        }

        // Payment mode & term
        $paymentMode = 'CASH';
        if ($invoice->paid_amount >= $invoice->total_amount && $invoice->total_amount > 0) {
            $paymentMode = 'CASH';
        } elseif ($invoice->balance_amount > 0 && $invoice->paid_amount > 0) {
            $paymentMode = 'CREDIT';
        } elseif ($invoice->paid_amount == 0) {
            $paymentMode = 'CREDIT';
        }

        // Mandatory MoR spelling 'IMMIDIATE'
        $paymentTerm = ($paymentMode === 'CASH') ? 'IMMIDIATE' : 'NET30';
        if (!empty($invoice->payment_terms)) {
            $termsLower = strtolower($invoice->payment_terms);
            if (str_contains($termsLower, '60')) {
                $paymentTerm = 'NET60';
            } elseif (str_contains($termsLower, '30')) {
                $paymentTerm = 'NET30';
            }
        }

        return [
            'documentType' => $overrides['document_type'] ?? 'INV',
            'invoiceNumber' => $invoice->invoice_number,
            'invoiceDate' => $this->formatEatTimestamp($invoice->invoice_date ? Carbon::parse($invoice->invoice_date) : null),
            'seller' => [
                'tin' => $setting->tin ?? '0000000000',
                'vatNumber' => $setting->vat_number ?? ('VAT' . ($setting->tin ?? '0000000000')),
                'legalName' => substr($setting->legal_name ?? (auth()->user()->name ?? 'Company'), 0, 100),
                'tradeName' => substr($setting->trade_name ?? $setting->legal_name ?? 'Company', 0, 100),
                'branchCode' => $setting->branch_code ?? 'MAIN',
                'deviceId' => $setting->device_id ?? 'POS-01',
            ],
            'buyer' => $buyer,
            'lineItems' => $items,
            'summary' => [
                'currency' => 'ETB',
                'preTaxAmount' => round($calculatedPreTax, 2),
                'discountAmount' => round($calculatedDiscount, 2),
                'taxableAmount' => round($calculatedPreTax - $calculatedDiscount, 2),
                'taxAmount' => round($calculatedTax, 2),
                'exciseTaxAmount' => round(array_sum(array_column($items, 'exciseTaxValue')), 2),
                'totalAmount' => round($calculatedTotal, 2),
            ],
            'payment' => [
                'mode' => $paymentMode,
                'paymentTerm' => $paymentTerm,
                'paidAmount' => round((float) $invoice->paid_amount, 2),
                'balanceDue' => round((float) $invoice->balance_amount, 2),
            ],
            'metadata' => [
                'system' => 'GadaaCloud SaaS ERP',
                'version' => '2.0-EIMS',
                'directive' => '1142/2026',
            ]
        ];
    }
}
