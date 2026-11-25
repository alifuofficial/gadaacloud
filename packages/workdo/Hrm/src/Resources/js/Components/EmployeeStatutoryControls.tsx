import { useTranslation } from 'react-i18next';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';

interface EmployeeStatutoryControlsProps {
    basicSalary: string | number;
    hoursPerDay: string | number;
    daysPerWeek: string | number;
    isPensionApplicable: boolean;
    isTaxExempt: boolean;
    onChange: (field: 'is_pension_applicable' | 'is_tax_exempt', value: boolean) => void;
}

/**
 * Shared Ethiopian POESSA Pension (7% / 11%) & Employment Income Tax toggles
 * with live loaded hourly cost preview for HRM Employee Create/Edit forms.
 */
export function EmployeeStatutoryControls({
    basicSalary,
    hoursPerDay,
    daysPerWeek,
    isPensionApplicable,
    isTaxExempt,
    onChange,
}: EmployeeStatutoryControlsProps) {
    const { t } = useTranslation();
    const basic = parseFloat(String(basicSalary || '0')) || 0;
    const hpd = parseFloat(String(hoursPerDay || '8')) || 8;
    const dpw = parseFloat(String(daysPerWeek || '6')) || 6;
    const monthlyHours = Math.max(1, hpd * dpw * 4.333);
    const employerPension = isPensionApplicable ? basic * 0.11 : 0;
    const loadedHourly = basic > 0 ? ((basic + employerPension) / monthlyHours).toFixed(2) : '0.00';

    return (
        <div className="border-t pt-4 space-y-4">
            <h4 className="text-sm font-semibold text-muted-foreground uppercase tracking-wide">
                {t('Ethiopian Statutory Tax, Pension & Loaded Costing')}
            </h4>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="flex items-center justify-between rounded-lg border p-3">
                    <div>
                        <Label htmlFor="is_pension_applicable" className="font-medium cursor-pointer">
                            {t('Subject to POESSA Pension (7% / 11%)')}
                        </Label>
                        <p className="text-xs text-muted-foreground">
                            {t('Disable for casual, contract, or expatriate exempt staff')}
                        </p>
                    </div>
                    <Switch
                        id="is_pension_applicable"
                        checked={isPensionApplicable}
                        onCheckedChange={(v) => onChange('is_pension_applicable', v)}
                    />
                </div>
                <div className="flex items-center justify-between rounded-lg border p-3">
                    <div>
                        <Label htmlFor="is_tax_exempt" className="font-medium cursor-pointer">
                            {t('Exempt from Employment PIT Withholding')}
                        </Label>
                        <p className="text-xs text-muted-foreground">
                            {t('Special diplomatic or legally PIT-exempt engagement')}
                        </p>
                    </div>
                    <Switch
                        id="is_tax_exempt"
                        checked={isTaxExempt}
                        onCheckedChange={(v) => onChange('is_tax_exempt', v)}
                    />
                </div>
            </div>
            {basic > 0 && (
                <p className="text-xs text-muted-foreground">
                    {t('Estimated Employer 11% Pension Cost')}: <span className="font-semibold text-foreground">{employerPension.toFixed(2)}</span>
                    {' • '}
                    {t('Loaded Project Hourly Cost')}: <span className="font-semibold text-foreground">{loadedHourly}</span>
                </p>
            )}
        </div>
    );
}
