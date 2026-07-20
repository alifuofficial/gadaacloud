import { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchInput } from "@/components/ui/search-input";
import NoRecordsFound from '@/components/no-records-found';
import { Boxes, Package, Power, PowerOff, Shield, CheckCircle2, XCircle, DollarSign, X, Save } from "lucide-react";
import { formatAdminCurrency } from '@/utils/helpers';
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
    const pageProps = usePage().props;

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

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Modules') }]} pageTitle={t('Modules')}>
            <Head title={t('Modules')} />

            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <StatCard icon={<Boxes className="h-5 w-5" />} label={t('Total modules')} value={stats.total} tone="primary" />
                <StatCard icon={<CheckCircle2 className="h-5 w-5" />} label={t('Enabled')} value={stats.enabled} tone="green" />
                <StatCard icon={<XCircle className="h-5 w-5" />} label={t('Disabled')} value={stats.disabled} tone="gray" />
                <StatCard icon={<Shield className="h-5 w-5" />} label={t('Admin-only')} value={stats.admin_only} tone="amber" />
            </div>

            <Card>
                <CardHeader className="space-y-3">
                    <SearchInput value={search} onChange={setSearch} onSearch={() => {}} placeholder={t('Search modules by name, package or key...')} className="w-full" />
                    <div className="flex flex-wrap items-center gap-2 pt-1">
                        {FILTERS.map((f) => {
                            const active = filter === f.key;
                            return (
                                <button
                                    key={f.key}
                                    onClick={() => setFilter(f.key)}
                                    className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-all duration-200 ${active ? 'text-white border-transparent shadow-sm' : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300 hover:text-gray-900'}`}
                                    style={active ? { background: 'hsl(var(--primary))' } : undefined}
                                >
                                    {f.label}
                                    <span className={`px-1.5 py-0.5 rounded-full text-[10px] font-bold ${active ? 'bg-white/20' : 'bg-gray-100 text-gray-600'}`}>{f.count}</span>
                                </button>
                            );
                        })}
                    </div>
                </CardHeader>

                <CardContent>
                    {filtered.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm min-w-[700px]">
                                <thead>
                                    <tr className="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide bg-gray-50/60">
                                        <th className="p-3">{t('Module')}</th>
                                        <th className="p-3 hidden md:table-cell">{t('Package')}</th>
                                        <th className="p-3 text-right">{t('Monthly')}</th>
                                        <th className="p-3 text-right hidden md:table-cell">{t('Yearly')}</th>
                                        <th className="p-3 text-center">{t('Status')}</th>
                                        <th className="p-3 text-center hidden lg:table-cell">{t('Admin only')}</th>
                                        <th className="p-3 text-center">{t('Priority')}</th>
                                        <th className="p-3 text-right">{t('Actions')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filtered.map((m) => (
                                        <tr key={m.id} className="border-t border-gray-100 hover:bg-gray-50/40 transition-colors">
                                            <td className="p-3">
                                                <div className="flex items-center gap-3">
                                                    <div className="relative flex-shrink-0">
                                                        <img src={m.image} alt={m.name} className="h-9 w-9 object-contain rounded-lg border border-gray-100 bg-white" onError={(e) => { const t = e.target as HTMLImageElement; t.style.display = 'none'; t.nextElementSibling?.classList.remove('hidden'); }} />
                                                        <Package className="h-9 w-9 text-gray-400 hidden p-1.5" />
                                                    </div>
                                                    <div className="min-w-0">
                                                        <div className="font-semibold text-gray-900 truncate">{m.name}</div>
                                                        <div className="text-[11px] text-gray-400 truncate font-mono">{m.module}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="p-3 hidden md:table-cell">
                                                {m.package_name ? <span className="px-2 py-0.5 rounded-md bg-gray-100 text-gray-700 text-xs font-medium">{m.package_name}</span> : <span className="text-gray-300 text-xs">—</span>}
                                            </td>
                                            <td className="p-3 text-right font-medium text-gray-800 whitespace-nowrap">
                                                {m.monthly_price > 0 ? formatAdminCurrency(m.monthly_price, pageProps) : <span className="text-gray-300 text-xs">Free</span>}
                                            </td>
                                            <td className="p-3 text-right font-medium text-gray-800 whitespace-nowrap hidden md:table-cell">
                                                {m.yearly_price > 0 ? formatAdminCurrency(m.yearly_price, pageProps) : <span className="text-gray-300 text-xs">Free</span>}
                                            </td>
                                            <td className="p-3 text-center">
                                                <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold ${m.is_enable ? 'bg-green-500 text-white' : 'bg-gray-400 text-white'}`}>
                                                    <span className="w-1.5 h-1.5 rounded-full bg-white/80" />
                                                    {m.is_enable ? t('Active') : t('Inactive')}
                                                </span>
                                            </td>
                                            <td className="p-3 text-center hidden lg:table-cell">
                                                {m.for_admin ? <Shield className="h-4 w-4 inline-block text-amber-500" /> : <span className="text-gray-300 text-xs">—</span>}
                                            </td>
                                            <td className="p-3 text-center text-gray-600 whitespace-nowrap">{m.priority}</td>
                                            <td className="p-3 text-right">
                                                <div className="flex items-center justify-end gap-1.5">
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