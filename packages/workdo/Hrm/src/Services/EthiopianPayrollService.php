<?php

namespace Workdo\Hrm\Services;

/**
 * Single source of truth for Ethiopian Employment Income Tax (PIT), POESSA/PSSSA Pension,
 * Transport Exemption, and Overtime statutory multipliers across Payroll & MoR Declarations.
 */
class EthiopianPayrollService
{
    /**
     * Default FDRE Proclamation No. 979/2016 monthly Employment Income Tax brackets.
     * Tenants can override via company setting `ethiopian_pit_brackets` JSON if statutory brackets change.
     */
    public const DEFAULT_PIT_BRACKETS = [
        ['max' => 600.0, 'rate' => 0.00, 'deduction' => 0.00],
        ['max' => 1650.0, 'rate' => 0.10, 'deduction' => 60.00],
        ['max' => 3200.0, 'rate' => 0.15, 'deduction' => 142.50],
        ['max' => 5250.0, 'rate' => 0.20, 'deduction' => 302.50],
        ['max' => 7800.0, 'rate' => 0.25, 'deduction' => 565.00],
        ['max' => 10900.0, 'rate' => 0.30, 'deduction' => 955.00],
        ['max' => null, 'rate' => 0.35, 'deduction' => 1500.00],
    ];

    /**
     * Split transport allowances cleanly into tax-exempt vs taxable excess
     * under Directive No. 02/2008 & Proc. No. 979/2016 (lower of 25% of basic salary or 2,200 ETB).
     */
    public function splitTransportAllowance(array $allowancesBreakdown, float $basicSalary, array $settings = []): array
    {
        $maxCap = isset($settings['ethiopian_transport_exempt_cap']) && is_numeric($settings['ethiopian_transport_exempt_cap'])
            ? (float) $settings['ethiopian_transport_exempt_cap']
            : 2200.0;

        $transportTotal = 0.0;
        $otherAllowances = 0.0;

        foreach ($allowancesBreakdown as $name => $amount) {
            $amt = (float) $amount;
            $lower = strtolower((string) $name);
            if (
                str_contains($lower, 'transport')
                || str_contains((string) $name, 'የትራንስፖርት')
            ) {
                $transportTotal += $amt;
            } else {
                $otherAllowances += $amt;
            }
        }

        $exemptionLimit = min($basicSalary * 0.25, $maxCap);
        $exemptTransport = min($transportTotal, max(0.0, $exemptionLimit));
        $taxableTransport = max(0.0, $transportTotal - $exemptTransport);

        return [
            'transport_total' => round($transportTotal, 2),
            'exempt_transport' => round($exemptTransport, 2),
            'taxable_transport' => round($taxableTransport, 2),
            'other_taxable_allowances' => round($otherAllowances, 2),
        ];
    }

    /**
     * Calculate monthly Ethiopian PIT & Pension contributions for an employee.
     */
    public function calculatePayrollTaxes(
        float $basicSalary,
        float $earnedBasicSalary,
        array $allowancesBreakdown,
        float $totalOvertimeEarned,
        bool $isPensionApplicable = true,
        bool $isTaxExempt = false,
        array $settings = []
    ): array {
        $empPensionRate = isset($settings['ethiopian_employee_pension_rate']) && is_numeric($settings['ethiopian_employee_pension_rate'])
            ? ((float) $settings['ethiopian_employee_pension_rate']) / 100
            : 0.07;

        $emprPensionRate = isset($settings['ethiopian_employer_pension_rate']) && is_numeric($settings['ethiopian_employer_pension_rate'])
            ? ((float) $settings['ethiopian_employer_pension_rate']) / 100
            : 0.11;

        $employeePension = $isPensionApplicable ? round($basicSalary * $empPensionRate, 2) : 0.0;
        $employerPension = $isPensionApplicable ? round($basicSalary * $emprPensionRate, 2) : 0.0;

        $transport = $this->splitTransportAllowance($allowancesBreakdown, $basicSalary, $settings);

        $taxableIncome = max(
            0.0,
            round(
                ($earnedBasicSalary + $transport['taxable_transport'] + $transport['other_taxable_allowances'] + $totalOvertimeEarned)
                - $employeePension,
                2
            )
        );

        $incomeTax = $isTaxExempt ? 0.0 : $this->computeProgressivePit($taxableIncome, $settings);

        return [
            'employee_pension' => $employeePension,
            'employer_pension' => $employerPension,
            'exempt_transport' => $transport['exempt_transport'],
            'taxable_transport' => $transport['taxable_transport'],
            'other_taxable_allowances' => $transport['other_taxable_allowances'],
            'taxable_income' => $taxableIncome,
            'income_tax' => $incomeTax,
        ];
    }

    public function computeProgressivePit(float $taxableIncome, array $settings = []): float
    {
        $brackets = self::DEFAULT_PIT_BRACKETS;
        if (!empty($settings['ethiopian_pit_brackets'])) {
            $decoded = is_string($settings['ethiopian_pit_brackets'])
                ? json_decode($settings['ethiopian_pit_brackets'], true)
                : $settings['ethiopian_pit_brackets'];
            if (is_array($decoded) && count($decoded) > 0) {
                $brackets = $decoded;
            }
        }

        foreach ($brackets as $bracket) {
            $max = $bracket['max'] ?? null;
            $rate = (float) ($bracket['rate'] ?? 0);
            $deduction = (float) ($bracket['deduction'] ?? 0);
            if ($max === null || $taxableIncome <= (float) $max) {
                return max(0.0, round(($taxableIncome * $rate) - $deduction, 2));
            }
        }

        return 0.0;
    }
}
