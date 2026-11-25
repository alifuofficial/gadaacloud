import React, { useState, useEffect, useMemo } from 'react';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ArrowRight, Menu, X, ShieldCheck, CloudCog } from 'lucide-react';
import { getAdminSetting, getImagePath } from '@/utils/helpers';

interface SiteHeaderProps {
    settings?: any;
    activePage?: 'home' | 'pricing';
}

export default function SiteHeader({ settings = {}, activePage = 'home' }: SiteHeaderProps) {
    const { t, i18n } = useTranslation();

    const companyName = settings?.company_name || 'GadaaCloud';
    const isAuthenticated = !!settings?.is_authenticated;
    const enableRegistration = settings?.enable_registration !== false;
    const customPages = settings?.custom_pages || [];

    const themeMode = getAdminSetting('theme_mode') || 'light';
    const logoKey = themeMode === 'dark' ? 'logo_light' : 'logo_dark';
    const logoPath = getAdminSetting(logoKey);
    const logoUrl = logoPath ? getImagePath(logoPath) : null;

    const [mobileOpen, setMobileOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);
    const [currentLang, setCurrentLang] = useState<'en' | 'am' | 'om'>('en');

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 12);
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    const handleLanguageChange = (lang: 'en' | 'am' | 'om') => {
        setCurrentLang(lang);
        if (i18n && typeof i18n.changeLanguage === 'function') {
            i18n.changeLanguage(lang);
        }
    };

    const grad = 'linear-gradient(135deg, #00A76F, #059669)';

    // Minimized, crisp navigation links (3 core items + optional custom pages)
    const navItems = useMemo(() => {
        const homeBase = activePage === 'home' ? '' : route('landing.page');
        const core = [
            { text: t('Platform'), href: `${homeBase}#pillars`, isAnchor: activePage === 'home' },
            { text: t('Solutions'), href: `${homeBase}#industries`, isAnchor: activePage === 'home' },
            { text: t('Pricing'), href: route('pricing.page'), isAnchor: false, isActive: activePage === 'pricing' },
        ];
        const custom = (customPages || []).slice(0, 2).map((p: any) => ({
            text: p.title,
            href: route('custom-page.show', p.slug),
            isAnchor: false,
            isActive: false,
        }));
        return [...core, ...custom];
    }, [activePage, customPages, t]);

    const renderNavLinks = (mobile = false) => (
        <>
            {(navItems || []).map((item: any) => {
                const cls = mobile
                    ? `block px-4 py-2.5 text-sm font-semibold rounded-xl transition ${
                          item.isActive
                              ? 'text-[#00A76F] bg-emerald-50/70'
                              : 'text-slate-700 hover:text-[#00A76F] hover:bg-emerald-50/50'
                      }`
                    : `px-3.5 py-1.5 rounded-lg text-xs lg:text-sm font-semibold transition-colors whitespace-nowrap ${
                          item.isActive
                              ? 'text-[#00A76F] bg-emerald-50/80'
                              : 'text-slate-600 hover:text-[#00A76F] hover:bg-emerald-50/40'
                      }`;

                if (item.isAnchor) {
                    return (
                        <a key={item.text} href={item.href} onClick={() => mobile && setMobileOpen(false)} className={cls}>
                            {item.text}
                        </a>
                    );
                }

                return (
                    <Link key={item.text} href={item.href} onClick={() => mobile && setMobileOpen(false)} className={cls}>
                        {item.text}
                    </Link>
                );
            })}
        </>
    );

    const renderCTAs = (mobile = false) => {
        if (isAuthenticated) {
            return (
                <Link
                    href={route('dashboard')}
                    className={`${mobile ? 'w-full' : ''} inline-flex items-center justify-center px-4 py-2 rounded-xl text-xs sm:text-sm font-bold text-white shadow-md shadow-emerald-500/20 transition hover:shadow-lg hover:scale-[1.02]`}
                    style={{ background: grad }}
                >
                    {t('Dashboard')} <ArrowRight className="ml-1.5 h-3.5 w-3.5" />
                </Link>
            );
        }
        return (
            <div className={`flex ${mobile ? 'flex-col w-full gap-2' : 'items-center gap-2'}`}>
                <Link
                    href={route('login')}
                    className={`${mobile ? 'w-full text-center border border-slate-200 rounded-xl' : ''} inline-flex items-center justify-center px-3.5 py-2 text-xs sm:text-sm font-semibold text-slate-700 hover:text-[#00A76F] transition-colors`}
                >
                    {t('Log in')}
                </Link>
                {enableRegistration && (
                    <Link
                        href={route('register')}
                        className={`${mobile ? 'w-full' : ''} inline-flex items-center justify-center px-4 py-2 rounded-xl text-xs sm:text-sm font-bold text-white shadow-md shadow-emerald-500/20 transition hover:shadow-lg hover:scale-[1.02] whitespace-nowrap`}
                        style={{ background: grad }}
                    >
                        {t('Start Free Trial')} <ArrowRight className="ml-1.5 h-3.5 w-3.5" />
                    </Link>
                )}
            </div>
        );
    };

    return (
        <>
            {/* Compact Cloud & Security Telemetry Top Strip */}
            <div className="bg-[#031B13] text-emerald-200 text-[11px] font-semibold py-1.5 px-4 border-b border-emerald-900/60 hidden sm:block">
                <div className="max-w-7xl mx-auto flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <span className="flex items-center gap-1.5 text-[#34D399]">
                            <CloudCog className="h-3.5 w-3.5 text-[#34D399]" />
                            <span className="w-1.5 h-1.5 rounded-full bg-[#34D399] animate-pulse" />
                            99.9% Secured Cloud
                        </span>
                        <span className="text-emerald-800">|</span>
                        <span className="flex items-center gap-1.5 text-emerald-200/90">
                            <ShieldCheck className="h-3.5 w-3.5 text-[#34D399]" />
                            AES-256 · MoR EIMS 1142 Ready
                        </span>
                    </div>

                    <div className="flex items-center gap-2 text-[10px]">
                        <div className="flex items-center gap-1 bg-emerald-950/80 px-2 py-0.5 rounded-md border border-emerald-800/60">
                            <button
                                onClick={() => handleLanguageChange('en')}
                                className={`px-1.5 py-0.2 rounded transition ${currentLang === 'en' ? 'bg-[#00A76F] text-white font-bold' : 'text-emerald-400 hover:text-white'}`}
                            >
                                EN
                            </button>
                            <button
                                onClick={() => handleLanguageChange('am')}
                                className={`px-1.5 py-0.2 rounded transition ${currentLang === 'am' ? 'bg-[#00A76F] text-white font-bold' : 'text-emerald-400 hover:text-white'}`}
                            >
                                አማርኛ
                            </button>
                            <button
                                onClick={() => handleLanguageChange('om')}
                                className={`px-1.5 py-0.2 rounded transition ${currentLang === 'om' ? 'bg-[#00A76F] text-white font-bold' : 'text-emerald-400 hover:text-white'}`}
                            >
                                OM
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {/* Minimalist Sticky Navbar */}
            <header
                className={`sticky top-0 inset-x-0 z-50 transition-all duration-300 ${
                    scrolled
                        ? 'bg-white/95 backdrop-blur-md border-b border-emerald-100 shadow-sm'
                        : 'bg-white/80 backdrop-blur-sm border-b border-slate-100'
                }`}
            >
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between h-16">
                        <Link href={route('landing.page')} className="flex items-center gap-2.5 group">
                            {logoUrl ? (
                                <img src={logoUrl} alt={companyName} className="h-8 w-auto" />
                            ) : (
                                <div className="flex items-center gap-2">
                                    <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-[#00A76F] to-[#059669] flex items-center justify-center text-white font-black text-lg shadow-md shadow-emerald-500/20 group-hover:scale-105 transition-transform">
                                        G
                                    </div>
                                    <span className="font-extrabold text-lg tracking-tight text-slate-900">
                                        Gadaa<span className="text-[#00A76F]">Cloud</span>{' '}
                                        <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-[#00A76F] border border-emerald-200 uppercase tracking-widest ml-0.5">
                                            AI ERP
                                        </span>
                                    </span>
                                </div>
                            )}
                        </Link>

                        <nav className="hidden md:flex items-center gap-1">{renderNavLinks()}</nav>

                        <div className="hidden sm:flex items-center gap-2">{renderCTAs()}</div>

                        <button
                            onClick={() => setMobileOpen(!mobileOpen)}
                            aria-label="Toggle Menu"
                            className="md:hidden p-2 rounded-xl text-slate-700 hover:bg-emerald-50 transition"
                        >
                            {mobileOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                        </button>
                    </div>
                </div>

                {mobileOpen && (
                    <div className="md:hidden bg-white border-b border-emerald-100 shadow-xl animate-in slide-in-from-top duration-200">
                        <div className="px-4 py-3 space-y-1.5">
                            {renderNavLinks(true)}
                            <div className="pt-2.5 border-t border-slate-100 mt-2">{renderCTAs(true)}</div>
                        </div>
                    </div>
                )}
            </header>
        </>
    );
}
