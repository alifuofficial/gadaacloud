import { ArrowRight, Check, TrendingUp, Users, Wallet, ShoppingCart } from 'lucide-react';
import { getImagePath } from '@/utils/helpers';
import { useTranslation } from 'react-i18next';

interface HeroProps {
    settings?: any;
}

const HERO_VARIANTS = {
    hero1: {
        section: 'bg-white py-20 md:py-28 relative overflow-hidden',
        container: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10',
        layout: 'split',
        showImage: true
    },
    hero2: {
        section: 'bg-gray-50 py-24 relative overflow-hidden',
        container: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10',
        layout: 'right-split',
        showImage: true
    },
    hero3: {
        section: 'relative bg-gray-950 py-32 md:py-40 overflow-hidden',
        container: 'relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-white z-10',
        layout: 'background',
        showImage: false
    },
    hero4: {
        section: 'bg-white py-20 border-b border-gray-100',
        container: 'max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center',
        layout: 'minimal',
        showImage: false
    }
};

const TRUST_BADGES = [
    'ETB accounting',
    'VAT & TIN ready',
    'Amharic interface',
    'Coverage in 11 regions',
    'No credit card required'
];

const HeroDashboard = ({ colors }: { colors: any }) => (
    <div className="relative w-full">
        {/* Browser frame */}
        <div className="relative bg-white rounded-2xl shadow-2xl border border-gray-200/80 overflow-hidden transform-gpu transition-transform duration-500 hover:scale-[1.015]">
            {/* Browser top bar */}
            <div className="flex items-center gap-1.5 px-4 py-3 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-gray-100/50">
                <span className="w-2.5 h-2.5 rounded-full bg-red-400/90" />
                <span className="w-2.5 h-2.5 rounded-full bg-yellow-400/90" />
                <span className="w-2.5 h-2.5 rounded-full bg-green-400/90" />
                <div className="ml-3 flex-1 max-w-xs px-3 py-1.5 rounded-full bg-white border border-gray-200/80 text-[10px] text-gray-400 truncate flex items-center gap-1.5">
                    <span
                        className="w-1.5 h-1.5 rounded-full"
                        style={{ backgroundColor: colors.primary }}
                    />
                    app.gadaacloud.et/dashboard
                </div>
                <div className="hidden sm:block ml-2 text-[10px] text-gray-400 font-medium">Addis Ababa HQ</div>
            </div>

            {/* Dashboard body */}
            <div className="flex">
                {/* Mini sidebar */}
                <div className="hidden sm:flex flex-col w-14 bg-gradient-to-b from-gray-50 to-white py-4 border-r border-gray-100">
                    {[0, 1, 2, 3, 4, 5, 6].map((i) => {
                        const active = i === 0;
                        return (
                            <div
                                key={i}
                                className={`mx-auto mb-3 w-7 h-7 rounded-lg transition-all duration-300 ${active ? 'scale-110' : 'hover:scale-110'}`}
                                style={active
                                    ? { background: `linear-gradient(135deg, ${colors.primary}, ${colors.secondary})`, boxShadow: `0 4px 12px ${colors.primary}40` }
                                    : { backgroundColor: `${colors.primary}10` }
                                }
                            />
                        );
                    })}
                </div>

                {/* Content */}
                <div className="flex-1 p-5 md:p-6 bg-gradient-to-br from-gray-50/30 to-white">
                    {/* Header row */}
                    <div className="flex items-center justify-between mb-5">
                        <div>
                            <div className="text-sm font-bold text-gray-900">Welcome back, Selam 👋</div>
                            <div className="text-[10px] text-gray-400">Today's snapshot · Live ETB data</div>
                        </div>
                        <div className="flex items-center gap-2">
                            <div className="px-2.5 py-1 rounded-full text-[10px] font-semibold flex items-center gap-1" style={{ backgroundColor: `${colors.accent}25`, color: colors.secondary }}>
                                <span className="w-1.5 h-1.5 rounded-full animate-pulse" style={{ backgroundColor: colors.primary }} />
                                Live
                            </div>
                            <div className="w-8 h-8 rounded-full" style={{ background: `linear-gradient(135deg, ${colors.primary}, ${colors.secondary})` }} />
                        </div>
                    </div>

                    {/* KPI cards */}
                    <div className="grid grid-cols-3 gap-3 mb-5">
                        {[
                            { label: 'Revenue (ETB)', value: '1,245,300', icon: Wallet,      delta: '+12%' },
                            { label: 'Customers',     value: '3,420',     icon: Users,        delta: '+8%'  },
                            { label: 'Orders',        value: '892',       icon: ShoppingCart, delta: '+23%' }
                        ].map((k) => {
                            const Icon = k.icon;
                            return (
                                <div key={k.label} className="p-3.5 rounded-xl border border-gray-100 bg-white shadow-sm hover:shadow-md transition-shadow">
                                    <div className="flex items-center justify-between mb-2.5">
                                        <div
                                            className="w-8 h-8 rounded-lg flex items-center justify-center"
                                            style={{ backgroundColor: `${colors.primary}15` }}
                                        >
                                            <Icon className="h-4 w-4" style={{ color: colors.primary }} />
                                        </div>
                                        <span className="text-[10px] font-bold flex items-center px-1.5 py-0.5 rounded-md" style={{ backgroundColor: `${colors.primary}10`, color: colors.primary }}>
                                            <TrendingUp className="h-2.5 w-2.5 mr-0.5" />
                                            {k.delta}
                                        </span>
                                    </div>
                                    <div className="text-lg font-black text-gray-900 leading-none">{k.value}</div>
                                    <div className="text-[10px] text-gray-500 mt-1">{k.label}</div>
                                </div>
                            );
                        })}
                    </div>

                    {/* Revenue chart */}
                    <div className="rounded-xl border border-gray-100 p-4 mb-4 bg-white">
                        <div className="flex items-center justify-between mb-3">
                            <div>
                                <div className="text-xs font-bold text-gray-900">Monthly Revenue · Birr</div>
                                <div className="text-[10px] text-gray-400">Last 7 months</div>
                            </div>
                            <div className="text-[10px] font-bold flex items-center gap-1" style={{ color: colors.primary }}>
                                <TrendingUp className="h-3 w-3" /> +24.5%
                            </div>
                        </div>
                        <div className="flex items-end justify-between gap-2 h-24">
                            {[42, 56, 48, 72, 64, 88, 96].map((h, i) => (
                                <div key={i} className="flex-1 flex flex-col items-center gap-1 group">
                                    <div
                                        className="w-full rounded-t-md transition-all duration-300 group-hover:opacity-100 opacity-80"
                                        style={{
                                            height: `${h}%`,
                                            background: `linear-gradient(180deg, ${colors.primary}, ${colors.primary}40)`
                                        }}
                                    />
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Recent activity */}
                    <div className="rounded-xl border border-gray-100 overflow-hidden bg-white">
                        {[
                            { t: 'CBE Bank · Telebirr reconciled', s: 'Accounting', amt: '+ETB 245,000', dot: colors.primary },
                            { t: 'New order #8821 · Sidama Coffee',  s: 'Coffee export', amt: 'ETB 84,300',  dot: colors.accent  },
                            { t: 'Habesha Garments · payslip run',   s: 'HRM',           amt: 'ETB 32,150',  dot: colors.primary }
                        ].map((r, i) => (
                            <div key={i} className={`flex items-center justify-between px-4 py-3 ${i !== 2 ? 'border-b border-gray-50' : ''}`}>
                                <div className="flex items-center gap-2.5">
                                    <span className="w-1.5 h-1.5 rounded-full" style={{ backgroundColor: r.dot }} />
                                    <span className="text-[11px] text-gray-700 font-medium">{r.t}</span>
                                </div>
                                <span className="text-[11px] font-bold text-gray-900">{r.amt}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>

        {/* Floating badge — top right */}
        <div
            className="absolute -top-5 -right-4 px-3.5 py-2 rounded-full text-[10px] font-bold text-white shadow-xl hidden md:flex items-center gap-1.5 z-20"
            style={{ background: `linear-gradient(135deg, ${colors.primary}, ${colors.secondary})` }}
        >
            <span className="w-1.5 h-1.5 rounded-full bg-white animate-ping" />
            Live Demo
        </div>

        {/* Floating glass card — AI Copilot */}
        <div className="absolute -bottom-6 left-6 md:left-auto md:-right-6 bg-white/95 backdrop-blur-md px-4 py-3 rounded-2xl border border-gray-200/60 shadow-2xl hidden sm:flex items-center gap-3 z-20 transform-gpu hover:scale-[1.04] transition-transform">
            <div
                className="w-9 h-9 rounded-xl flex items-center justify-center text-[10px] font-black text-white shadow-md"
                style={{ background: `linear-gradient(135deg, ${colors.primary}, ${colors.secondary})` }}
            >
                AI
            </div>
            <div className="flex flex-col">
                <span className="text-xs font-bold text-gray-900">Gadaa AI Copilot</span>
                <span className="text-[10px] text-gray-500">Reading invoices in Amharic · 99% accurate</span>
            </div>
        </div>

        {/* Floating stat card */}
        <div className="absolute -bottom-4 right-6 md:right-12 bg-white/95 backdrop-blur-md px-4 py-2.5 rounded-2xl border border-gray-200/60 shadow-2xl hidden md:flex flex-col z-20 transform-gpu hover:scale-[1.04] transition-transform max-w-[200px]">
            <span className="text-[9px] font-bold text-gray-400 uppercase tracking-widest">CBE + Telebirr</span>
            <span className="text-sm font-black text-gray-900">ETB 1.48M synced</span>
            <span className="text-[10px] font-bold flex items-center" style={{ color: colors.primary }}>
                ▲ 24.5% · auto-reconciled
            </span>
        </div>
    </div>
);

export default function Hero({ settings }: HeroProps) {
    const { t } = useTranslation();
    const sectionData = settings?.config_sections?.sections?.hero || {};
    const variant = sectionData.variant || 'hero1';
    const config = HERO_VARIANTS[variant as keyof typeof HERO_VARIANTS] || HERO_VARIANTS.hero1;

    const title = sectionData.title || 'Your entire Ethiopian business run on GadaaCloud';
    const subtitle = sectionData.subtitle || 'Accounting in Birr, CRM, POS, HRM & project management — purpose-built for Ethiopia with VAT/TIN compliance, Amharic support and CBE + Telebirr banking sync across all regions.';
    const primaryButtonText = sectionData.primary_button_text || 'Start Free Trial';
    const primaryButtonLink = sectionData.primary_button_link || route('register');
    const secondaryButtonText = sectionData.secondary_button_text || 'Request a Demo';
    const secondaryButtonLink = sectionData.secondary_button_link || route('login');
    const highlightText = sectionData.highlight_text || 'GadaaCloud';
    const heroImage = sectionData.image;
    const colors = settings?.config_sections?.colors || { primary: '#078930', secondary: '#054a2b', accent: '#fcdd09' };

    const renderTitle = () => {
        if (highlightText && title?.includes(highlightText)) {
            const parts = title.split(highlightText);
            return (
                <>
                    {parts[0]}
                    <span
                        className="px-2 -mx-2 bg-clip-text text-transparent"
                        style={{ backgroundImage: `linear-gradient(135deg, ${colors.primary}, ${colors.secondary})` }}
                    >
                        {highlightText}
                    </span>
                    {parts[1]}
                </>
            );
        }
        return title;
    };

    const renderButtons = () => (
        <div className="flex flex-col sm:flex-row gap-3 md:gap-4">
            <button
                onClick={() => window.location.href = primaryButtonLink}
                className="group inline-flex items-center justify-center text-white px-7 py-3.5 rounded-xl text-sm font-bold shadow-lg transition-all duration-300 transform hover:-translate-y-0.5"
                style={{
                    background: `linear-gradient(135deg, ${colors.primary}, ${colors.secondary})`,
                    boxShadow: `0 10px 25px -8px ${colors.primary}80`
                }}
            >
                {primaryButtonText}
                <ArrowRight className="ml-2 h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" />
            </button>
            <button
                onClick={() => window.location.href = secondaryButtonLink}
                className="inline-flex items-center justify-center border-2 border-gray-200 text-gray-800 px-7 py-3.5 rounded-xl text-sm font-bold hover:bg-gray-50 hover:border-gray-300 transition-all duration-300"
            >
                {secondaryButtonText}
            </button>
        </div>
    );

    const renderBackgroundImage = () => {
        if (config.layout !== 'background') return null;
        return (
            <>
                {heroImage && (
                    <div className="absolute inset-0">
                        <img src={getImagePath(heroImage)} alt="Hero Background" className="w-full h-full object-cover" />
                        <div className="absolute inset-0 bg-gradient-to-br from-black/60 via-black/40 to-black/60" />
                    </div>
                )}
                {!heroImage && (
                    <div className="absolute inset-0 bg-gradient-to-br from-gray-900 via-gray-800 to-black" />
                )}
                <div className="absolute inset-0">
                    <div className="absolute top-10 left-10 w-32 h-32 bg-white/10 rounded-full blur-3xl animate-pulse" />
                    <div className="absolute bottom-20 right-20 w-48 h-48 bg-white/5 rounded-full blur-3xl animate-pulse delay-1000" />
                </div>
            </>
        );
    };

    const renderImage = () => {
        if (!config.showImage || config.layout === 'background') return null;
        return (
            <div className={`relative ${config.layout === 'split' || config.layout === 'right-split' ? '' : 'mt-12'} flex items-center justify-center`}>
                {heroImage ? (
                    <img src={getImagePath(heroImage)} alt="Hero" className="w-full h-auto rounded-2xl shadow-2xl" />
                ) : (
                    <HeroDashboard colors={colors} />
                )}
            </div>
        );
    };

    const renderContent = () => {
        const isCentered = config.layout === 'background' || config.layout === 'minimal';
        return (
            <div className={isCentered ? 'w-full text-center' : ''}>
                {/* Status pill */}
                <div
                    className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold mb-6"
                    style={{ backgroundColor: `${colors.primary}10`, color: colors.primary, border: `1px solid ${colors.primary}20` }}
                >
                    <span className="relative flex h-2 w-2">
                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" style={{ backgroundColor: colors.accent }} />
                        <span className="relative inline-flex rounded-full h-2 w-2" style={{ backgroundColor: colors.accent }} />
                    </span>
                    Trusted by 5,000+ Ethiopian enterprises
                </div>

                {/* Headline */}
                <h1 className={`font-bold text-gray-900 leading-tight tracking-tight ${isCentered
                    ? 'text-4xl md:text-5xl lg:text-6xl mb-6'
                    : 'text-4xl md:text-5xl lg:text-[3.4rem] mb-5'}`}>
                    {renderTitle()}
                </h1>

                {/* Subtitle */}
                <p className={`text-gray-600 leading-relaxed mb-8 ${isCentered
                    ? 'text-base md:text-lg max-w-3xl mx-auto'
                    : 'text-base md:text-lg max-w-xl'}`}>
                    {subtitle}
                </p>

                {/* CTAs */}
                {renderButtons()}

                {/* Trust badges */}
                {!isCentered && (
                    <div className="mt-7 flex flex-wrap gap-x-4 gap-y-2.5 max-w-xl">
                        {TRUST_BADGES.map((b) => (
                            <div key={b} className="flex items-center gap-1.5 text-xs font-medium text-gray-600">
                                <Check
                                    className="h-3.5 w-3.5 flex-shrink-0"
                                    style={{ stroke: colors.primary, strokeWidth: 3 }}
                                />
                                {b}
                            </div>
                        ))}
                    </div>
                )}

                {isCentered && (
                    <div className="mt-8 flex flex-wrap gap-x-5 gap-y-2 justify-center">
                        {TRUST_BADGES.map((b) => (
                            <div key={b} className="flex items-center gap-1.5 text-xs font-medium opacity-90 text-white">
                                <Check className="h-3.5 w-3.5" style={{ stroke: colors.accent, strokeWidth: 3 }} />
                                {b}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        );
    };

    return (
        <section className={config.section}>
            <style dangerouslySetInnerHTML={{ __html: `
                @keyframes heroFloat {
                    0%, 100% { transform: translateY(0px); }
                    50% { transform: translateY(-10px); }
                }
                @keyframes spinSlow {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
                .hero-float { animation: heroFloat 6s ease-in-out infinite; }
                .grid-bg {
                    background-image: linear-gradient(to right, rgba(128,128,128,0.04) 1px, transparent 1px), linear-gradient(to bottom, rgba(128,128,128,0.04) 1px, transparent 1px);
                    background-size: 28px 28px;
                    mask-image: radial-gradient(ellipse at center, black 0%, transparent 80%);
                }
            ` }} />

            {renderBackgroundImage()}

            {config.layout !== 'background' && (
                <>
                    <div className="absolute inset-0 grid-bg pointer-events-none" />
                    <div
                        className="absolute top-0 left-0 w-72 h-72 rounded-full blur-[120px] opacity-20 pointer-events-none hero-float"
                        style={{ backgroundColor: colors.primary }}
                    />
                    <div
                        className="absolute bottom-0 right-0 w-96 h-96 rounded-full blur-[140px] opacity-10 pointer-events-none hero-float"
                        style={{ backgroundColor: colors.accent, animationDelay: '2s' }}
                    />
                </>
            )}

            <div className={config.container}>
                {config.layout === 'split' || config.layout === 'right-split' ? (
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                        {config.layout === 'right-split' ? (
                            <>
                                {renderImage()}
                                {renderContent()}
                            </>
                        ) : (
                            <>
                                {renderContent()}
                                {renderImage()}
                            </>
                        )}
                    </div>
                ) : config.layout === 'background' ? (
                    <div className="relative">
                        {renderContent()}
                        <div className="absolute inset-0 pointer-events-none">
                            <div className="absolute top-1/4 left-1/4 w-2 h-2 bg-white rounded-full animate-ping" />
                            <div className="absolute top-3/4 right-1/4 w-1 h-1 bg-white/70 rounded-full animate-ping delay-700" />
                            <div className="absolute top-1/2 right-1/3 w-1.5 h-1.5 bg-white/50 rounded-full animate-ping delay-1000" />
                        </div>
                    </div>
                ) : (
                    <>
                        {renderContent()}
                        {renderImage()}
                    </>
                )}
            </div>
        </section>
    );
}