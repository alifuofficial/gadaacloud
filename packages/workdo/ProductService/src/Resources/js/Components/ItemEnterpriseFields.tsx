import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/ui/input-error';
import { AlertTriangle } from 'lucide-react';
import { AccountOption, ItemFormData, Unit } from '../Pages/Items/types';

interface ComplianceFieldsProps {
    data: ItemFormData;
    setData: (key: keyof ItemFormData, value: any) => void;
    errors: Partial<Record<keyof ItemFormData, string>>;
}

/**
 * Shared Barcode, Harmonized System (HS) Customs Code, and MoR EIMS Tax Classification fields.
 */
export function ItemComplianceFields({ data, setData, errors }: ComplianceFieldsProps) {
    const { t } = useTranslation();

    return (
        <div className="border-t pt-5 space-y-4">
            <h3 className="text-sm font-semibold text-muted-foreground uppercase tracking-wide">
                {t('Barcode, Customs & EIMS Tax Compliance')}
            </h3>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <Label htmlFor="barcode">{t('Barcode / EAN-13')}</Label>
                    <Input
                        id="barcode"
                        value={data.barcode || ''}
                        onChange={(e) => setData('barcode', e.target.value)}
                        placeholder="e.g. 6291041500213"
                    />
                    <InputError message={errors.barcode} />
                </div>
                <div>
                    <Label htmlFor="hs_code">{t('HS Tariff Code')}</Label>
                    <Input
                        id="hs_code"
                        value={data.hs_code || ''}
                        onChange={(e) => setData('hs_code', e.target.value)}
                        placeholder="e.g. 0901.11.00"
                    />
                    <InputError message={errors.hs_code} />
                </div>
                <div>
                    <Label htmlFor="tax_classification">{t('MoR EIMS Tax Code')}</Label>
                    <Select
                        value={data.tax_classification || 'VAT15'}
                        onValueChange={(value) => setData('tax_classification', value)}
                    >
                        <SelectTrigger id="tax_classification">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="VAT15">{t('VAT15 (Standard 15%)')}</SelectItem>
                            <SelectItem value="VAT0">{t('VAT0 (Zero-Rated / Export)')}</SelectItem>
                            <SelectItem value="EXEMPT">{t('EXEMPT (Tax Exempt)')}</SelectItem>
                            <SelectItem value="EXCISE">{t('EXCISE (Excise + VAT)')}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.tax_classification} />
                </div>
                <div>
                    <Label htmlFor="excise_rate">{t('Excise Rate (%)')}</Label>
                    <Input
                        id="excise_rate"
                        type="number"
                        step="0.01"
                        min="0"
                        disabled={(data.tax_classification || 'VAT15') !== 'EXCISE'}
                        value={data.excise_rate?.toString() || '0'}
                        onChange={(e) => setData('excise_rate', e.target.value)}
                    />
                    <InputError message={errors.excise_rate} />
                </div>
            </div>
            <div className="flex items-center gap-3 pt-1">
                <Switch
                    id="is_tax_inclusive"
                    checked={Boolean(data.is_tax_inclusive)}
                    onCheckedChange={(checked) => setData('is_tax_inclusive', checked)}
                />
                <Label htmlFor="is_tax_inclusive" className="cursor-pointer font-normal text-sm">
                    {t('Sale price includes tax (Tax-Inclusive POS / Retail pricing)')}
                </Label>
            </div>
        </div>
    );
}

interface PricingGlFieldsProps {
    data: ItemFormData;
    setData: (key: keyof ItemFormData, value: any) => void;
    errors: Partial<Record<keyof ItemFormData, string>>;
    units: Unit[];
    hasAccountModule?: boolean;
    revenueAccounts?: AccountOption[];
    expenseAccounts?: AccountOption[];
    assetAccounts?: AccountOption[];
}

/**
 * Shared Enterprise Pricing tiers, Purchase UoM conversion, Reorder Alert, and GL Account mappings.
 */
export function ItemPricingAndGlFields({
    data,
    setData,
    errors,
    units = [],
    hasAccountModule = false,
    revenueAccounts = [],
    expenseAccounts = [],
    assetAccounts = [],
}: PricingGlFieldsProps) {
    const { t } = useTranslation();

    const salePriceNum = parseFloat(data.sale_price || '0');
    const purchasePriceNum = parseFloat(data.purchase_price || '0');
    const isNegativeMargin =
        !Number.isNaN(salePriceNum) &&
        !Number.isNaN(purchasePriceNum) &&
        salePriceNum > 0 &&
        purchasePriceNum > salePriceNum;

    return (
        <div className="space-y-5">
            {isNegativeMargin && (
                <div className="flex items-center gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    <AlertTriangle className="h-4 w-4 shrink-0 text-amber-600" />
                    <span>
                        {t('Margin Warning: Sale price is currently lower than purchase cost.')}
                    </span>
                </div>
            )}

            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <Label htmlFor="min_sale_price">{t('Min Allowed Sale Price')}</Label>
                    <Input
                        id="min_sale_price"
                        type="number"
                        step="0.01"
                        min="0"
                        value={data.min_sale_price?.toString() || ''}
                        onChange={(e) => setData('min_sale_price', e.target.value)}
                        placeholder={t('Optional floor price')}
                    />
                    <InputError message={errors.min_sale_price} />
                </div>
                <div>
                    <Label htmlFor="wholesale_price">{t('Wholesale / Bulk Price')}</Label>
                    <Input
                        id="wholesale_price"
                        type="number"
                        step="0.01"
                        min="0"
                        value={data.wholesale_price?.toString() || ''}
                        onChange={(e) => setData('wholesale_price', e.target.value)}
                        placeholder={t('Optional bulk price')}
                    />
                    <InputError message={errors.wholesale_price} />
                </div>
                <div>
                    <Label htmlFor="wholesale_min_qty">{t('Wholesale Min Qty')}</Label>
                    <Input
                        id="wholesale_min_qty"
                        type="number"
                        step="0.01"
                        min="0"
                        value={data.wholesale_min_qty?.toString() || ''}
                        onChange={(e) => setData('wholesale_min_qty', e.target.value)}
                        placeholder="e.g. 10"
                    />
                    <InputError message={errors.wholesale_min_qty} />
                </div>
            </div>

            {data.type !== 'service' && (
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3 border-t pt-4">
                    <div>
                        <Label htmlFor="purchase_unit_id">{t('Purchase Unit (Optional)')}</Label>
                        <Select
                            value={data.purchase_unit_id?.toString() || 'none'}
                            onValueChange={(value) => setData('purchase_unit_id', value === 'none' ? '' : value)}
                        >
                            <SelectTrigger id="purchase_unit_id">
                                <SelectValue placeholder={t('Same as Sales Unit')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">{t('Same as Sales Unit')}</SelectItem>
                                {(units || []).map((u) => (
                                    <SelectItem key={u.id} value={u.id.toString()}>
                                        {u.unit_name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.purchase_unit_id} />
                    </div>
                    <div>
                        <Label htmlFor="unit_conversion_factor">{t('Conversion Factor (to Base Unit)')}</Label>
                        <Input
                            id="unit_conversion_factor"
                            type="number"
                            step="0.0001"
                            min="0.0001"
                            value={data.unit_conversion_factor?.toString() || '1'}
                            onChange={(e) => setData('unit_conversion_factor', e.target.value)}
                            placeholder="1"
                        />
                        <p className="text-[11px] text-muted-foreground mt-1">
                            {t('e.g. 1 Carton = 24 Pcs -> enter 24')}
                        </p>
                        <InputError message={errors.unit_conversion_factor} />
                    </div>
                    <div>
                        <Label htmlFor="reorder_level">{t('Low-Stock Reorder Level')}</Label>
                        <Input
                            id="reorder_level"
                            type="number"
                            step="0.01"
                            min="0"
                            value={data.reorder_level?.toString() || '0'}
                            onChange={(e) => setData('reorder_level', e.target.value)}
                            placeholder="0"
                        />
                        <InputError message={errors.reorder_level} />
                    </div>
                </div>
            )}

            {hasAccountModule && (
                <div className="border-t pt-4 space-y-3">
                    <h3 className="text-sm font-semibold text-muted-foreground uppercase tracking-wide">
                        {t('Accounting GL Overrides (Optional)')}
                    </h3>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <Label htmlFor="sale_chartaccount_id">{t('Sales Revenue Account')}</Label>
                            <Select
                                value={data.sale_chartaccount_id?.toString() || 'none'}
                                onValueChange={(v) => setData('sale_chartaccount_id', v === 'none' ? '' : v)}
                            >
                                <SelectTrigger id="sale_chartaccount_id">
                                    <SelectValue placeholder={t('Default (4100 Sales Revenue)')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">{t('Default (4100 Sales Revenue)')}</SelectItem>
                                    {(revenueAccounts || []).map((acc) => (
                                        <SelectItem key={acc.id} value={acc.id.toString()}>
                                            {acc.account_code} - {acc.account_name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="expense_chartaccount_id">{t('COGS / Expense Account')}</Label>
                            <Select
                                value={data.expense_chartaccount_id?.toString() || 'none'}
                                onValueChange={(v) => setData('expense_chartaccount_id', v === 'none' ? '' : v)}
                            >
                                <SelectTrigger id="expense_chartaccount_id">
                                    <SelectValue placeholder={t('Default (5100 COGS)')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">{t('Default (5100 COGS)')}</SelectItem>
                                    {(expenseAccounts || []).map((acc) => (
                                        <SelectItem key={acc.id} value={acc.id.toString()}>
                                            {acc.account_code} - {acc.account_name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        {data.type !== 'service' && (
                            <div>
                                <Label htmlFor="inventory_chartaccount_id">{t('Inventory Asset Account')}</Label>
                                <Select
                                    value={data.inventory_chartaccount_id?.toString() || 'none'}
                                    onValueChange={(v) => setData('inventory_chartaccount_id', v === 'none' ? '' : v)}
                                >
                                    <SelectTrigger id="inventory_chartaccount_id">
                                        <SelectValue placeholder={t('Default (1200 Inventory)')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">{t('Default (1200 Inventory)')}</SelectItem>
                                        {(assetAccounts || []).map((acc) => (
                                            <SelectItem key={acc.id} value={acc.id.toString()}>
                                                {acc.account_code} - {acc.account_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
