import { Head, router, usePage } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import {
    Check, X, Users, HardDrive, Sparkles, ArrowRight,
    ShieldCheck, CloudCog, LockKeyhole, ServerCog, DatabaseBackup,
    FileLock2, ChevronDown, CheckCircle2, Layers
} from 'lucide-react';
import { getAdminSetting, getImagePath, formatAdminCurrency } from '@/utils/helpers';
import CookieConsent from "@/components/cookie-consent";
import SiteHeader from './components/SiteHeader';
import SiteFooter from './components/SiteFooter';
import {
    CloudTopologyPattern,
    SecurityHexPattern,
    CircuitDotPattern,
    CloudSecurityWatermark
} from './components/CloudSecurityPatterns';

interface Plan {
    id: number;
    name: string;
    description?: string;
    package_price_monthly: number;
    package_price_yearly: number;
    number_of_users: number;
    storage_limit: number;
    modules: string[];
    free_plan: boolean;
    trial: boolean;
    trial_days: number;
    orders_count?: number;
}

interface Module {
    module: string;
    alias: string;
    image?: string;
    monthly_price?: number;
    yearly_price?: number;
}

interface PricingProps {
    plans?: Plan[];
    activeModules?: Module[];
    settings?: any;
}

const BRAND_COLORS = {
    primary: '#00A76F',
    secondary: '#059669',
    accent: '#34D399',
    dark: '#031B13',
};

export default function Pricing(props: PricingProps) {
    const { t } = useTranslation();
    const pageProps = usePage().props as any;
    const { adminAllSetting, auth } = pageProps;

    const favicon = getAdminSetting('favicon');
    const faviconUrl = favicon ? getImagePath(favicon) : null;

    const plans = props.plans || [];
    const activeModules = props.activeModules || [];
    const settings = {
        ...(props.settings || {}),
        is_authenticated: !!(auth?.user?.id !== undefined && auth?.user?.id !== null),
    };

    const colors = {
        ...BRAND_COLORS,
        ...(settings?.config_sections?.colors || {}),
        primary: '#00A76F',
        secondary: '#059669',
        accent: '#34D399',
    };

    const pricingSettings = settings?.config_sections?.sections?.pricing || {};
    const showPrePackage = pricingSettings.show_pre_package !== false;
    const showToggle = pricingSettings.show_monthly_yearly_toggle !== false;

    const [priceType, setPriceType] = useState<'monthly' | 'yearly'>(
        pricingSettings.default_price_type || 'monthly'
    );
    const [showCompare, setShowCompare] = useState(false);
    const [openFaq, setOpenFaq] = useState<number>(0);

    const isAuthed = !!settings?.is_authenticated;
    const grad = `linear-gradient(135deg, ${colors.primary}, ${colors.secondary})`;

    const mostPopularPlanId = useMemo(() => {
        if (plans.length <= 1) return null;
        return plans.reduce((prev, current) =>
            (current.orders_count || 0) > (prev.orders_count || 0) ? current : prev
        ).id;
    }, [plans]);

    const yearlySavingsMonths = useMemo(() => {
        const paid = plans.find((p) => !p.free_plan && p.package_price_monthly > 0 && p.package_price_yearly > 0);
        if (!paid) return 0;
        const monthlyTotal = paid.package_price_monthly * 12;
        if (paid.package_price_yearly >= monthlyTotal) return 0;
        return Math.round((monthlyTotal - paid.package_price_yearly) / paid.package_price_monthly);
    }, [plans]);

    const handleCTA = () => {
        if (isAuthed) {
            router.visit(route('dashboard'));
        } else {
            router.visit(route('register'));
        }
    };

    const CLOUD_GUARANTEES = [
        { icon: CloudCog, label: '99.9% Cloud SLA', sub: 'Redundant regional zones' },
        { icon: LockKeyhole, label: 'AES-256 & TLS 1.3', sub: 'End-to-end vault security' },
        { icon: ShieldCheck, label: 'MoR EIMS 1142 Ready', sub: 'Fiscal QR & IRN signing' },
        { icon: DatabaseBackup, label: 'Daily Geo-Backups', sub: 'Zero-loss recovery' },
    ];

    const PRICING_FAQS = [
        {
            q: t('Are prices in Ethiopian Birr (ETB)?'),
            a: t('Yes — every subscription plan is billed in Ethiopian Birr (ETB) with VAT transparency and MoR EIMS compliant electronic tax receipts, eliminating FX conversion friction for your finance team.')
        },
        {
            q: t('Which Ethiopian payment methods are supported?'),
            a: t('We natively support Telebirr, CBE Birr, direct Commercial Bank of Ethiopia (CBE) / Awash / Dashen bank transfers with automated reference matching, and manual bank slip verification.')
        },
        {
            q: t('Can I try GadaaCloud before paying?'),
            a: t('Yes — paid plans include a risk-free trial period (14 to 30 days depending on tier) with no credit card required upfront.')
        },
        {
            q: t('How are my tenant company data and fiscal keys secured?'),
            a: t('Every workspace is strictly isolated by creatorId scoping, protected by AES-256 encryption at rest, TLS 1.3 in transit, role-based access control, and immutable audit trails.')
        },
        {
            q: t('Can we add or remove ERP modules as we grow?'),
            a: t('Yes — Superadmins and company admins can enable or upgrade individual modules (such as Coffee Export, Real Estate, Inventory, or AI Copilot) anytime with automatic prorated adjustments.')
        }
    ];

    return (
        <div className="min-h-screen bg-[#FDFDFD] text-slate-900 font-sans selection:bg-[#00A76F] selection:text-white relative overflow-x-hidden">
            <Head title={`Pricing — ${settings?.company_name || 'GadaaCloud'} ERP`}>
                {faviconUrl && <link rel="icon" type="image/x-icon" href={faviconUrl} />}
            </Head>

            <SiteHeader settings={settings} activePage="pricing" />

            <main>
                {/* ============ COMPACT PRICING HERO ============ */}
                <section className="relative pt-8 pb-8 md:pt-10 md:pb-10 overflow-hidden bg-gradient-to-b from-emerald-50/75 via-emerald-50/20 to-white">
                    <CloudTopologyPattern className="absolute inset-0 opacity-80" />
                    <div className="absolute top-0 left-1/2 -translate-x-1/2 w-[480px] h-[280px] bg-[#00A76F]/10 rounded-full blur-3xl pointer-events-none" />

                    <div className="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-4 z-10">
                        <div className="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-emerald-100/80 border border-emerald-200 text-xs font-bold text-[#00A76F] shadow-2xs">
                            <ShieldCheck className="h-3.5 w-3.5 text-[#00A76F]" />
                            <span>{t('Transparent Birr Pricing')} · MoR EIMS Ready</span>
                        </div>

                        <h1 className="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 tracking-tight leading-[1.12]">
                            {pricingSettings.title || t('Simple, secured cloud pricing in Birr.')}
                        </h1>

                        <p className="text-sm sm:text-base text-slate-600 max-w-2xl mx-auto leading-relaxed">
                            {pricingSettings.subtitle || t('Choose a plan built for Ethiopian enterprises — complete with AES-256 cloud security, dual calendar engine, and zero hidden fees.')}
                        </p>

                        {/* Monthly / Yearly Toggle */}
                        {showToggle && (
                            <div className="flex items-center justify-center pt-2">
                                <div className="inline-flex p-1 rounded-2xl bg-slate-100 border border-slate-200 shadow-2xs">
                                    {(['monthly', 'yearly'] as const).map((type) => {
                                        const isSelected = priceType === type;
                                        return (
                                            <button
                                                key={type}
                                                onClick={() => setPriceType(type)}
                                                className={`px-5 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all duration-200 flex items-center gap-2 ${
                                                    isSelected
                                                        ? 'text-white shadow-md shadow-emerald-500/20'
                                                        : 'text-slate-600 hover:text-slate-900'
                                                }`}
                                                style={isSelected ? { background: grad } : undefined}
                                            >
                                                <span>{type === 'monthly' ? t('Monthly Billing') : t('Yearly Billing')}</span>
                                                {type === 'yearly' && yearlySavingsMonths > 0 && (
                                                    <span
                                                        className={`text-[10px] font-black px-2 py-0.5 rounded-full ${
                                                            isSelected
                                                                ? 'bg-white/20 text-white'
                                                                : 'bg-emerald-100 text-[#00A76F]'
                                                        }`}
                                                    >
                                                        {t('Save')} {yearlySavingsMonths} {t('mo')}
                                                    </span>
                                                )}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>
                </section>

                {/* ============ PRICING CARDS GRID ============ */}
                <section className="pb-10 bg-white relative">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        {showPrePackage && plans.length > 0 ? (
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 items-stretch">
                                {plans.map((plan) => {
                                    const isPopular = plan.id === mostPopularPlanId;
                                    const planModules = plan.modules || [];
                                    const enabledAddOns = activeModules.filter((m) => planModules.includes(m.module));
                                    const totalAddOns = activeModules.length;
                                    const activePrice =
                                        priceType === 'yearly'
                                            ? plan.package_price_yearly
                                            : plan.package_price_monthly;

                                    return (
                                        <div
                                            key={plan.id}
                                            className={`relative flex flex-col rounded-3xl transition-all duration-300 overflow-hidden ${
                                                isPopular
                                                    ? 'bg-gradient-to-b from-[#031B13] via-[#042A1D] to-[#022C22] text-white border-2 border-[#00A76F] shadow-2xl lg:-translate-y-1.5'
                                                    : 'bg-white text-slate-900 border border-slate-200/90 shadow-md hover:shadow-xl hover:border-emerald-200'
                                            }`}
                                        >
                                            {isPopular && (
                                                <>
                                                    <SecurityHexPattern className="absolute inset-0 opacity-65" />
                                                    <CloudSecurityWatermark className="absolute -right-8 -top-6 w-44 h-36 text-[#34D399] opacity-20" />
                                                </>
                                            )}

                                            {/* Top Status Badge */}
                                            <div className="px-6 pt-6 flex items-center justify-between relative z-10">
                                                <h3 className={`text-xl font-black tracking-tight ${isPopular ? 'text-white' : 'text-slate-900'}`}>
                                                    {plan.name}
                                                </h3>
                                                {isPopular ? (
                                                    <span className="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-[#00A76F] text-white shadow-sm">
                                                        <Sparkles className="h-3 w-3" /> {t('Most Popular')}
                                                    </span>
                                                ) : plan.free_plan ? (
                                                    <span className="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-[#00A76F] border border-emerald-200">
                                                        {t('Starter Free')}
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">
                                                        <LockKeyhole className="h-3 w-3 text-[#00A76F]" /> Secured Cloud
                                                    </span>
                                                )}
                                            </div>

                                            <div className="p-6 flex-1 flex flex-col relative z-10">
                                                <p className={`text-xs mb-5 min-h-[32px] leading-relaxed ${isPopular ? 'text-emerald-100/80' : 'text-slate-500'}`}>
                                                    {plan.description || t('Full multi-tenant Ethiopian cloud workspace with EIMS compliance.')}
                                                </p>

                                                {/* Price Display (Fixed Monthly/Yearly Switch) */}
                                                <div className={`mb-5 pb-5 border-b ${isPopular ? 'border-emerald-800/60' : 'border-slate-100'}`}>
                                                    {plan.free_plan ? (
                                                        <div className="flex items-baseline gap-2">
                                                            <span className="text-4xl font-black text-[#34D399]" style={!isPopular ? { color: colors.primary } : undefined}>
                                                                {t('Free')}
                                                            </span>
                                                            <span className={`text-xs font-bold uppercase tracking-wider ${isPopular ? 'text-emerald-300/80' : 'text-slate-400'}`}>
                                                                {t('Forever')}
                                                            </span>
                                                        </div>
                                                    ) : (
                                                        <div>
                                                            <div className="flex items-baseline gap-1.5">
                                                                <span className={`text-3xl sm:text-4xl font-black tracking-tight ${isPopular ? 'text-white' : 'text-slate-900'}`}>
                                                                    {formatAdminCurrency(activePrice, pageProps)}
                                                                </span>
                                                                <span className={`text-sm font-semibold ${isPopular ? 'text-emerald-300/80' : 'text-slate-500'}`}>
                                                                    /{priceType === 'monthly' ? t('mo') : t('yr')}
                                                                </span>
                                                            </div>
                                                            <p className={`text-[11px] mt-1.5 font-medium ${isPopular ? 'text-emerald-300/75' : 'text-slate-500'}`}>
                                                                {priceType === 'yearly'
                                                                    ? `${t('Billed annually')} · ${t('MoR VAT receipt included')}`
                                                                    : `${t('Billed monthly')} · ${t('Cancel anytime')}`}
                                                            </p>
                                                        </div>
                                                    )}
                                                </div>

                                                {/* Resource Quotas */}
                                                <div className="space-y-2.5 mb-5 text-xs font-semibold">
                                                    <div className={`flex items-center gap-2.5 ${isPopular ? 'text-emerald-100' : 'text-slate-700'}`}>
                                                        <div className={`w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 ${isPopular ? 'bg-emerald-500/20 text-[#34D399]' : 'bg-emerald-50 text-[#00A76F]'}`}>
                                                            <Users className="h-3.5 w-3.5" />
                                                        </div>
                                                        <span>
                                                            {plan.number_of_users === -1
                                                                ? t('Unlimited Team Seats')
                                                                : `${plan.number_of_users} ${t('Team Users Included')}`}
                                                        </span>
                                                    </div>

                                                    <div className={`flex items-center gap-2.5 ${isPopular ? 'text-emerald-100' : 'text-slate-700'}`}>
                                                        <div className={`w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 ${isPopular ? 'bg-emerald-500/20 text-[#34D399]' : 'bg-emerald-50 text-[#00A76F]'}`}>
                                                            <HardDrive className="h-3.5 w-3.5" />
                                                        </div>
                                                        <span>
                                                            {plan.storage_limit > 0
                                                                ? `${Math.max(1, Math.round(plan.storage_limit / (1024 * 1024)))} ${t('GB Encrypted Cloud Storage')}`
                                                                : t('Standard Cloud Storage')}
                                                        </span>
                                                    </div>

                                                    {plan.trial && !plan.free_plan && (
                                                        <div className={`flex items-center gap-2.5 ${isPopular ? 'text-[#34D399]' : 'text-[#00A76F]'}`}>
                                                            <div className={`w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 ${isPopular ? 'bg-emerald-500/25 text-[#34D399]' : 'bg-emerald-100 text-[#00A76F]'}`}>
                                                                <Sparkles className="h-3.5 w-3.5" />
                                                            </div>
                                                            <span>{plan.trial_days}-{t('Day Risk-Free Trial')}</span>
                                                        </div>
                                                    )}
                                                </div>

                                                {/* Modules Preview */}
                                                <div className={`p-3.5 rounded-2xl mb-5 ${isPopular ? 'bg-emerald-950/60 border border-emerald-500/20' : 'bg-slate-50/80 border border-slate-100'}`}>
                                                    <div className={`text-[10px] font-bold uppercase tracking-wider mb-2.5 flex items-center justify-between ${isPopular ? 'text-emerald-300' : 'text-slate-500'}`}>
                                                        <span>{t('Included ERP Modules')}</span>
                                                        <span className={isPopular ? 'text-[#34D399]' : 'text-[#00A76F]'}>
                                                            {enabledAddOns.length} / {totalAddOns}
                                                        </span>
                                                    </div>
                                                    <ul className="grid grid-cols-2 gap-1.5">
                                                        {enabledAddOns.slice(0, 6).map((m) => (
                                                            <li key={m.module} className={`flex items-center text-[11px] font-medium ${isPopular ? 'text-emerald-100/90' : 'text-slate-700'}`}>
                                                                <Check className={`h-3 w-3 mr-1.5 flex-shrink-0 stroke-[3] ${isPopular ? 'text-[#34D399]' : 'text-[#00A76F]'}`} />
                                                                <span className="truncate">{m.alias}</span>
                                                            </li>
                                                        ))}
                                                    </ul>
                                                    {enabledAddOns.length > 6 && (
                                                        <div className={`text-[10px] font-bold mt-2 ${isPopular ? 'text-[#34D399]' : 'text-[#00A76F]'}`}>
                                                            +{enabledAddOns.length - 6} {t('additional modules included')}
                                                        </div>
                                                    )}
                                                </div>

                                                <div className="flex-1" />

                                                {/* CTA Button */}
                                                <div className="space-y-2">
                                                    <button
                                                        onClick={handleCTA}
                                                        className={`w-full py-3 px-4 rounded-xl text-sm font-bold flex items-center justify-center gap-2 transition-all duration-200 hover:scale-[1.01] ${
                                                            isPopular
                                                                ? 'bg-[#00A76F] hover:bg-[#059669] text-white shadow-lg shadow-emerald-950/60'
                                                                : 'bg-emerald-50 hover:bg-[#00A76F] text-[#00A76F] hover:text-white border border-emerald-200'
                                                        }`}
                                                    >
                                                        <span>
                                                            {isAuthed
                                                                ? t('Open Dashboard')
                                                                : plan.free_plan
                                                                    ? t('Get Started Free')
                                                                    : plan.trial
                                                                        ? `${t('Start')} ${plan.trial_days}-${t('Day Trial')}`
                                                                        : t('Choose Plan')}
                                                        </span>
                                                        <ArrowRight className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="text-center py-12 bg-slate-50 rounded-3xl border border-slate-200/80">
                                <div className="w-16 h-16 mx-auto mb-4 rounded-2xl bg-emerald-50 flex items-center justify-center text-[#00A76F]">
                                    <CloudCog className="h-8 w-8" />
                                </div>
                                <h3 className="text-lg font-bold text-slate-900 mb-1">{t('Custom Enterprise Plans Available')}</h3>
                                <p className="text-sm text-slate-600 max-w-md mx-auto">
                                    {pricingSettings.empty_message || t('Contact our Addis Ababa team for a tailored Birr ERP deployment.')}
                                </p>
                            </div>
                        )}
                    </div>
                </section>

                {/* ============ CLOUD & SECURITY INCLUDED BAR ============ */}
                <section className="py-8 bg-[#031B13] text-white relative overflow-hidden border-y border-emerald-900/60">
                    <SecurityHexPattern className="absolute inset-0 opacity-70" />
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                            {CLOUD_GUARANTEES.map((item) => {
                                const Icon = item.icon;
                                return (
                                    <div key={item.label} className="flex items-center gap-3 p-3.5 rounded-2xl bg-emerald-950/65 border border-emerald-500/20">
                                        <div className="w-10 h-10 rounded-xl bg-emerald-500/15 border border-emerald-400/20 flex items-center justify-center text-[#34D399] flex-shrink-0">
                                            <Icon className="h-5 w-5" />
                                        </div>
                                        <div>
                                            <div className="text-xs sm:text-sm font-bold text-white">{item.label}</div>
                                            <div className="text-[11px] text-emerald-200/75">{item.sub}</div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </section>

                {/* ============ COMPARISON TOGGLE & COLLAPSIBLE MATRIX ============ */}
                {showPrePackage && plans.length > 0 && activeModules.length > 0 && (
                    <section className="py-8 sm:py-10 bg-white relative">
                        <CircuitDotPattern className="absolute inset-0 opacity-45" />
                        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                            <div className="text-center">
                                <button
                                    onClick={() => setShowCompare(!showCompare)}
                                    className="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-xs sm:text-sm font-bold transition-all shadow-sm hover:shadow-md border-2"
                                    style={{
                                        backgroundColor: showCompare ? colors.primary : '#ffffff',
                                        color: showCompare ? '#ffffff' : colors.primary,
                                        borderColor: colors.primary,
                                    }}
                                >
                                    <Layers className="h-4 w-4" />
                                    <span>{showCompare ? t('Hide Full Module Matrix') : t('Compare All 34 Modules Across Plans')}</span>
                                    <ChevronDown className={`h-4 w-4 transition-transform duration-200 ${showCompare ? 'rotate-180' : ''}`} />
                                </button>
                            </div>

                            {showCompare && (
                                <div className="mt-6 overflow-x-auto rounded-3xl border border-emerald-100 shadow-xl bg-white animate-in fade-in duration-200">
                                    <table className="w-full text-xs sm:text-sm min-w-[640px]">
                                        <thead>
                                            <tr className="bg-slate-50 border-b border-slate-200">
                                                <th className="text-left p-4 font-black text-slate-900 sticky left-0 bg-slate-50 z-10 border-r border-slate-200">
                                                    {t('Enterprise Module')}
                                                </th>
                                                {plans.map((plan) => {
                                                    const isPop = plan.id === mostPopularPlanId;
                                                    return (
                                                        <th
                                                            key={plan.id}
                                                            className="p-4 text-center font-black min-w-[150px]"
                                                            style={isPop ? { color: colors.primary } : { color: '#0f172a' }}
                                                        >
                                                            <div>{plan.name}</div>
                                                            {isPop && (
                                                                <span className="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-100 text-[#00A76F] inline-block mt-1">
                                                                    {t('Popular')}
                                                                </span>
                                                            )}
                                                        </th>
                                                    );
                                                })}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {activeModules.map((module, mi) => (
                                                <tr key={module.module} className={mi % 2 === 0 ? 'bg-white' : 'bg-slate-50/40'}>
                                                    <td
                                                        className="text-left px-4 py-3 font-semibold text-slate-800 sticky left-0 z-10 border-r border-slate-100"
                                                        style={{ backgroundColor: mi % 2 === 0 ? '#ffffff' : '#f8fafc' }}
                                                    >
                                                        {module.alias}
                                                    </td>
                                                    {plans.map((plan) => {
                                                        const includes = (plan.modules || []).includes(module.module);
                                                        return (
                                                            <td key={plan.id} className="px-4 py-3 text-center">
                                                                {includes ? (
                                                                    <span className="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-50 text-[#00A76F]">
                                                                        <Check className="h-3.5 w-3.5 stroke-[3]" />
                                                                    </span>
                                                                ) : (
                                                                    <span className="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-100 text-slate-400">
                                                                        <X className="h-3.5 w-3.5" />
                                                                    </span>
                                                                )}
                                                            </td>
                                                        );
                                                    })}
                                                </tr>
                                            ))}
                                            <tr className="bg-emerald-50/30 font-bold">
                                                <td className="text-left px-4 py-3.5 text-slate-900 sticky left-0 z-10 border-r border-slate-200 bg-slate-50">
                                                    {t('Users Included')}
                                                </td>
                                                {plans.map((plan) => (
                                                    <td key={plan.id} className="px-4 py-3.5 text-center text-slate-800">
                                                        {plan.number_of_users === -1 ? t('Unlimited') : plan.number_of_users}
                                                    </td>
                                                ))}
                                            </tr>
                                            <tr className="bg-emerald-50/30 font-bold">
                                                <td className="text-left px-4 py-3.5 text-slate-900 sticky left-0 z-10 border-r border-slate-200 bg-slate-50">
                                                    {t('Cloud Storage')}
                                                </td>
                                                {plans.map((plan) => (
                                                    <td key={plan.id} className="px-4 py-3.5 text-center text-slate-800">
                                                        {plan.storage_limit > 0
                                                            ? `${Math.max(1, Math.round(plan.storage_limit / (1024 * 1024)))} GB`
                                                            : t('Standard')}
                                                    </td>
                                                ))}
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </section>
                )}

                {/* ============ PRICING FAQ ============ */}
                <section id="faq" className="py-10 sm:py-12 bg-slate-50/60 border-t border-slate-200/60">
                    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                        <div className="text-center space-y-2">
                            <span className="inline-flex items-center gap-2 px-3.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-50 text-[#00A76F] border border-emerald-100">
                                <FileLock2 className="h-3.5 w-3.5" /> {t('Billing & Compliance FAQ')}
                            </span>
                            <h2 className="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                                {t('Questions about Birr pricing & local payments.')}
                            </h2>
                        </div>

                        <div className="space-y-2.5">
                            {PRICING_FAQS.map((item, idx) => {
                                const isOpen = openFaq === idx;
                                return (
                                    <div key={idx} className="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden text-left">
                                        <button
                                            onClick={() => setOpenFaq(isOpen ? -1 : idx)}
                                            className="w-full px-5 py-3.5 flex items-center justify-between text-left gap-4 hover:bg-emerald-50/30 transition-colors"
                                        >
                                            <span className="text-xs sm:text-sm font-bold text-slate-900">{item.q}</span>
                                            <ChevronDown className={`h-4 w-4 text-[#00A76F] transition-transform duration-200 flex-shrink-0 ${isOpen ? 'rotate-180' : ''}`} />
                                        </button>
                                        {isOpen && (
                                            <div className="px-5 pb-4 pt-0 text-xs sm:text-sm text-slate-600 leading-relaxed border-t border-slate-100 bg-slate-50/40">
                                                <p className="pt-2.5">{item.a}</p>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </section>

                {/* ============ BOTTOM CTA ============ */}
                <section className="py-10 sm:py-12 bg-white">
                    <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="rounded-3xl bg-gradient-to-r from-[#00A76F] via-[#059669] to-emerald-800 text-white p-8 sm:p-10 text-center shadow-2xl space-y-5 relative overflow-hidden">
                            <CloudSecurityWatermark className="absolute -right-8 -top-6 w-52 h-44 text-white opacity-20" />
                            <div className="space-y-2 max-w-2xl mx-auto relative z-10">
                                <span className="px-3 py-1 rounded-full text-[11px] font-bold bg-white/20 text-white border border-white/20 uppercase tracking-widest inline-flex items-center gap-1.5">
                                    <ServerCog className="h-3.5 w-3.5" /> Enterprise Consultation
                                </span>
                                <h2 className="text-2xl sm:text-3xl font-black tracking-tight">
                                    {t('Still deciding which plan fits your team?')}
                                </h2>
                                <p className="text-emerald-50 text-xs sm:text-sm max-w-xl mx-auto">
                                    {t('Speak with our Addis Ababa ERP specialists — we will tailor a Birr subscription and data migration roadmap for your sector.')}
                                </p>
                            </div>
                            <div className="flex flex-col sm:flex-row gap-3 justify-center relative z-10">
                                <button
                                    onClick={handleCTA}
                                    className="px-7 py-3 rounded-xl text-sm font-bold bg-white text-[#00A76F] hover:bg-slate-50 shadow-xl transition-all inline-flex items-center justify-center gap-2"
                                >
                                    {isAuthed ? t('Go to Dashboard') : t('Start Free Trial')} <ArrowRight className="h-4 w-4" />
                                </button>
                                <a
                                    href={`mailto:${settings?.contact_email || 'info@gadaacloud.et'}?subject=GadaaCloud%20Plan%20Consultation`}
                                    className="px-7 py-3 rounded-xl text-sm font-bold border-2 border-white/80 text-white hover:bg-white/10 transition-all inline-flex items-center justify-center"
                                >
                                    {t('Contact Addis Ababa Sales')}
                                </a>
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            <SiteFooter settings={settings} />
            <CookieConsent settings={adminAllSetting || {}} />
        </div>
    );
}
