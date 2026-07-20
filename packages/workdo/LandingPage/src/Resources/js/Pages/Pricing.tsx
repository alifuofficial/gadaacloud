import { Head, router, usePage } from '@inertiajs/react';
import Header from './components/Header';
import Footer from './components/Footer';
import FAQ from './components/FAQ';
import { getAdminSetting, getImagePath, formatAdminCurrency } from '@/utils/helpers';
import { useState } from 'react';
import CookieConsent from "@/components/cookie-consent";
import { useTranslation } from 'react-i18next';
import { Check, X, Users, HardDrive, Sparkles, ArrowRight } from 'lucide-react';

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

export default function Pricing(props: PricingProps) {
    const { t } = useTranslation();
    const favicon = getAdminSetting('favicon');
    const faviconUrl = favicon ? getImagePath(favicon) : null;
    const { adminAllSetting, auth } = usePage().props as any;

    const plans = props.plans || [];
    const activeModules = props.activeModules || [];
    const settings = { ...props.settings, is_authenticated: !!(auth?.user?.id !== undefined && auth?.user?.id !== null) };
    const colors = settings?.config_sections?.colors || { primary: '#078930', secondary: '#054a2b', accent: '#fcdd09' };
    const pricingSettings = settings?.config_sections?.sections?.pricing || {};

    const [priceType, setPriceType] = useState<'monthly' | 'yearly'>(pricingSettings.default_price_type || 'monthly');
    const [showCompare, setShowCompare] = useState(false);

    const isAuthed = !!settings?.is_authenticated;

    const mostPopularPlanId = plans.length > 0
        ? plans.reduce((prev, current) =>
            (current.orders_count || 0) > (prev.orders_count || 0) ? current : prev
          ).id
        : null;

    const priceFormatter = priceType === 'monthly'
        ? formatAdminCurrency
        : formatAdminCurrency;

    const handleCTA = (plan: Plan) => {
        if (isAuthed) {
            router.visit(route('dashboard'));
        } else if (plan.trial && !plan.free_plan) {
            router.visit(route('register'));
        } else {
            router.visit(route('register'));
        }
    };

    const YearlySavings = () => {
        // Detect if yearly is offering a discount vs paying monthly*12
        const paid = plans.find(p => !p.free_plan);
        if (!paid || paid.package_price_yearly <= 0) return null;
        const monthlyCost = paid.package_price_monthly * 12;
        const yearlyCost = paid.package_price_yearly;
        if (yearlyCost >= monthlyCost) return null;
        const monthsFree = Math.round((monthlyCost - yearlyCost) / paid.package_price_monthly);
        if (monthsFree <= 0) return null;
        return (
            <span
                className="ml-2 text-xs font-bold px-2 py-1 rounded-full"
                style={{ backgroundColor: `${colors.accent}25`, color: colors.secondary }}
            >
                {t('Save')} {monthsFree} {t('months free')}
            </span>
        );
    };

    return (
        <>
            <Head title="Pricing — GadaaCloud ERP">
                {faviconUrl && <link rel="icon" type="image/x-icon" href={faviconUrl} />}
            </Head>

            <Header settings={settings} />

            <main className="min-h-screen bg-white">
                {/* Hero */}
                <section className="pt-20 pb-12 relative overflow-hidden">
                    <div
                        className="absolute inset-0 pointer-events-none"
                        style={{ background: `linear-gradient(180deg, ${colors.primary}08 0%, #ffffff 100%)` }}
                    />
                    <div
                        className="absolute top-10 left-1/2 -translate-x-1/2 w-96 h-96 rounded-full blur-3xl opacity-30"
                        style={{ backgroundColor: colors.primary }}
                    />
                    <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                        <span
                            className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-semibold tracking-wide uppercase mb-5"
                            style={{ backgroundColor: `${colors.primary}15`, color: colors.primary }}
                        >
                            <Sparkles className="h-3.5 w-3.5" style={{ stroke: colors.accent }} />
                            {t('Pricing in Birr')} · VAT & TIN ready
                        </span>
                        <h1 className="text-4xl md:text-5xl lg:text-6xl font-bold text-gray-900 mb-6 leading-tight tracking-tight">
                            {pricingSettings.title || t('Simple pricing that scales with your Ethiopian business')}
                        </h1>
                        <p className="text-lg md:text-xl text-gray-600 max-w-3xl mx-auto mb-10 leading-relaxed">
                            {pricingSettings.subtitle || t('Choose the perfect subscription plan for your business needs')}
                        </p>

                        {/* Monthly / Yearly Toggle */}
                        {pricingSettings.show_monthly_yearly_toggle === true && (
                            <div className="flex items-center justify-center mb-12">
                                <div className="inline-flex p-1 rounded-full bg-gray-100 border border-gray-200">
                                    {(['monthly', 'yearly'] as const).map((type) => (
                                        <button
                                            key={type}
                                            onClick={() => setPriceType(type)}
                                            className={`relative px-6 py-2.5 rounded-full text-sm font-semibold transition-all duration-300 ${
                                                priceType === type ? 'text-white shadow-lg' : 'text-gray-600 hover:text-gray-900'
                                            }`}
                                            style={priceType === type ? { background: `linear-gradient(135deg, ${colors.primary}, ${colors.secondary})` } : {}}
                                        >
                                            {type === 'monthly' ? t('Monthly') : t('Yearly')}
                                            {type === 'yearly' && <YearlySavings />}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </section>

                {/* Plan cards */}
                <section className="pb-16">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        {pricingSettings.show_pre_package === true && plans.length > 0 ? (
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 items-stretch">
                                {plans.map((plan) => {
                                    const isPopular = plan.id === mostPopularPlanId && plans.length > 1;
                                    const enabledAddOns = activeModules.filter(m => plan.modules?.includes(m.module));
                                    const totalAddOns = activeModules.length;

                                    return (
                                        <div
                                            key={plan.id}
                                            className={`relative flex flex-col bg-white rounded-3xl border-2 transition-all duration-500 ${
                                                isPopular
                                                    ? 'shadow-2xl md:scale-105 lg:-mt-4 lg:mb-4'
                                                    : 'shadow-sm hover:shadow-xl'
                                            }`}
                                            style={isPopular ? {
                                                borderColor: colors.primary,
                                                boxShadow: `0 20px 50px -15px ${colors.primary}40`
                                            } : { borderColor: '#e5e7eb' }}
                                        >
                                            {isPopular && (
                                                <div className="absolute -top-4 left-1/2 -translate-x-1/2 z-10">
                                                    <span
                                                        className="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-bold text-white shadow-lg rounded-full"
                                                        style={{ background: `linear-gradient(135deg, ${colors.primary}, ${colors.secondary})` }}
                                                    >
                                                        <Sparkles className="h-3.5 w-3.5" style={{ fill: colors.accent, stroke: colors.accent }} />
                                                        {t('Most Popular')}
                                                    </span>
                                                </div>
                                            )}

                                            {plan.free_plan && (
                                                <div className="absolute -top-4 left-1/2 -translate-x-1/2 z-10">
                                                    <span
                                                        className="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-bold text-white shadow-lg rounded-full"
                                                        style={{ backgroundColor: colors.accent, color: colors.secondary }}
                                                    >
                                                        {t('Get Started Free')}
                                                    </span>
                                                </div>
                                            )}

                                            <div className="p-8 md:p-10 flex-1 flex flex-col">
                                                {/* Name + description */}
                                                <div className="mb-6">
                                                    <h3 className="text-2xl font-bold text-gray-900 mb-2">{plan.name}</h3>
                                                    <p className="text-sm text-gray-500">{plan.description || '\u00a0'}</p>
                                                </div>

                                                {/* Price */}
                                                <div className="mb-6">
                                                    {plan.free_plan ? (
                                                        <div className="flex items-baseline gap-2">
                                                            <span className="text-5xl font-black" style={{ color: colors.primary }}>{t('Free')}</span>
                                                            <span className="text-base text-gray-500 font-semibold">{t('Forever')}</span>
                                                        </div>
                                                    ) : (
                                                        <div className="flex items-baseline gap-1">
                                                            <span className="text-5xl font-black text-gray-900">
                                                                {priceFormatter(plan.package_price_monthly, usePage().props)}
                                                            </span>
                                                            <span className="text-gray-500 font-semibold text-lg">
                                                                /{priceType === 'monthly' ? t('mo') : t('yr')}
                                                            </span>
                                                        </div>
                                                    )}
                                                    {!plan.free_plan && priceType === 'yearly' && (
                                                        <p className="text-xs text-gray-500 mt-2">
                                                            {t('billed yearly')} · {t('VAT included')}
                                                        </p>
                                                    )}
                                                    {!plan.free_plan && priceType === 'monthly' && (
                                                        <p className="text-xs text-gray-500 mt-2">
                                                            {t('billed monthly')} · {t('cancel anytime')}
                                                        </p>
                                                    )}
                                                </div>

                                                {/* Key specs */}
                                                <div className="space-y-3 mb-6 pb-6 border-b border-gray-100">
                                                    <div className="flex items-center gap-3 text-sm text-gray-700">
                                                        <span
                                                            className="w-8 h-8 rounded-lg flex items-center justify-center"
                                                            style={{ backgroundColor: `${colors.primary}12` }}
                                                        >
                                                            <Users className="h-4 w-4" style={{ color: colors.primary }} />
                                                        </span>
                                                        {plan.number_of_users === -1
                                                            ? t('Unlimited users')
                                                            : `${plan.number_of_users} ${t('users included')}`}
                                                    </div>
                                                    <div className="flex items-center gap-3 text-sm text-gray-700">
                                                        <span
                                                            className="w-8 h-8 rounded-lg flex items-center justify-center"
                                                            style={{ backgroundColor: `${colors.primary}12` }}
                                                        >
                                                            <HardDrive className="h-4 w-4" style={{ color: colors.primary }} />
                                                        </span>
                                                        {plan.storage_limit > 0
                                                            ? `${Math.round(plan.storage_limit / (1024 * 1024))} ${t('GB storage')}`
                                                            : t('Standard cloud storage')}
                                                    </div>
                                                    {plan.trial && !plan.free_plan && (
                                                        <div className="flex items-center gap-3 text-sm font-medium" style={{ color: colors.primary }}>
                                                            <span
                                                                className="w-8 h-8 rounded-lg flex items-center justify-center"
                                                                style={{ backgroundColor: `${colors.accent}30` }}
                                                            >
                                                                <Sparkles className="h-4 w-4" style={{ stroke: colors.accent }} />
                                                            </span>
                                                            {plan.trial_days}-day {t('free trial')}
                                                        </div>
                                                    )}
                                                </div>

                                                {/* Module preview (first N) */}
                                                <div className="mb-6">
                                                    <div className="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">
                                                        {enabledAddOns.length} / {totalAddOns} {t('modules enabled')}
                                                    </div>
                                                    <ul className="grid grid-cols-2 gap-2">
                                                        {enabledAddOns.slice(0, 6).map((m) => (
                                                            <li key={m.module} className="flex items-center text-xs text-gray-700">
                                                                <Check
                                                                    className="h-3.5 w-3.5 mr-1.5 flex-shrink-0"
                                                                    style={{ stroke: colors.primary, strokeWidth: 3 }}
                                                                />
                                                                <span className="truncate">{m.alias}</span>
                                                            </li>
                                                        ))}
                                                        {enabledAddOns.length > 6 && (
                                                            <li className="text-xs text-gray-500 italic">
                                                                +{enabledAddOns.length - 6} {t('more')}
                                                            </li>
                                                        )}
                                                    </ul>
                                                </div>

                                                {/* Spacer */}
                                                <div className="flex-1" />

                                                {/* CTA */}
                                                <div className="space-y-3">
                                                    <button
                                                        onClick={() => handleCTA(plan)}
                                                        className={`w-full py-3.5 px-4 rounded-xl font-bold flex items-center justify-center transition-all duration-300 ${
                                                            isPopular
                                                                ? 'text-white shadow-lg hover:shadow-2xl transform hover:-translate-y-0.5'
                                                                : 'hover:transform hover:-translate-y-0.5'
                                                        }`}
                                                        style={isPopular
                                                            ? { background: `linear-gradient(135deg, ${colors.primary}, ${colors.secondary})` }
                                                            : { backgroundColor: `${colors.primary}10`, color: colors.primary }
                                                        }
                                                    >
                                                        {isAuthed
                                                            ? t('Go to Dashboard')
                                                            : plan.free_plan
                                                                ? t('Get Started Free')
                                                                : plan.trial
                                                                    ? `${t('Start Free Trial')} (${plan.trial_days}d)`
                                                                    : t('Get Started')
                                                        }
                                                        <ArrowRight className="ml-2 h-4 w-4" />
                                                    </button>
                                                    {!isAuthed && !plan.free_plan && (
                                                        <button
                                                            onClick={() => router.visit(route('login'))}
                                                            className="w-full py-2.5 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors"
                                                        >
                                                            {t('Already have an account? Sign in')}
                                                        </button>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="text-center py-20">
                                <div className="w-24 h-24 mx-auto mb-6 rounded-2xl flex items-center justify-center" style={{ backgroundColor: `${colors.primary}10` }}>
                                    <Sparkles className="h-10 w-10" style={{ color: colors.primary }} />
                                </div>
                                <h3 className="text-xl font-bold text-gray-900 mb-2">{t('No Plans Available Yet')}</h3>
                                <p className="text-gray-600 max-w-md mx-auto">
                                    {pricingSettings.empty_message || t('Check back later for new pricing plans.')}
                                </p>
                            </div>
                        )}
                    </div>
                </section>

                {/* Comparison toggle */}
                {pricingSettings.show_pre_package === true && plans.length > 0 && activeModules.length > 0 && (
                    <section className="py-12 bg-gray-50 border-y border-gray-100">
                        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                            <button
                                onClick={() => setShowCompare(!showCompare)}
                                className="inline-flex items-center gap-2 px-6 py-3 rounded-full font-semibold transition-all duration-300 shadow-sm hover:shadow-lg"
                                style={{
                                    backgroundColor: showCompare ? colors.primary : '#ffffff',
                                    color: showCompare ? '#fff' : colors.primary,
                                    border: `2px solid ${colors.primary}`
                                }}
                            >
                                {showCompare ? t('Hide feature comparison') : t('Compare all features')}
                                <ArrowRight className={`h-4 w-4 transition-transform ${showCompare ? 'rotate-90' : ''}`} />
                            </button>
                        </div>
                    </section>
                )}

                {/* Full comparison table — collapsible */}
                {showCompare && plans.length > 0 && activeModules.length > 0 && (
                    <section className="py-16 bg-white">
                        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                            <div className="overflow-x-auto rounded-3xl border border-gray-200 shadow-sm">
                                <table className="w-full text-sm min-w-[640px]">
                                    <thead>
                                        <tr className="bg-gray-50">
                                            <th className="text-left p-5 font-bold text-gray-900 sticky left-0 bg-gray-50 z-10 border-r border-gray-200">
                                                {t('Module')}
                                            </th>
                                            {plans.map(plan => {
                                                const isPop = plan.id === mostPopularPlanId && plans.length > 1;
                                                return (
                                                    <th
                                                        key={plan.id}
                                                        className="p-5 text-center font-bold text-gray-900 min-w-[180px]"
                                                        style={isPop ? { color: colors.primary } : undefined}
                                                    >
                                                        {plan.name}
                                                        {isPop && (
                                                            <div className="mt-1">
                                                                <span className="text-[10px] font-bold uppercase tracking-wide" style={{ color: colors.primary }}>
                                                                    ⭐ {t('Popular')}
                                                                </span>
                                                            </div>
                                                        )}
                                                    </th>
                                                );
                                            })}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {activeModules.map((module, mi) => (
                                            <tr
                                                key={module.module}
                                                className={mi % 2 === 0 ? 'bg-white' : 'bg-gray-50/30'}
                                            >
                                                <td className="text-left p-5 font-medium text-gray-800 sticky left-0 z-10 border-r border-gray-100" style={{ backgroundColor: mi % 2 === 0 ? '#fff' : '#f9fafb' }}>
                                                    {module.alias}
                                                </td>
                                                {plans.map(plan => {
                                                    const includes = plan.modules?.includes(module.module);
                                                    return (
                                                        <td key={plan.id} className="p-5 text-center">
                                                            {includes ? (
                                                                <span
                                                                    className="inline-flex items-center justify-center w-6 h-6 rounded-full"
                                                                    style={{ backgroundColor: `${colors.primary}15` }}
                                                                >
                                                                    <Check className="h-4 w-4" style={{ stroke: colors.primary, strokeWidth: 3 }} />
                                                                </span>
                                                            ) : (
                                                                <span className="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100">
                                                                    <X className="h-4 w-4 text-gray-400" />
                                                                </span>
                                                            )}
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        ))}
                                        <tr>
                                            <td className="text-left p-5 font-bold text-gray-900 sticky left-0 z-10 border-r border-gray-100 bg-gray-50">
                                                {t('Users')}
                                            </td>
                                            {plans.map(plan => (
                                                <td key={plan.id} className="p-5 text-center font-medium text-gray-700">
                                                    {plan.number_of_users === -1 ? t('Unlimited') : plan.number_of_users}
                                                </td>
                                            ))}
                                        </tr>
                                        <tr>
                                            <td className="text-left p-5 font-bold text-gray-900 sticky left-0 z-10 border-r border-gray-100 bg-gray-50">
                                                {t('Storage')}
                                            </td>
                                            {plans.map(plan => (
                                                <td key={plan.id} className="p-5 text-center font-medium text-gray-700">
                                                    {plan.storage_limit > 0
                                                        ? `${Math.round(plan.storage_limit / (1024 * 1024))} GB`
                                                        : t('Standard')}
                                                </td>
                                            ))}
                                        </tr>
                                        <tr>
                                            <td className="p-5 sticky left-0 z-10 border-r border-gray-100 bg-white" />
                                            {plans.map(plan => (
                                                <td key={plan.id} className="p-5 text-center">
                                                    <button
                                                        onClick={() => handleCTA(plan)}
                                                        className="text-xs font-bold px-4 py-2 rounded-lg transition-all"
                                                        style={{
                                                            backgroundColor: colors.primary,
                                                            color: '#fff'
                                                        }}
                                                    >
                                                        {isAuthed ? t('Dashboard') : t('Choose')} {plan.name}
                                                    </button>
                                                </td>
                                            ))}
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                )}

                {/* Pricing FAQ reusing the FAQ component with ET pricing questions */}
                <section id="faq" className="bg-gradient-to-b from-white to-gray-50 py-20">
                    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center mb-12">
                            <span
                                className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-semibold tracking-wide uppercase mb-4"
                                style={{ backgroundColor: `${colors.primary}15`, color: colors.primary }}
                            >
                                {t('Pricing Questions')}
                            </span>
                            <h2 className="text-3xl md:text-4xl font-bold text-gray-900 mb-4 tracking-tight">
                                {t('Pricing Questions from Ethiopian Businesses')}
                            </h2>
                            <p className="text-lg text-gray-600">
                                {t('Everything you need to know about billing, trials and Birr pricing.')}
                            </p>
                        </div>

                        <div className="space-y-3">
                            {[
                                { q: t('Are prices in Ethiopian Birr (ETB)?'),        a: t('Yes — every plan is billed in ETB with VAT included. Add-ons and overages are also billed in Birr so your finance team has zero conversion work.') },
                                { q: t('Can I try it before paying?'),                  a: t('Yes — paid plans include a free trial (Starter 14 days, Professional 30 days). No credit card required up front.') },
                                { q: t('What happens after the trial ends?'),           a: t('You pick the subscription plan that fits your team. We can also down-grade you to the Forever-Free plan so your data stays safe.') },
                                { q: t('Do you offer discounts for Ethiopian SMEs?'),   a: t('Yes — yearly billing saves months versus paying monthly. Cooperatives, NGOs and startups can also request tailored rates by contacting sales.') },
                                { q: t('Which payment methods are supported?'),        a: t('Telebirr, CBE birr, bank transfer to our Commercial Bank of Ethiopia account, Visa/Mastercard via Stripe, and PayPal.') },
                                { q: t('Can I add or remove modules later?'),           a: t('Absolutely — you can upgrade, downgrade or add-on modules at any time from the Super Admin dashboard. We prorate the difference automatically.') }
                            ].map((item, i) => (
                                <details
                                    key={i}
                                    className="group bg-white rounded-2xl border border-gray-200 hover:border-gray-300 transition-colors overflow-hidden"
                                >
                                    <summary
                                        className="flex items-center justify-between px-6 py-5 cursor-pointer list-none"
                                    >
                                        <span className="font-semibold text-gray-900">{item.q}</span>
                                        <span
                                            className="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center transition-all duration-300 group-open:rotate-45"
                                            style={{ backgroundColor: `${colors.primary}10`, color: colors.primary }}
                                        >
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="3" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4v16m8-8H4" />
                                            </svg>
                                        </span>
                                    </summary>
                                    <div className="px-6 pb-6 text-gray-600 leading-relaxed">{item.a}</div>
                                </details>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Bottom CTA */}
                <section className="relative overflow-hidden py-20">
                    <div
                        className="absolute inset-0"
                        style={{ background: `linear-gradient(135deg, ${colors.primary}, ${colors.secondary})` }}
                    />
                    <div className="absolute inset-0 pointer-events-none">
                        <div className="absolute -top-20 -left-20 w-80 h-80 rounded-full blur-3xl opacity-20" style={{ backgroundColor: colors.accent }} />
                        <div className="absolute -bottom-32 -right-20 w-96 h-96 rounded-full blur-3xl opacity-10" style={{ backgroundColor: '#fff' }} />
                    </div>
                    <div className="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-white">
                        <h2 className="text-3xl md:text-4xl lg:text-5xl font-bold mb-6 leading-tight tracking-tight">
                            {t('Still deciding which plan fits you?')}
                        </h2>
                        <p className="text-lg md:text-xl opacity-90 mb-10 max-w-2xl mx-auto">
                            {t('Talk to our Addis Ababa team — we will help you pick the right plan for your Ethiopian business size and industry.')}
                        </p>
                        <div className="flex flex-col sm:flex-row gap-4 justify-center">
                            <button
                                onClick={() => router.visit(route('register'))}
                                className="inline-flex items-center justify-center px-8 py-4 rounded-xl font-bold shadow-lg transition-all duration-300 transform hover:-translate-y-0.5"
                                style={{ backgroundColor: colors.accent, color: colors.secondary }}
                            >
                                {t('Start Free Trial')} <ArrowRight className="ml-2 h-5 w-5" />
                            </button>
                            <a
                                href="mailto:hello@gadaacloud.et?subject=Plan%20consultation"
                                className="inline-flex items-center justify-center px-8 py-4 rounded-xl font-bold border-2 border-white/40 hover:bg-white/10 hover:border-white transition-all duration-300"
                            >
                                {t('Contact Sales in Addis')}
                            </a>
                        </div>
                    </div>
                </section>
            </main>

            <Footer settings={settings} />
            <CookieConsent settings={adminAllSetting || {}} />
        </>
    );
}