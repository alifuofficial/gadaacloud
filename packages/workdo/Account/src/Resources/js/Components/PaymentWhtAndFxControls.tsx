import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { formatCurrency } from '@/utils/helpers';

const CURRENCIES = ['ETB', 'USD', 'EUR', 'GBP', 'AED', 'CNY', 'KES', 'DJF'];

interface PaymentWhtAndFxControlsProps {
    mode: 'vendor' | 'customer';
    grossAllocationTotal: number;
    whtEnabled: boolean;
    whtRate: string;
    whtAmount: string;
    currencyCode: string;
    exchangeRate: string;
    foreignAmount: string;
    onToggleWht: (enabled: boolean) => void;
    onWhtRateChange: (rate: string) => void;
    onWhtAmountChange: (amount: string) => void;
    onCurrencyChange: (currency: string) => void;
    onExchangeRateChange: (rate: string) => void;
    onForeignAmountChange: (amount: string) => void;
}

export default function PaymentWhtAndFxControls({
    mode,
    grossAllocationTotal,
    whtEnabled,
    whtRate,
    whtAmount,
    currencyCode,
    exchangeRate,
    foreignAmount,
    onToggleWht,
    onWhtRateChange,
    onWhtAmountChange,
    onCurrencyChange,
    onExchangeRateChange,
    onForeignAmountChange,
}: PaymentWhtAndFxControlsProps) {
    const { t } = useTranslation();
    const isForeign = currencyCode !== 'ETB';
    const numericWht = Number(whtAmount || 0);
    const netEtbCash = Math.max(0, grossAllocationTotal - numericWht);
    const fxConvertedEtb = isForeign ? Number(foreignAmount || 0) * Number(exchangeRate || 1) : netEtbCash;
    const estimatedFxVariance = isForeign
        ? (mode === 'vendor' ? netEtbCash - fxConvertedEtb : fxConvertedEtb - netEtbCash)
        : 0;

    return (
        <Card className="border-amber-200/80 bg-amber-50/30">
            <CardHeader className="py-3 px-4 border-b">
                <CardTitle className="text-sm font-semibold flex items-center justify-between">
                    <span>{t('Ethiopian MoR Withholding Tax (2% WHT) & Multi-Currency Settlement')}</span>
                    <div className="flex items-center gap-2 font-normal">
                        <Switch
                            id={`wht-toggle-${mode}`}
                            checked={whtEnabled}
                            onCheckedChange={(checked) => onToggleWht(!!checked)}
                        />
                        <Label htmlFor={`wht-toggle-${mode}`} className="cursor-pointer text-xs">
                            {mode === 'vendor'
                                ? t('Withhold 2% MoR WHT')
                                : t('Customer Deducted 2% WHT')}
                        </Label>
                    </div>
                </CardTitle>
            </CardHeader>
            <CardContent className="p-4 space-y-3">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                        <Label className="text-xs">{t('Settlement Currency')}</Label>
                        <Select value={currencyCode || 'ETB'} onValueChange={onCurrencyChange}>
                            <SelectTrigger className="mt-1">
                                <SelectValue placeholder="ETB" />
                            </SelectTrigger>
                            <SelectContent>
                                {CURRENCIES.map((code) => (
                                    <SelectItem key={code} value={code}>
                                        {code}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {isForeign && (
                        <>
                            <div>
                                <Label className="text-xs">{t('Foreign Amount (:cur)', { cur: currencyCode })}</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    className="mt-1"
                                    value={foreignAmount}
                                    onChange={(e) => onForeignAmountChange(e.target.value)}
                                />
                            </div>
                            <div>
                                <Label className="text-xs">{t('Exchange Rate (ETB per 1 :cur)', { cur: currencyCode })}</Label>
                                <Input
                                    type="number"
                                    step="0.0001"
                                    min="0.0001"
                                    className="mt-1"
                                    value={exchangeRate}
                                    onChange={(e) => onExchangeRateChange(e.target.value)}
                                />
                            </div>
                        </>
                    )}

                    {whtEnabled && (
                        <>
                            <div>
                                <Label className="text-xs">{t('WHT Rate (%)')}</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    className="mt-1"
                                    value={whtRate}
                                    onChange={(e) => onWhtRateChange(e.target.value)}
                                />
                            </div>
                            <div>
                                <Label className="text-xs">{t('WHT Amount (ETB)')}</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    className="mt-1"
                                    value={whtAmount}
                                    onChange={(e) => onWhtAmountChange(e.target.value)}
                                />
                            </div>
                        </>
                    )}
                </div>

                {(whtEnabled || isForeign || grossAllocationTotal > 0) && (
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-2 border-t text-xs text-muted-foreground">
                        <div>
                            {t('Invoices Settled')}: <span className="font-semibold text-foreground">{formatCurrency(grossAllocationTotal)}</span>
                        </div>
                        {whtEnabled && (
                            <div>
                                {mode === 'vendor' ? t('WHT Payable (2230)') : t('WHT Receivable (1510)')}:{' '}
                                <span className="font-semibold text-amber-700">{formatCurrency(numericWht)}</span>
                            </div>
                        )}
                        {isForeign && (
                            <div>
                                {t('Estimated Realized FX')}:{' '}
                                <span className={`font-semibold ${estimatedFxVariance >= 0 ? 'text-green-700' : 'text-red-700'}`}>
                                    {estimatedFxVariance >= 0 ? '+' : ''}
                                    {formatCurrency(estimatedFxVariance)}
                                </span>
                            </div>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
