import { useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import { MultiSelectEnhanced } from '@/components/ui/multi-select-enhanced';
import { DatePicker } from '@/components/ui/date-picker';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useFormFields } from '@/hooks/useFormFields';

interface CreateProps {
    onSuccess: () => void;
    users?: Array<{
        id: number;
        name: string;
    }>;
}

export default function Create({ onSuccess, users = [] }: CreateProps) {
    const { t } = useTranslation();
    const pageProps = usePage()?.props as any;
    const defaultScope = pageProps?.companyDefaults?.default_project_scope || 'client';
    const defaultInternalCategory = pageProps?.companyDefaults?.default_internal_category || 'opex';
    const defaultCurrency = pageProps?.companyDefaults?.default_currency || 'ETB';

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        project_scope: defaultScope, // 'client' or 'internal'
        internal_category: defaultInternalCategory,
        project_sponsor_id: '' as string | number,
        business_case: '',
        target_roi_savings: '',
        project_type: 'general',
        currency_code: defaultCurrency,
        user_ids: [] as number[],
        description: '',
        budget: 0,
        start_date: '',
        end_date: '',
    });

    // AI hooks for name and description fields
    const nameAI = useFormFields('aiField', data, setData, errors, 'create', 'name', 'Name', 'taskly', 'project');
    const descriptionAI = useFormFields('aiField', data, setData, errors, 'create', 'description', 'Description', 'taskly', 'project');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('project.store'), {
            onSuccess: () => {
                onSuccess();
            }
        });
    };

    return (
        <DialogContent className="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{t('Create Project')}</DialogTitle>
            </DialogHeader>

            <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                    <div className="flex gap-2 items-end">
                        <div className="flex-1">
                            <Label htmlFor="name">{t('Name')}</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder={t('Enter project name')}
                                className={errors.name ? 'border-red-500' : ''}
                                required
                            />
                            {errors.name && <p className="text-sm text-red-500 mt-1">{errors.name}</p>}
                        </div>
                        {nameAI.map(field => <div key={field.id}>{field.component}</div>)}
                    </div>
                </div>

                {/* Project Scope (Client vs In-House) */}
                <div>
                    <Label required>{t('Project Nature / Scope')}</Label>
                    <div className="grid grid-cols-2 gap-2 mt-1">
                        <button
                            type="button"
                            onClick={() => setData('project_scope', 'client')}
                            className={`p-2.5 rounded-xl text-xs font-semibold border flex flex-col items-center gap-1 transition-all ${
                                data.project_scope === 'client'
                                    ? 'bg-blue-50 border-blue-600 text-blue-700 shadow-sm dark:bg-blue-950 dark:border-blue-400 dark:text-blue-200'
                                    : 'border-slate-200 hover:bg-slate-50 text-slate-600 dark:border-slate-800 dark:hover:bg-slate-900'
                            }`}
                        >
                            <span>🏢 {t('Client-Facing Project')}</span>
                            <span className="text-[10px] font-normal text-slate-500">{t('External customer, billable delivery')}</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setData('project_scope', 'internal')}
                            className={`p-2.5 rounded-xl text-xs font-semibold border flex flex-col items-center gap-1 transition-all ${
                                data.project_scope === 'internal'
                                    ? 'bg-purple-50 border-purple-600 text-purple-700 shadow-sm dark:bg-purple-950 dark:border-purple-400 dark:text-purple-200'
                                    : 'border-slate-200 hover:bg-slate-50 text-slate-600 dark:border-slate-800 dark:hover:bg-slate-900'
                            }`}
                        >
                            <span>🏭 {t('In-House / Internal Project')}</span>
                            <span className="text-[10px] font-normal text-slate-500">{t('CapEx/OpEx, company initiative')}</span>
                        </button>
                    </div>
                    {errors.project_scope && <p className="text-sm text-red-500 mt-1">{errors.project_scope}</p>}
                </div>

                {/* In-House Specific Fields */}
                {data.project_scope === 'internal' && (
                    <div className="p-3.5 bg-purple-50/50 dark:bg-purple-950/20 border border-purple-200 dark:border-purple-800 rounded-2xl space-y-3">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label required className="text-xs">{t('Internal Accounting Class')}</Label>
                                <Select
                                    value={data.internal_category}
                                    onValueChange={(val) => setData('internal_category', val)}
                                >
                                    <SelectTrigger className="w-full text-xs">
                                        <SelectValue placeholder={t('Select Category')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="capex">{t('CapEx - Capital Asset Investment')}</SelectItem>
                                        <SelectItem value="opex">{t('OpEx - Operational Improvement')}</SelectItem>
                                        <SelectItem value="rd">{t('R&D - New Product / Prototyping')}</SelectItem>
                                        <SelectItem value="maintenance">{t('Infrastructure / Overhaul')}</SelectItem>
                                        <SelectItem value="strategic">{t('Strategic PMO Initiative')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label className="text-xs">{t('Executive Project Sponsor')}</Label>
                                <Select
                                    value={data.project_sponsor_id ? data.project_sponsor_id.toString() : 'none'}
                                    onValueChange={(val) => setData('project_sponsor_id', val === 'none' ? '' : parseInt(val))}
                                >
                                    <SelectTrigger className="w-full text-xs">
                                        <SelectValue placeholder={t('Select Sponsor')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">{t('None / PM Managed')}</SelectItem>
                                        {(users || []).map(u => (
                                            <SelectItem key={u.id} value={u.id.toString()}>{u.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div>
                            <Label className="text-xs">{t('Target Business Outcome / ROI')}</Label>
                            <Input
                                value={data.target_roi_savings}
                                onChange={(e) => setData('target_roi_savings', e.target.value)}
                                placeholder={t('e.g. Save $50,000/yr or reduce delivery time by 30%')}
                                className="text-xs"
                            />
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <Label required>{t('Industry / Workspace Type')}</Label>
                        <Select
                            value={data.project_type}
                            onValueChange={(val) => setData('project_type', val)}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder={t('Select Type')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="general">{t('General / IT / Agency')}</SelectItem>
                                <SelectItem value="construction">{t('Construction & Real Estate (BoQ)')}</SelectItem>
                                <SelectItem value="import_export">{t('Import / Export Logistics (LC & Port)')}</SelectItem>
                                <SelectItem value="manufacturing">{t('Manufacturing & Assembly')}</SelectItem>
                                <SelectItem value="consulting">{t('Consulting & Professional Services')}</SelectItem>
                            </SelectContent>
                        </Select>
                        {errors.project_type && <p className="text-sm text-red-500 mt-1">{errors.project_type}</p>}
                    </div>

                    <div>
                        <Label required>{t('Currency')}</Label>
                        <Select
                            value={data.currency_code}
                            onValueChange={(val) => setData('currency_code', val)}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder={t('Select Currency')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="ETB">ETB (Ethiopian Birr)</SelectItem>
                                <SelectItem value="USD">USD (US Dollar)</SelectItem>
                                <SelectItem value="EUR">EUR (Euro)</SelectItem>
                                <SelectItem value="GBP">GBP (British Pound)</SelectItem>
                                <SelectItem value="AED">AED (UAE Dirham)</SelectItem>
                                <SelectItem value="CNY">CNY (Chinese Yuan)</SelectItem>
                            </SelectContent>
                        </Select>
                        {errors.currency_code && <p className="text-sm text-red-500 mt-1">{errors.currency_code}</p>}
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <Label required>{t('Start Date')}</Label>
                        <DatePicker
                            value={data.start_date}
                            onChange={(value) => setData('start_date', value)}
                            placeholder={t('Select start date')}
                        />
                        {errors.start_date && <p className="text-sm text-red-500 mt-1">{errors.start_date}</p>}
                    </div>
                    <div>
                        <Label required>{t('End Date')}</Label>
                        <DatePicker
                            value={data.end_date}
                            onChange={(value) => setData('end_date', value)}
                            placeholder={t('Select end date')}
                        />
                        {errors.end_date && <p className="text-sm text-red-500 mt-1">{errors.end_date}</p>}
                    </div>
                </div>

                <div>
                    <Label required>{t('Users')}</Label>
                    <MultiSelectEnhanced
                        options={(users || []).map(user => ({ value: user.id.toString(), label: user.name }))}
                        value={data.user_ids.map(id => id.toString())}
                        onValueChange={(value) => setData('user_ids', value.map(v => parseInt(v)))}
                        placeholder={t('Select users')}
                        searchable={true}
                    />
                    {errors.user_ids && <p className="text-sm text-red-500 mt-1">{errors.user_ids}</p>}
                </div>

                <div>
                    <CurrencyInput
                        label={t('Budget')}
                        value={data.budget.toString()}
                        onChange={(value) => setData('budget', parseFloat(value) || 0)}
                        error={errors.budget}
                        required
                    />
                </div>

                <div>
                    <div className="flex items-center justify-between mb-2">
                        <Label htmlFor="description">{t('Description')}</Label>
                        <div className="flex gap-2">
                            {descriptionAI.map(field => <div key={field.id}>{field.component}</div>)}
                        </div>
                    </div>
                    <RichTextEditor
                        id="description"
                        variant="full"
                        content={data.description}
                        onChange={(value) => setData('description', value)}
                        placeholder={t('Enter project description')}
                        className={errors.description ? 'border-red-500' : ''}
                    />
                    {errors.description && <p className="text-sm text-red-500 mt-1">{errors.description}</p>}
                </div>

                <div className="flex justify-end gap-2 pt-4">
                    <Button type="button" variant="outline" onClick={onSuccess}>
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? t('Creating...') : t('Create')}
                    </Button>
                </div>
            </form>
        </DialogContent>
    );
}
