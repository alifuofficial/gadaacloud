import { useMemo, useState } from 'react';
import { Head, router, usePage, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchInput } from "@/components/ui/search-input";
import NoRecordsFound from '@/components/no-records-found';
import { Boxes, Package, Power, PowerOff, Shield, DollarSign, X, Save, Settings, Sparkles } from "lucide-react";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "@/components/ui/dialog";

interface ModuleRow {
    id: number;
    module: string;
    name: string;
    image: string;
    monthly_price: number;
    yearly_price: number;
    is_enable: boolean;
    for_admin: boolean;
    package_name: string | null;
    priority: number;
}

interface ModulesListProps {
    addons: ModuleRow[];
    stats: {
        total: number;
        enabled: number;
        disabled: number;
        admin_only: number;
    };
    auth: any;
    [key: string]: unknown;
}

type FilterKey = 'all' | 'enabled' | 'disabled' | 'admin';

export default function List() {
    const { addons, stats } = usePage<ModulesListProps>().props;
    const { t } = useTranslation();

    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState<FilterKey>('all');

    // Price edit dialog state
    const [priceDialog, setPriceDialog] = useState<{ open: boolean; module: ModuleRow | null }>({ open: false, module: null });
    const [monthlyPrice, setMonthlyPrice] = useState('');
    const [yearlyPrice, setYearlyPrice] = useState('');
    const [isSaving, setIsSaving] = useState(false);

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        return addons.filter((m) => {
            const matchesSearch = !q || m.name.toLowerCase().includes(q) || m.module.toLowerCase().includes(q) || (m.package_name ?? '').toLowerCase().includes(q);
            const matchesFilter = filter === 'all' || (filter === 'enabled' && m.is_enable) || (filter === 'disabled' && !m.is_enable) || (filter === 'admin' && m.for_admin);
            return matchesSearch && matchesFilter;
        });
    }, [addons, search, filter]);

    const handleToggle = (moduleName: string) => {
        router.post(route('add-on.enable', moduleName), {}, { preserveState: true });
    };

    const openPriceDialog = (m: ModuleRow) => {
        setPriceDialog({ open: true, module: m });
        setMonthlyPrice(String(m.monthly_price));
        setYearlyPrice(String(m.yearly_price));
    };

    const closePriceDialog = () => {
        setPriceDialog({ open: false, module: null });
        setMonthlyPrice('');
        setYearlyPrice('');
    };

    const savePrice = () => {
        if (!priceDialog.module) return;
        setIsSaving(true);
        router.post(
            route('modules.update-price', priceDialog.module.module),
            { monthly_price: parseFloat(monthlyPrice) || 0, yearly_price: parseFloat(yearlyPrice) || 0 },
            {
                preserveScroll: true,
                onSuccess: () => { setIsSaving(false); closePriceDialog(); },
                onError: () => { setIsSaving(false); },
            }
        );
    };

    const FILTERS: { key: FilterKey; label: string; count: number }[] = [
        { key: 'all',      label: t('All'),        count: stats.total },
        { key: 'enabled',  label: t('Enabled'),    count: stats.enabled },
        { key: 'disabled', label: t('Disabled'),   count: stats.disabled },
        { key: 'admin',    label: t('Admin only'),  count: stats.admin_only },
    ];

    let copilotSetupUrl = '/settings/copilot/setup';
    try {
        if (typeof route === 'function') {
            copilotSetupUrl = route('settings.copilot.setup');
        }
    } catch (e) {}

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Add-on Manager') }]}
            pageTitle={t('Add-on Manager')}
        >
            <Head title={t('Add-on Manager')} />

            {/* Header & Stats Cards */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                <StatCard icon={<Boxes className="h-5 w-5 text-emerald-600" />} label={t('Total Modules')} value={stats.total} tone="primary" />
                <StatCard icon={<Package className="h-5 w-5 text-green-600" />} label={t('Enabled')} value={stats.enabled} tone="green" />
                <StatCard icon={<PowerOff className="h-5 w-5 text-gray-500" />} label={t('Disabled')} value={stats.disabled} tone="gray" />
                <StatCard icon={<Shield className="h-5 w-5 text-amber-600" />} label={t('Admin Only')} value={stats.admin_only} tone="amber" />
            </div>

            <Card className="border shadow-sm">
                <CardHeader className="p-4 sm:p-6 border-b flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    {/* Filters */}
                    <div className="flex items-center gap-1.5 flex-wrap">
                        {FILTERS.map((f) => (
                            <Button
                                key={f.key}
                                variant={filter === f.key ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => setFilter(f.key)}
                                className={`h-8 text-xs font-medium rounded-full ${
                                    filter === f.key
                                        ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm'
                                        : 'text-gray-600 hover:text-gray-900 border-gray-200'
                                }`}
                            >
                                {f.label}
                                <span className={`ml-1.5 px-1.5 py-0.2 text-[10px] rounded-full ${
                                    filter === f.key ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600'
                                }`}>
                                    {f.count}
                                </span>
                            </Button>
                        ))}
                    </div>

                    {/* Search */}
                    <div className="w-full sm:w-64">
                        <SearchInput
                            value={search}
                            onChange={(val) => setSearch(val)}
                            onSearch={() => {}}
                            placeholder={t('Search modules...')}
                        />
                    </div>
                </CardHeader>

                <CardContent className="p-0">
                    {filtered.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm text-left">
                                <thead>
                                    <tr className="bg-gray-50/80 border-b text-gray-500 font-semibold text-xs uppercase tracking-wider">
                                        <th className="p-3.5 pl-6">{t('Module')}</th>
                                        <th className="p-3.5">{t('Package')}</th>
                                        <th className="p-3.5 text-center">{t('Monthly')}</th>
                                        <th className="p-3.5 text-center">{t('Yearly')}</th>
                                        <th className="p-3.5 text-center">{t('Status')}</th>
                                        <th className="p-3.5 text-center hidden lg:table-cell">{t('Admin Only')}</th>
                                        <th className="p-3.5 text-center">{t('Priority')}</th>
                                        <th className="p-3.5 pr-6 text-right">{t('Actions')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {filtered.map((m) => (
                                        <tr key={m.id} className="hover:bg-gray-50/60 transition-colors">
                                            <td className="p-3 pl-6 font-medium text-gray-900">
                                                <div className="flex items-center gap-3">
                                                    <img
                                                        src={m.image}
                                                        alt={m.name}
                                                        className="w-8 h-8 rounded-lg object-cover border bg-white p-0.5 shadow-2xs"
                                                        onError={(e) => {
                                                            (e.target as HTMLElement).style.display = 'none';
                                                        }}
                                                    />
                                                    <div>
                                                        <div className="font-semibold text-gray-900 flex items-center gap-1.5">
                                                            {m.name}
                                                            {m.module === 'GadaaCloudCopilot' && (
                                                                <span className="inline-flex items-center gap-1 text-[9px] font-bold bg-purple-100 text-purple-700 px-1.5 py-0.2 rounded-full border border-purple-200">
                                                                    <Sparkles className="w-2.5 h-2.5 text-purple-600" />
                                                                    AI Engine
                                                                </span>
                                                            )}
                                                        </div>
                                                        <div className="text-xs text-gray-400 font-mono">{m.module}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="p-3 text-gray-500 font-mono text-xs">
                                                {m.package_name ? (
                                                    <span className="px-2 py-0.5 bg-gray-100 rounded text-gray-600">{m.package_name}</span>
                                                ) : (
                                                    <span className="text-gray-300">—</span>
                                                )}
                                            </td>
                                            <td className="p-3 text-center text-gray-600">
                                                {m.monthly_price === 0 ? (
                                                    <span className="text-xs font-semibold text-emerald-600">{t('Free')}</span>
                                                ) : (
                                                    <span className="font-mono text-xs">${m.monthly_price.toFixed(2)}</span>
                                                )}
                                            </td>
                                            <td className="p-3 text-center text-gray-600">
                                                {m.yearly_price === 0 ? (
                                                    <span className="text-xs font-semibold text-emerald-600">{t('Free')}</span>
                                                ) : (
                                                    <span className="font-mono text-xs">${m.yearly_price.toFixed(2)}</span>
                                                )}
                                            </td>
                                            <td className="p-3 text-center">
                                                {m.is_enable ? (
                                                    <span className="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">
                                                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                        {t('Active')}
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 bg-gray-100 px-2.5 py-0.5 rounded-full border border-gray-200">
                                                        <span className="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                                        {t('Disabled')}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="p-3 text-center hidden lg:table-cell">
                                                {m.for_admin ? <Shield className="h-4 w-4 inline-block text-amber-500" /> : <span className="text-gray-300 text-xs">—</span>}
                                            </td>
                                            <td className="p-3 text-center text-gray-600 whitespace-nowrap">{m.priority}</td>
                                            <td className="p-3 pr-6 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    {/* AI Copilot Configuration Button for Superadmin */}
                                                    {(m.module === 'GadaaCloudCopilot' || m.module === 'GadaaCloudCopilot') && (
                                                        <TooltipProvider>
                                                            <Tooltip delayDuration={0}>
                                                                <TooltipTrigger asChild>
                                                                    <Link
                                                                        href={copilotSetupUrl}
                                                                        className="inline-flex items-center justify-center h-8 px-2.5 rounded-md bg-purple-600 hover:bg-purple-700 text-white font-medium text-xs gap-1 shadow-sm transition-colors"
                                                                    >
                                                                        <Settings className="h-3.5 w-3.5" />
                                                                        <span>{t('AI Config')}</span>
                                                                    </Link>
                                                                </TooltipTrigger>
                                                                <TooltipContent><p>{t('Configure Global AI API Key & Token Pricing')}</p></TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                    )}

                                                    {/* Set Price button */}
                                                    <TooltipProvider>
                                                        <Tooltip delayDuration={0}>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    variant="outline"
                                                                    size="sm"
                                                                    onClick={() => openPriceDialog(m)}
                                                                    className="h-8 px-2 bg-blue-50 hover:bg-blue-100 border-blue-200 text-blue-600 hover:text-blue-700"
                                                                >
                                                                    <DollarSign className="h-3.5 w-3.5" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent><p>{t('Set Pricing')}</p></TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>

                                                    {/* Enable / Disable button */}
                                                    <TooltipProvider>
                                                        <Tooltip delayDuration={0}>
                                                            <TooltipTrigger asChild>
                                                                <Button variant="outline" size="sm" onClick={() => handleToggle(m.module)} className={`h-8 px-2 ${m.is_enable ? 'bg-red-50 hover:bg-red-100 border-red-200 text-red-600 hover:text-red-700' : 'bg-green-50 hover:bg-green-100 border-green-200 text-green-700 hover:text-green-800'}`}>
                                                                    {m.is_enable ? <PowerOff className="h-3.5 w-3.5" /> : <Power className="h-3.5 w-3.5" />}
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent><p>{m.is_enable ? t('Disable Module') : t('Enable Module')}</p></TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <NoRecordsFound
                            icon={Boxes}
                            title={t('No modules found')}
                            description={search || filter !== 'all' ? t('No modules match your search criteria.') : t('No modules are installed on this system.')}
                            hasFilters={!!search || filter !== 'all'}
                            onClearFilters={() => { setSearch(''); setFilter('all'); }}
                        />
                    )}
                </CardContent>
            </Card>

            {/* ─── Price Edit Dialog ─── */}
            <Dialog open={priceDialog.open} onOpenChange={(o) => !o && closePriceDialog()}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <DollarSign className="h-5 w-5 text-blue-600" />
                            {t('Set Module Pricing')}
                        </DialogTitle>
                        <DialogDescription>
                            {priceDialog.module && (
                                <span className="font-semibold text-gray-700">{priceDialog.module.name}</span>
                            )}
                            {' — '}{t('Set 0 to make it free')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-5 pt-2">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="monthly_price" className="flex items-center gap-1.5">
                                    <span className="text-sm font-medium">{t('Monthly Price')}</span>
                                </Label>
                                <div className="relative">
                                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                    <Input
                                        id="monthly_price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={monthlyPrice}
                                        onChange={(e) => setMonthlyPrice(e.target.value)}
                                        className="pl-7"
                                        placeholder="0.00"
                                    />
                                </div>
                                {parseFloat(monthlyPrice) === 0 && (
                                    <p className="text-xs text-emerald-600 font-medium">✓ {t('Free')}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="yearly_price" className="flex items-center gap-1.5">
                                    <span className="text-sm font-medium">{t('Yearly Price')}</span>
                                </Label>
                                <div className="relative">
                                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                    <Input
                                        id="yearly_price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={yearlyPrice}
                                        onChange={(e) => setYearlyPrice(e.target.value)}
                                        className="pl-7"
                                        placeholder="0.00"
                                    />
                                </div>
                                {parseFloat(yearlyPrice) === 0 && (
                                    <p className="text-xs text-emerald-600 font-medium">✓ {t('Free')}</p>
                                )}
                                {parseFloat(yearlyPrice) > 0 && parseFloat(monthlyPrice) > 0 && parseFloat(yearlyPrice) < parseFloat(monthlyPrice) * 12 && (
                                    <p className="text-xs text-amber-600 font-medium">
                                        💡 {t('Saving')} ${((parseFloat(monthlyPrice) * 12) - parseFloat(yearlyPrice)).toFixed(2)}/yr vs monthly
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="flex justify-end gap-2 pt-1">
                            <Button variant="outline" onClick={closePriceDialog} className="gap-1.5">
                                <X className="h-4 w-4" />
                                {t('Cancel')}
                            </Button>
                            <Button onClick={savePrice} disabled={isSaving} className="gap-1.5">
                                <Save className="h-4 w-4" />
                                {isSaving ? t('Saving...') : t('Save Pricing')}
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

        </AuthenticatedLayout>
    );
}

function StatCard({ icon, label, value, tone }: { icon: React.ReactNode; label: string; value: number; tone: 'primary' | 'green' | 'gray' | 'amber' }) {
    const TONES: Record<string, string> = {
        primary: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        green:   'bg-green-50 text-green-700 border-green-200',
        gray:    'bg-gray-100 text-gray-600 border-gray-200',
        amber:   'bg-amber-50 text-amber-700 border-amber-200',
    };
    return (
        <Card className={`border ${TONES[tone] || TONES['gray']}`}>
            <CardContent className="p-4 flex items-center gap-3">
                <span className="w-10 h-10 rounded-xl bg-white/70 flex items-center justify-center shadow-sm">{icon}</span>
                <div>
                    <div className="text-2xl font-black leading-none">{value}</div>
                    <div className="text-xs opacity-80 mt-1">{label}</div>
                </div>
            </CardContent>
        </Card>
    );
}