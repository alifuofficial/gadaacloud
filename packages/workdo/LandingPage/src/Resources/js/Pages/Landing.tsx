import { Head, Link, usePage, router } from '@inertiajs/react';
import { useMemo, useState, useEffect } from 'react';
import { toast } from 'sonner';
import { useTranslation } from 'react-i18next';
import {
    ArrowRight, Check, Menu, X, ChevronDown, Star, Quote,
    Brain, Cloud, ShieldCheck, Sparkles, Lock, Server, Cpu,
    Wallet, Users, ShoppingCart, TrendingUp, Calculator, Home as HomeIcon,
    Boxes, Store, UserCheck, FolderOpen, Receipt,
    Coffee, ShoppingBag, Building2,
    Mail, Phone, MapPin, Globe, Zap, Bot, Send,
    Fingerprint, KeyRound, FileCheck, Activity, Waypoints,
    CheckCircle2, QrCode, WifiOff, Clock
} from 'lucide-react';
import { getAdminSetting, getImagePath } from '@/utils/helpers';
import CookieConsent from '@/components/cookie-consent';

// Subcomponents
import InteractiveStudio from './components/InteractiveStudio';
import WorkflowEngine from './components/WorkflowEngine';
import BentoPillars from './components/BentoPillars';
import IndustrySolutions from './components/IndustrySolutions';
import RoiCalculator from './components/RoiCalculator';

interface LandingProps {
    settings?: any;
}

const THEMES = {
    primary: '#00A76F',   // Modern vibrant light green / emerald
    secondary: '#059669', // Rich medium emerald
    accent: '#34D399',    // Light mint accent
    cloud: '#0EA5E9',     // Sky blue accent
    ai: '#00A76F',        // Unified light green AI
    secure: '#059669',    // Emerald security
    dark: '#031B13'       // Obsidian dark emerald
};

export default function Landing({ settings }: LandingProps) {
    const { t, i18n } = useTranslation();
    const { adminAllSetting } = usePage().props as any;

    const c = settings?.config_sections?.colors || THEMES;
    const colors = { ...THEMES, ...c, primary: '#00A76F', secondary: '#059669', accent: '#34D399', dark: '#031B13' };

    const companyName = settings?.company_name || 'GadaaCloud';
    const isAuthenticated = settings?.is_authenticated;
    const enableRegistration = settings?.enable_registration !== false;
    const customPages = settings?.custom_pages || [];

    const themeMode = getAdminSetting('theme_mode') || 'light';
    const logoKey = themeMode === 'dark' ? 'logo_light' : 'logo_dark';
    const logoPath = getAdminSetting(logoKey);
    const logoUrl = logoPath ? getImagePath(logoPath) : null;

    const favicon = getAdminSetting('favicon');
    const faviconUrl = favicon ? getImagePath(favicon) : null;

    const [mobileOpen, setMobileOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);
    const [openFaq, setOpenFq] = useState<number>(0);
    const [currentLang, setCurrentLang] = useState<'en' | 'am' | 'om'>('en');

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 16);
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    // Change language
    const handleLanguageChange = (lang: 'en' | 'am' | 'om') => {
        setCurrentLang(lang);
        if (i18n && typeof i18n.changeLanguage === 'function') {
            i18n.changeLanguage(lang);
        }
    };

    // -------- DATA --------
    const NAV_LINKS = useMemo(() => {
        const navSettings = settings?.config_sections?.sections?.header?.navigation_items || [];
        const customItems = (customPages || []).map((p: any) => ({ text: p.title, href: `/page/${p.slug}` }));
        
        const coreLinks = [
            { text: t('Features & EIMS'), href: '#pillars' },
            { text: t('Workflows'), href: '#workflows' },
            { text: t('AI Copilot'), href: '#ai-copilot' },
            { text: t('Industries'), href: '#industries' },
            { text: t('ROI Calculator'), href: '#roi-calculator' },
        ];

        return [...coreLinks, ...navSettings, ...customItems];
    }, [settings, customPages, t]);

    const STATS = [
        { value: 'ETB 14.8B+', label: 'Annual Business Volume Processed' },
        { value: '99.9%', label: 'Cloud Uptime SLA Across 11 Regions' },
        { value: '100%', label: 'MoR Directive 1142 EIMS Compliance' },
        { value: '30 Sec', label: 'CBE & Telebirr AI Reconciliation' }
    ];

    const TRUST_MARKETS = [
        'Addis Ababa Commerce Hub',
        'Hawassa Industrial Park',
        'Bole Lemi Free Zone',
        'Dire Dawa Logistics Corridor',
        'Sidama Coffee Producers Union',
        'Merkato Traders Association',
        'Adama Industrial Depot',
        'Oromia Agribusiness Federation'
    ];

    const TESTIMONIALS = [
        { 
            quote: 'GadaaCloud\'s MoR EIMS module generated our cryptographic QR invoices with zero errors from day one. Invoicing in Birr while tracking coffee exports to Hamburg in USD is completely seamless.', 
            author: 'Dawit Tesfaye', 
            role: 'Managing Director', 
            company: 'Bole Coffee Exporters PLC' 
        },
        { 
            quote: 'During internet cuts in Merkato, our POS registers kept ringing up customers with offline receipts. When the connection returned, all sales synced without a single Birr discrepancy.', 
            author: 'Hiwot Mekonnen', 
            role: 'Head of Retail Operations', 
            company: 'Merkato Supermarket Group' 
        },
        { 
            quote: 'Birr payroll under Ethiopian labor law with income tax brackets (0%-35%) and pension deductions saves our finance team three full days every month-end.', 
            author: 'Yonas Kebede', 
            role: 'Finance & HR Director', 
            company: 'Habesha Apparel & Manufacturing' 
        },
        { 
            quote: 'The Amharic AI Copilot is remarkable. Our accountants ask "በጥቅምት ወር ስንት ቫት ተቀበልን?" and get an instant audit-ready breakdown with CBE bank reconciliation.', 
            author: 'Selamawit Bekele', 
            role: 'Chief Financial Officer', 
            company: 'Ethio Logistics & Freight' 
        }
    ];

    const FAQS = [
        { 
            q: 'How does GadaaCloud comply with Ethiopian MoR EIMS Directive No. 1142/2026?', 
            a: 'GadaaCloud integrates directly with the Ethiopian Ministry of Revenues Electronic Invoicing Management System (EIMS). Every tax invoice generates an ECDSA SHA-256 cryptographic QR code, unique IRN hash, and transfers data in real-time. Offline fiscal queueing ensures invoices are securely buffered and pushed if network connectivity drops.' 
        },
        { 
            q: 'How does the Dual Calendar engine work for Ethiopian businesses?', 
            a: 'GadaaCloud natively supports both the Ethiopian Ge\'ez (Kenat) calendar and the Gregorian calendar. You can schedule accounting periods, VAT filing deadlines (20th of each Ge\'ez month), and payroll cycles in either calendar with automatic synchronous conversion.' 
        },
        { 
            q: 'Can the POS and warehouse systems function during internet interruptions?', 
            a: 'Yes. GadaaCloud POS and field inventory scanners are built offline-first. They continue creating receipts, deducting local stock, and storing transactions in an encrypted local database. The moment internet connectivity is restored, all data synchronizes to the cloud automatically.' 
        },
        { 
            q: 'How does automated Commercial Bank of Ethiopia (CBE) and Telebirr reconciliation work?', 
            a: 'GadaaCloud supports automated statement imports for CBE, Awash, Dashen, and Co-op banks, alongside live Telebirr merchant webhooks. The AI engine matches transaction reference numbers and amounts against unpaid invoices, clearing accounts receivable in seconds.' 
        },
        { 
            q: 'Can we migrate our existing data from Excel, QuickBooks, or legacy ERPs?', 
            a: 'Yes. GadaaCloud includes the ERP Data Migration Studio (ERPMigrate) allowing one-click imports of your chart of accounts, customers, vendors, opening stock balances, and historical invoices with automated field mapping and duplicate validation.' 
        },
        { 
            q: 'How is tenant data security and privacy enforced?', 
            a: 'Every company workspace is strictly isolated through row-level multi-tenant architecture scoped by creatorId. All data is encrypted at rest using AES-256 and in transit via TLS 1.3, backed by nightly redundant backups and immutable audit trail logging.' 
        }
    ];

    const TRUST_BADGES = ['MoR Directive 1142', 'CBE & Telebirr Ready', 'Ge\'ez Dual Calendar', 'Amharic AI Copilot', '11 Regions Covered'];

    // -------- COLORS HELPERS --------
    const grad = (a: string = colors.primary, b: string = colors.secondary) => `linear-gradient(135deg, ${a}, ${b})`;

    // -------- CMS CONFIG OVERRIDES --------
    const heroSec = settings?.config_sections?.sections?.hero || {};
    const heroTitle = heroSec.title || 'The modern AI cloud ERP built for Ethiopian businesses.';
    const heroSubtitle = heroSec.subtitle || 'Accounting in Birr with MoR EIMS Directive 1142 e-invoicing, Ge\'ez dual calendar, POS, HRM, payroll and coffee export management — unified on a secure cloud platform running across all 11 regions, supercharged by an AI copilot that speaks Amharic.';
    
    // -------- ACTIONS --------
    const primaryHref = isAuthenticated ? route('dashboard') : route('register');
    const primaryLabel = isAuthenticated ? t('Dashboard') : (enableRegistration ? t('Start Free Trial') : t('Sign In'));
    const primaryBtnText = heroSec.primary_button_text || primaryLabel;
    const primaryBtnLink = heroSec.primary_button_link || primaryHref;
    const secondaryBtnText = heroSec.secondary_button_text || t('Explore Live Demo');
    const secondaryBtnLink = heroSec.secondary_button_link || '#studio';

    const go = (link: string) => {
        if (link.startsWith('#')) {
            const el = document.querySelector(link);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth' });
                return;
            }
        }
        window.location.href = link;
    };

    // -------- RENDER HELPERS --------
    const renderNav = (mobile = false) => (
        <>
            {(NAV_LINKS || []).map((item: any) => {
                const href = item.href?.startsWith('/page/')
                    ? route('custom-page.show', item.href.replace('/page/', ''))
                    : item.href;
                const cls = mobile
                    ? 'block px-4 py-3 text-base font-semibold text-slate-700 hover:text-[#00A76F] hover:bg-emerald-50/50 rounded-xl transition'
                    : 'px-3 py-2 text-xs lg:text-sm font-semibold text-slate-700 hover:text-[#00A76F] transition-colors whitespace-nowrap';
                
                if (href.startsWith('#')) {
                    return (
                        <a key={item.text} href={href} className={cls}>
                            {item.text}
                        </a>
                    );
                }

                return item.target === '_blank'
                    ? <a key={item.text} href={href} target="_blank" rel="noopener noreferrer" className={cls}>{item.text}</a>
                    : <Link key={item.text} href={href} className={cls}>{item.text}</Link>;
            })}
        </>
    );

    const renderCTAs = (mobile = false) => {
        if (isAuthenticated) {
            return (
                <Link href={route('dashboard')} className={`${mobile ? 'w-full' : ''} inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-xs sm:text-sm font-bold text-white shadow-lg shadow-emerald-500/20 transition hover:shadow-xl hover:scale-[1.02]`} style={{ background: grad() }}>
                    {t('Dashboard')} <ArrowRight className="ml-1.5 h-4 w-4" />
                </Link>
            );
        }
        return (
            <div className={`flex ${mobile ? 'flex-col w-full space-y-2' : 'items-center space-x-2'}`}>
                <Link href={route('login')} className={`${mobile ? 'w-full' : ''} inline-flex items-center justify-center px-3.5 py-2 text-xs sm:text-sm font-semibold text-slate-700 hover:text-[#00A76F] transition-colors`}>
                    {t('Log in')}
                </Link>
                {enableRegistration && (
                    <Link href={route('register')} className={`${mobile ? 'w-full' : ''} inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold text-white shadow-md shadow-emerald-500/20 transition hover:shadow-lg hover:scale-[1.02] whitespace-nowrap`} style={{ background: grad() }}>
                        {t('Start Free Trial')} <ArrowRight className="ml-1.5 h-3.5 w-3.5" />
                    </Link>
                )}
            </div>
        );
    };

    return (
        <div className="min-h-screen bg-[#FDFDFD] text-slate-900 font-sans selection:bg-[#00A76F] selection:text-white relative overflow-x-hidden">
            <Head title={`${companyName} — Modern AI Cloud ERP for Ethiopian Enterprise`}>
                {faviconUrl && <link rel="icon" type="image/x-icon" href={faviconUrl} />}
            </Head>

            <style>{`
                @keyframes floaty { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
                @keyframes marquee { 0%{transform:translateX(0)} 100%{transform:translateX(-50%)} }
                .floaty { animation: floaty 6s ease-in-out infinite; }
                .grid-bg {
                    background-image: linear-gradient(to right, rgba(0, 167, 111, 0.05) 1px, transparent 1px), linear-gradient(to bottom, rgba(0, 167, 111, 0.05) 1px, transparent 1px);
                    background-size: 36px 36px;
                }
                .marquee-track { display:flex; width:max-content; animation: marquee 35s linear infinite; }
                .marquee-track:hover { animation-play-state: paused; }
            `}</style>

            {/* ============ SYSTEM TELEMETRY TOP BAR ============ */}
            <div className="bg-[#031B13] text-emerald-200 text-[11px] font-semibold py-1.5 px-4 border-b border-emerald-900/60 hidden sm:block">
                <div className="max-w-7xl mx-auto flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <span className="flex items-center gap-1.5 text-[#34D399]">
                            <span className="w-2 h-2 rounded-full bg-[#34D399] animate-pulse" />
                            MoR EIMS Directive No. 1142/2026 Ready
                        </span>
                        <span className="text-emerald-700">|</span>
                        <span>CBE & Telebirr Direct Bank Settlement</span>
                        <span className="text-emerald-700">|</span>
                        <span>Ge'ez Dual Calendar</span>
                    </div>

                    <div className="flex items-center gap-4 text-[10px]">
                        <span className="text-emerald-400 font-mono">SYSTEM: 99.9% UPTIME</span>
                        {/* Language Switcher */}
                        <div className="flex items-center gap-1.5 bg-emerald-950/80 px-2 py-0.5 rounded-md border border-emerald-800/60">
                            <button
                                onClick={() => handleLanguageChange('en')}
                                className={`px-1.5 py-0.2 rounded transition ${currentLang === 'en' ? 'bg-[#00A76F] text-white font-bold' : 'text-emerald-400 hover:text-white'}`}
                            >
                                English
                            </button>
                            <span className="text-emerald-700">·</span>
                            <button
                                onClick={() => handleLanguageChange('am')}
                                className={`px-1.5 py-0.2 rounded transition ${currentLang === 'am' ? 'bg-[#00A76F] text-white font-bold' : 'text-emerald-400 hover:text-white'}`}
                            >
                                አማርኛ
                            </button>
                            <span className="text-emerald-700">·</span>
                            <button
                                onClick={() => handleLanguageChange('om')}
                                className={`px-1.5 py-0.2 rounded transition ${currentLang === 'om' ? 'bg-[#00A76F] text-white font-bold' : 'text-emerald-400 hover:text-white'}`}
                            >
                                Oromoo
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {/* ============ HEADER ============ */}
            <header className={`sticky top-0 inset-x-0 z-50 transition-all duration-300 ${scrolled ? 'bg-white/90 backdrop-blur-md border-b border-emerald-100 shadow-sm' : 'bg-white/75 backdrop-blur-sm border-b border-slate-100'}`}>
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between h-20">
                        {/* Logo */}
                        <Link href={route('landing.page')} className="flex items-center gap-2.5 group">
                            {logoUrl ? (
                                <img src={logoUrl} alt={companyName} className="h-9 w-auto" />
                            ) : (
                                <div className="flex items-center gap-2">
                                    <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-[#00A76F] to-[#059669] flex items-center justify-center text-white font-black text-xl shadow-md shadow-emerald-500/20 group-hover:scale-105 transition-transform">
                                        G
                                    </div>
                                    <span className="font-extrabold text-xl tracking-tight text-slate-900">
                                        Gadaa<span className="text-[#00A76F]">Cloud</span> <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-[#00A76F] border border-emerald-200 uppercase tracking-widest ml-1">AI ERP</span>
                                    </span>
                                </div>
                            )}
                        </Link>

                        {/* Navigation Links */}
                        <nav className="hidden lg:flex items-center gap-1">{renderNav()}</nav>

                        {/* Header CTAs */}
                        <div className="hidden sm:flex items-center gap-2">
                            <Link href={route('pricing.page')} className="px-3.5 py-2 text-xs sm:text-sm font-semibold text-slate-700 hover:text-[#00A76F] transition-colors">{t('Pricing')}</Link>
                            {renderCTAs()}
                        </div>

                        {/* Mobile Hamburger */}
                        <button onClick={() => setMobileOpen(!mobileOpen)} className="lg:hidden p-2 rounded-xl text-slate-700 hover:bg-emerald-50 transition">
                            {mobileOpen ? <X className="h-6 w-6"/> : <Menu className="h-6 w-6"/>}
                        </button>
                    </div>
                </div>

                {/* Mobile Dropdown */}
                {mobileOpen && (
                    <div className="lg:hidden bg-white border-b border-emerald-100 shadow-xl animate-in slide-in-from-top duration-200">
                        <div className="px-5 py-4 space-y-2">
                            {renderNav(true)}
                            <Link href={route('pricing.page')} className="block px-4 py-3 text-base font-semibold text-slate-700 hover:text-[#00A76F] hover:bg-emerald-50/50 rounded-xl transition">{t('Pricing')}</Link>
                            <div className="pt-3 border-t border-slate-100 mt-2">{renderCTAs(true)}</div>
                        </div>
                    </div>
                )}
            </header>

            {/* ============ HERO SECTION WITH INTERACTIVE STUDIO ============ */}
            <section id="studio" className="relative pt-10 pb-20 md:pt-16 md:pb-28 overflow-hidden bg-gradient-to-b from-emerald-50/70 via-emerald-50/20 to-white">
                {/* Ambient glows */}
                <div className="absolute top-0 right-1/4 w-[500px] h-[500px] bg-[#00A76F]/10 rounded-full blur-3xl pointer-events-none -z-10" />
                <div className="absolute top-1/3 left-0 w-[400px] h-[400px] bg-emerald-400/10 rounded-full blur-3xl pointer-events-none -z-10" />
                <div className="absolute inset-0 grid-bg opacity-60 pointer-events-none -z-10" />

                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                    {/* Left Column - Headline & Copy */}
                    <div className="lg:col-span-5 space-y-6 text-center lg:text-left">
                        <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-100/70 border border-emerald-200 text-xs font-bold text-[#00A76F] shadow-2xs">
                            <Sparkles className="h-4 w-4 text-[#00A76F]" />
                            <span>#1 AI Cloud ERP for Ethiopian Enterprise</span>
                        </div>

                        <h1 className="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 tracking-tight leading-[1.15]">
                            Run your entire enterprise in Birr. Compliant, automated, and powered by AI.
                        </h1>

                        <p className="text-sm sm:text-base text-slate-600 max-w-xl mx-auto lg:mx-0 leading-relaxed font-normal">
                            {heroSubtitle}
                        </p>

                        <div className="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-3 pt-2">
                            <button onClick={() => go(primaryBtnLink)} className="w-full sm:w-auto px-7 py-3.5 rounded-xl text-sm font-bold text-white shadow-xl shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:scale-[1.02] transition-all duration-200 flex items-center justify-center gap-2" style={{ background: grad() }}>
                                {primaryBtnText} <ArrowRight className="h-4 w-4" />
                            </button>
                            <button onClick={() => go(secondaryBtnLink)} className="w-full sm:w-auto px-6 py-3.5 rounded-xl text-sm font-bold bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 transition-all duration-200 shadow-2xs">
                                {secondaryBtnText}
                            </button>
                        </div>

                        {/* Trust highlights */}
                        <div className="flex flex-wrap items-center justify-center lg:justify-start gap-x-5 gap-y-2 pt-2 text-xs font-semibold text-slate-600">
                            {TRUST_BADGES.map((item) => (
                                <div key={item} className="flex items-center gap-1.5">
                                    <div className="w-4 h-4 rounded-full bg-emerald-100 flex items-center justify-center text-[#00A76F]">
                                        <Check className="h-3 w-3 stroke-[3]" />
                                    </div>
                                    <span>{item}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Right Column - Interactive ERP Simulation Studio */}
                    <div className="lg:col-span-7 relative">
                        <div className="relative">
                            {/* Glowing backdrop shadow */}
                            <div className="absolute inset-0 bg-gradient-to-tr from-[#00A76F]/20 to-emerald-400/20 rounded-3xl blur-2xl transform rotate-1 -z-10" />
                            
                            <InteractiveStudio colors={colors} grad={grad} />
                        </div>
                    </div>
                </div>
            </section>

            {/* ============ STATS COUNTER BAND ============ */}
            <section className="bg-[#031B13] text-white py-14 relative overflow-hidden border-y border-emerald-900/60">
                <div className="absolute inset-0 bg-[radial-gradient(#00A76F_1px,transparent_1px)] [background-size:24px_24px] opacity-10 pointer-events-none" />
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 space-y-10">
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                        {STATS.map((s) => (
                            <div key={s.label} className="space-y-1">
                                <div className="text-3xl sm:text-4xl font-black text-[#34D399] tracking-tight">{s.value}</div>
                                <div className="text-xs sm:text-sm font-medium text-emerald-200/80">{s.label}</div>
                            </div>
                        ))}
                    </div>

                    {/* Ethiopian Economic Hubs Marquee */}
                    <div className="pt-4 border-t border-emerald-900/40 overflow-hidden">
                        <div className="text-[10px] uppercase font-bold text-emerald-400/80 tracking-widest text-center mb-3">
                            Deployed Across Ethiopian Enterprise Hubs
                        </div>
                        <div className="marquee-track flex items-center gap-8 text-xs font-bold text-slate-300">
                            {[...TRUST_MARKETS, ...TRUST_MARKETS].map((hub, i) => (
                                <div key={i} className="flex items-center gap-2 whitespace-nowrap bg-emerald-950/60 px-4 py-1.5 rounded-full border border-emerald-800/40">
                                    <Building2 className="h-3.5 w-3.5 text-[#34D399]" />
                                    <span>{hub}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            {/* ============ WORKFLOW ENGINE (ORDER-TO-CASH & EXPORT) ============ */}
            <WorkflowEngine colors={colors} grad={grad} />

            {/* ============ BENTO GRID (4 CORE PILLARS) ============ */}
            <BentoPillars colors={colors} grad={grad} />

            {/* ============ AI DEEP DIVE SECTION (DARK ENTERPRISE CONTRAST) ============ */}
            <section id="ai-copilot" className="py-20 sm:py-28 bg-gradient-to-br from-[#064E3B] via-[#043E2F] to-[#022C22] text-white relative overflow-hidden">
                <div className="absolute top-0 right-0 w-[500px] h-[500px] bg-[#00A76F]/20 rounded-full blur-3xl pointer-events-none" />
                <div className="absolute bottom-0 left-0 w-[400px] h-[400px] bg-emerald-400/15 rounded-full blur-3xl pointer-events-none" />

                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-12 items-center relative z-10">
                    <div className="lg:col-span-6 space-y-6">
                        <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold border border-emerald-400/30 bg-emerald-500/10 text-emerald-300">
                            <Brain className="h-4 w-4 text-[#34D399]" />
                            <span>Gadaa AI Copilot</span>
                        </div>
                        <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight leading-tight">
                            Speak to your ERP in <span className="text-[#34D399] bg-gradient-to-r from-[#34D399] to-emerald-200 bg-clip-text text-transparent">Amharic or English</span>.
                        </h2>
                        <p className="text-emerald-100/80 text-sm sm:text-base leading-relaxed">
                            Gadaa AI Copilot parses vendor bills and receipts (Amharic + English OCR), auto-categorizes accounting entries, predicts Birr cash flow, and reconciles bank statements with Commercial Bank of Ethiopia (CBE) and Telebirr.
                        </p>

                        <div className="space-y-3 pt-2">
                            {[
                                { icon: Receipt, text: 'OCR parses Amharic & English supplier receipts automatically' },
                                { icon: TrendingUp, text: 'Predicts cash flow 30 days ahead using ETB transaction history' },
                                { icon: FileCheck, text: 'Drafts management reports in Amharic or English in seconds' },
                                { icon: Fingerprint, text: 'Suggests journal entries and flags duplicate bills for 1-click approval' }
                            ].map((item, idx) => {
                                const Icon = item.icon;
                                return (
                                    <div key={idx} className="flex items-start gap-3">
                                        <div className="w-8 h-8 rounded-xl bg-emerald-500/15 border border-emerald-400/20 flex items-center justify-center text-[#34D399] flex-shrink-0 mt-0.5">
                                            <Icon className="h-4 w-4" />
                                        </div>
                                        <span className="text-xs sm:text-sm font-medium text-emerald-100/90 pt-1 leading-snug">{item.text}</span>
                                    </div>
                                );
                            })}
                        </div>

                        <div className="pt-4">
                            <button onClick={() => go(primaryHref)} className="px-8 py-3.5 rounded-xl text-sm font-bold bg-[#00A76F] hover:bg-[#059669] text-white shadow-xl shadow-emerald-900/50 transition-all flex items-center gap-2 hover:scale-[1.02]">
                                {t('Try AI Copilot Now')} <ArrowRight className="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    {/* AI Chat Demo Visualizer */}
                    <div className="lg:col-span-6">
                        <div className="rounded-3xl bg-[#032017] border border-emerald-500/30 shadow-2xl overflow-hidden text-left">
                            <div className="flex items-center gap-3 px-6 py-4 border-b border-emerald-800/40 bg-emerald-950/40">
                                <div className="w-8 h-8 rounded-xl bg-[#00A76F] flex items-center justify-center text-white">
                                    <Bot className="h-4 w-4" />
                                </div>
                                <div>
                                    <span className="text-sm font-bold text-white block">Gadaa AI Assistant</span>
                                    <span className="text-[10px] text-emerald-400 flex items-center gap-1 font-medium">
                                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" /> Online · አማርኛ + English
                                    </span>
                                </div>
                            </div>
                            
                            <div className="p-6 space-y-4">
                                <div className="flex justify-end">
                                    <div className="max-w-[85%] px-4 py-3 rounded-2xl rounded-tr-xs bg-emerald-700/40 text-xs sm:text-sm text-white font-medium border border-emerald-500/30">
                                        በጥቅምት ወር ስንት ቫት (VAT) ተቀበልን?
                                    </div>
                                </div>

                                <div className="flex justify-start gap-3">
                                    <div className="w-7 h-7 rounded-lg bg-[#00A76F] flex items-center justify-center text-white flex-shrink-0 mt-1">
                                        <Bot className="h-4 w-4" />
                                    </div>
                                    <div className="max-w-[85%] px-4 py-3.5 rounded-2xl rounded-tl-xs bg-emerald-900/60 border border-emerald-500/25 text-xs sm:text-sm text-emerald-100 space-y-3">
                                        <p className="leading-relaxed">
                                            በጥቅምት 2017 ዓ.ም. <strong className="text-white font-bold">ETB 248,500.00</strong> ቫት ተቀብሏል።
                                        </p>
                                        <div className="grid grid-cols-3 gap-2 text-center pt-1">
                                            <div className="p-2 rounded-lg bg-emerald-950/60 border border-emerald-500/20">
                                                <span className="text-[9px] text-emerald-300/70 uppercase font-bold block">VAT Collected</span>
                                                <span className="text-xs font-bold text-white">248,500</span>
                                            </div>
                                            <div className="p-2 rounded-lg bg-emerald-950/60 border border-emerald-500/20">
                                                <span className="text-[9px] text-emerald-300/70 uppercase font-bold block">VAT Paid</span>
                                                <span className="text-xs font-bold text-white">132,000</span>
                                            </div>
                                            <div className="p-2 rounded-lg bg-emerald-950/60 border border-emerald-500/20">
                                                <span className="text-[9px] text-emerald-300/70 uppercase font-bold block">Net Tax Due</span>
                                                <span className="text-xs font-bold text-[#34D399]">116,500</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="pt-2">
                                    <div className="flex items-center gap-2 px-4 py-3 rounded-xl bg-emerald-950/80 border border-emerald-500/30 text-xs text-emerald-300/60">
                                        <span className="flex-1 truncate">Ask any business query in Amharic or English...</span>
                                        <Send className="h-4 w-4 text-[#34D399]" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ============ INDUSTRY VERTICAL SOLUTIONS ============ */}
            <IndustrySolutions colors={colors} grad={grad} />

            {/* ============ LIVE BIRR (ETB) ROI & SAVINGS CALCULATOR ============ */}
            <RoiCalculator colors={colors} grad={grad} onStartTrial={() => go(primaryHref)} />

            {/* ============ SECURITY POSTURE SECTION ============ */}
            <section id="security" className="py-20 sm:py-28 bg-[#031B13] text-white relative overflow-hidden border-t border-emerald-900/60">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-12 items-center relative z-10">
                    <div className="lg:col-span-5 space-y-6 text-left">
                        <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold border border-emerald-500/30 bg-emerald-500/10 text-emerald-300">
                            <ShieldCheck className="h-4 w-4 text-[#34D399]" />
                            <span>Enterprise Security</span>
                        </div>
                        <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight leading-tight">
                            Your business data, <span className="text-[#34D399]">locked down</span>.
                        </h2>
                        <p className="text-emerald-100/80 text-sm sm:text-base leading-relaxed">
                            GadaaCloud enforces enterprise-grade security protocols so your financial records and payroll remain encrypted, multi-tenant isolated, and compliant with Ethiopian fiscal laws.
                        </p>

                        <div className="grid grid-cols-2 gap-3 pt-2">
                            {[
                                { icon: Lock, label: 'AES-256 Encryption' },
                                { icon: KeyRound, label: '2FA & Passkey' },
                                { icon: Fingerprint, label: 'Role-Based Access' },
                                { icon: FileCheck, label: 'Audit Log Trail' },
                                { icon: Server, label: 'TLS 1.3 Transport' },
                                { icon: Cloud, label: 'Daily Offsite Backup' }
                            ].map((sec, idx) => {
                                const Icon = sec.icon;
                                return (
                                    <div key={idx} className="flex items-center gap-2.5 p-3 rounded-xl bg-emerald-900/40 border border-emerald-500/20 text-xs font-semibold text-emerald-100">
                                        <Icon className="h-4 w-4 text-[#34D399]" />
                                        <span>{sec.label}</span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <div className="lg:col-span-7">
                        <div className="rounded-3xl bg-[#022419] border border-emerald-500/30 p-6 sm:p-8 shadow-2xl space-y-6 text-left">
                            <div className="flex items-center justify-between border-b border-emerald-800/40 pb-4">
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-xl bg-[#00A76F] flex items-center justify-center text-white">
                                        <ShieldCheck className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h4 className="text-sm font-bold text-white">System Guardian</h4>
                                        <span className="text-[10px] text-emerald-400 font-medium">All compliance & tenant gates nominal</span>
                                    </div>
                                </div>
                                <span className="px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/20 text-[#34D399] border border-emerald-500/30">
                                    100% Secure
                                </span>
                            </div>

                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                {[
                                    { label: 'Encryption', val: 'AES-256' },
                                    { label: 'Multi-Tenant', val: 'creatorId' },
                                    { label: 'Cloud Uptime', val: '99.9%' },
                                    { label: 'MoR EIMS', val: 'Certified' }
                                ].map((item, idx) => (
                                    <div key={idx} className="p-3.5 rounded-xl bg-emerald-950/70 border border-emerald-500/20 text-center space-y-1">
                                        <div className="text-base font-black text-white">{item.val}</div>
                                        <div className="text-[10px] font-bold text-emerald-400/80 uppercase">{item.label}</div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ============ TESTIMONIALS ============ */}
            <section className="py-20 sm:py-28 bg-white overflow-hidden">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-12">
                    <div className="space-y-3 max-w-2xl mx-auto">
                        <span className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-50 text-[#00A76F] border border-emerald-100">
                            <Star className="h-3.5 w-3.5 fill-[#00A76F]" /> {t('Verified Customer Feedback')}
                        </span>
                        <h2 className="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight">
                            Trusted by Ethiopian businesses.
                        </h2>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        {TESTIMONIALS.map((t, idx) => (
                            <div key={idx} className="bg-slate-50/70 rounded-2xl p-6 border border-slate-200/80 flex flex-col justify-between text-left space-y-4">
                                <div className="space-y-3">
                                    <div className="flex text-amber-400 gap-1">
                                        {[...Array(5)].map((_, i) => (
                                            <Star key={i} className="w-4 h-4 fill-amber-400 text-amber-400" />
                                        ))}
                                    </div>
                                    <p className="text-xs text-slate-700 italic leading-relaxed">"{t.quote}"</p>
                                </div>
                                <div className="pt-2 border-t border-slate-200/60 flex items-center gap-3">
                                    <div className="w-9 h-9 rounded-full bg-gradient-to-br from-[#00A76F] to-[#059669] text-white flex items-center justify-center text-xs font-bold shadow-sm">
                                        {t.author.charAt(0)}
                                    </div>
                                    <div>
                                        <h4 className="text-xs font-bold text-slate-900">{t.author}</h4>
                                        <span className="text-[10px] text-slate-500 font-medium">{t.role} · {t.company}</span>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ============ FAQ SECTION ============ */}
            <section id="faq" className="py-20 sm:py-28 bg-slate-50/50">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
                    <div className="text-center space-y-3">
                        <span className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-50 text-[#00A76F] border border-emerald-100">
                            {t('FAQ')}
                        </span>
                        <h2 className="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight">
                            Frequently asked questions.
                        </h2>
                    </div>

                    <div className="space-y-3">
                        {FAQS.map((item, idx) => {
                            const isOpen = openFaq === idx;
                            return (
                                <div key={idx} className="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden transition-all text-left">
                                    <button 
                                        onClick={() => setOpenFq(isOpen ? -1 : idx)}
                                        className="w-full px-6 py-4.5 flex items-center justify-between text-left gap-4 hover:bg-emerald-50/30 transition-colors"
                                    >
                                        <span className="text-sm sm:text-base font-bold text-slate-900">{item.q}</span>
                                        <ChevronDown className={`h-5 w-5 text-[#00A76F] transition-transform duration-200 flex-shrink-0 ${isOpen ? 'rotate-180' : ''}`} />
                                    </button>
                                    {isOpen && (
                                        <div className="px-6 pb-5 pt-0 text-xs sm:text-sm text-slate-600 leading-relaxed border-t border-slate-100 bg-slate-50/40">
                                            <p className="pt-3">{item.a}</p>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* ============ CTA CALLOUT ============ */}
            <section className="py-16 sm:py-24 bg-white">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="rounded-3xl bg-gradient-to-r from-[#00A76F] via-[#059669] to-emerald-800 text-white p-10 sm:p-16 text-center shadow-2xl space-y-8 relative overflow-hidden">
                        <div className="absolute top-0 right-0 w-80 h-80 bg-white/10 rounded-full blur-3xl pointer-events-none" />
                        <div className="space-y-4 max-w-3xl mx-auto relative z-10">
                            <span className="px-3.5 py-1 rounded-full text-xs font-bold bg-white/20 text-white border border-white/20 uppercase tracking-widest inline-block">
                                GadaaCloud ERP
                            </span>
                            <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight leading-tight">
                                Ready to scale your Ethiopian enterprise?
                            </h2>
                            <p className="text-emerald-50 text-sm sm:text-base max-w-xl mx-auto font-normal">
                                Join leading businesses operating across Ethiopia. Get started in minutes with our 14-day free trial.
                            </p>
                        </div>
                        <div className="flex flex-col sm:flex-row items-center justify-center gap-4 relative z-10">
                            <button onClick={() => go(primaryHref)} className="w-full sm:w-auto px-8 py-4 rounded-xl text-base font-bold bg-white text-[#00A76F] hover:bg-slate-50 shadow-xl transition-all">
                                {primaryLabel}
                            </button>
                            <button onClick={() => go(route('login'))} className="w-full sm:w-auto px-8 py-4 rounded-xl text-base font-bold bg-transparent border-2 border-white text-white hover:bg-white/10 transition-all">
                                {t('Request Demo')}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            {/* ============ FOOTER ============ */}
            <Footer settings={settings} companyName={companyName} logoUrl={logoUrl} customPages={customPages} />

            <CookieConsent settings={adminAllSetting || {}} />
        </div>
    );
}

// =================== 12-COLUMN RESPONSIVE MEGA-FOOTER ===================
function Footer({ settings, companyName, logoUrl, customPages }: any) {
    const { t } = useTranslation();
    const [email, setEmail] = useState('');
    const [sending, setSending] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!email) return;
        setSending(true);
        router.post(route('newsletter.subscribe'), { email }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => { toast.success(t('Subscribed!')); setEmail(''); },
            onError: () => toast.error(t('Subscription failed')),
            onFinish: () => setSending(false)
        });
    };

    const year = new Date().getFullYear();

    return (
        <footer className="bg-[#031B13] text-slate-300 pt-16 pb-12 border-t border-emerald-900/50">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-10 text-left">
                    {/* Brand Info & Newsletter (4 Columns) */}
                    <div className="lg:col-span-4 space-y-4">
                        <Link href={route('landing.page')} className="flex items-center gap-2">
                            {logoUrl ? (
                                <img src={logoUrl} alt={companyName} className="h-8 w-auto" />
                            ) : (
                                <div className="flex items-center gap-2">
                                    <div className="w-8 h-8 rounded-lg bg-[#00A76F] flex items-center justify-center text-white font-extrabold text-base">
                                        G
                                    </div>
                                    <span className="font-extrabold text-lg text-white tracking-tight">
                                        Gadaa<span className="text-[#34D399]">Cloud</span> ERP
                                    </span>
                                </div>
                            )}
                        </Link>
                        <p className="text-xs text-slate-400 leading-relaxed max-w-sm">
                            The modern AI cloud ERP designed specifically for Ethiopian enterprises. Accounting in Birr, MoR EIMS Directive 1142 electronic invoicing, Ge'ez dual calendar, POS, and coffee export logistics.
                        </p>

                        <form onSubmit={submit} className="pt-2">
                            <label className="text-xs font-bold text-white block mb-1.5">{t('Subscribe to Updates')}</label>
                            <div className="flex gap-2 max-w-sm">
                                <input
                                    type="email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    required
                                    placeholder={t('you@company.et')}
                                    className="flex-1 px-3.5 py-2 rounded-xl bg-emerald-950/60 border border-emerald-800/60 text-xs text-white placeholder:text-slate-500 focus:outline-none focus:border-[#00A76F]"
                                />
                                <button
                                    type="submit"
                                    disabled={sending}
                                    className="px-4 py-2 rounded-xl text-xs font-bold bg-[#00A76F] hover:bg-[#059669] text-white shadow-md transition disabled:opacity-60"
                                >
                                    {sending ? '...' : t('Subscribe')}
                                </button>
                            </div>
                        </form>
                    </div>

                    {/* Column 1: Products (2 Columns) */}
                    <div className="lg:col-span-2 space-y-3">
                        <h4 className="text-xs font-bold text-white uppercase tracking-wider">{t('Products')}</h4>
                        <ul className="space-y-2 text-xs text-slate-400">
                            <li><a href="#pillars" className="hover:text-white transition-colors">{t('MoR EIMS Invoicing')}</a></li>
                            <li><a href="#ai-copilot" className="hover:text-white transition-colors">{t('Amharic AI Copilot')}</a></li>
                            <li><a href="#pillars" className="hover:text-white transition-colors">{t('Ge\'ez Dual Calendar')}</a></li>
                            <li><a href="#workflows" className="hover:text-white transition-colors">{t('Birr Accounting')}</a></li>
                            <li><a href="#pillars" className="hover:text-white transition-colors">{t('Offline-First POS')}</a></li>
                        </ul>
                    </div>

                    {/* Column 2: Solutions (2 Columns) */}
                    <div className="lg:col-span-2 space-y-3">
                        <h4 className="text-xs font-bold text-white uppercase tracking-wider">{t('Solutions')}</h4>
                        <ul className="space-y-2 text-xs text-slate-400">
                            <li><a href="#industries" className="hover:text-white transition-colors">{t('Coffee & Export')}</a></li>
                            <li><a href="#industries" className="hover:text-white transition-colors">{t('Real Estate')}</a></li>
                            <li><a href="#industries" className="hover:text-white transition-colors">{t('Manufacturing')}</a></li>
                            <li><a href="#industries" className="hover:text-white transition-colors">{t('Retail & Cafes')}</a></li>
                            <li><a href="#industries" className="hover:text-white transition-colors">{t('NGOs & Unions')}</a></li>
                        </ul>
                    </div>

                    {/* Column 3: Company & Custom Pages (2 Columns) */}
                    <div className="lg:col-span-2 space-y-3">
                        <h4 className="text-xs font-bold text-white uppercase tracking-wider">{t('Company')}</h4>
                        <ul className="space-y-2 text-xs text-slate-400">
                            <li><Link href={route('pricing.page')} className="hover:text-white transition-colors">{t('Pricing Plans')}</Link></li>
                            <li><a href="#workflows" className="hover:text-white transition-colors">{t('Workflow Engine')}</a></li>
                            <li><a href="#roi-calculator" className="hover:text-white transition-colors">{t('ROI Calculator')}</a></li>
                            <li><a href="#faq" className="hover:text-white transition-colors">{t('Help & FAQs')}</a></li>
                            {(customPages || []).map((p: any) => (
                                <li key={p.slug}>
                                    <Link href={route('custom-page.show', p.slug)} className="hover:text-white transition-colors">
                                        {p.title}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>

                    {/* Column 4: Contact Info (2 Columns) */}
                    <div className="lg:col-span-2 space-y-3">
                        <h4 className="text-xs font-bold text-white uppercase tracking-wider">{t('Contact Us')}</h4>
                        <ul className="space-y-2.5 text-xs text-slate-400">
                            <li className="flex items-center gap-2">
                                <MapPin className="h-4 w-4 text-[#34D399] flex-shrink-0" />
                                <span>Addis Ababa, Ethiopia</span>
                            </li>
                            <li className="flex items-center gap-2">
                                <Phone className="h-4 w-4 text-[#34D399] flex-shrink-0" />
                                <span>+251 911 00 00 00</span>
                            </li>
                            <li className="flex items-center gap-2">
                                <Mail className="h-4 w-4 text-[#34D399] flex-shrink-0" />
                                <span>info@gadaacloud.et</span>
                            </li>
                            <li className="flex items-center gap-2">
                                <Globe className="h-4 w-4 text-[#34D399] flex-shrink-0" />
                                <span>www.gadaacloud.et</span>
                            </li>
                        </ul>
                    </div>
                </div>

                {/* Footer Bottom Bar */}
                <div className="mt-12 pt-8 border-t border-emerald-900/50 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
                    <p>© {year} {companyName}. {t('All rights reserved.')} · Built for Ethiopian Enterprise 🇪🇹</p>
                    <div className="flex items-center gap-6">
                        <span className="flex items-center gap-1.5 text-emerald-400">
                            <ShieldCheck className="h-4 w-4 text-[#34D399]" />
                            {t('MoR EIMS Directive 1142 Compliant')}
                        </span>
                    </div>
                </div>
            </div>
        </footer>
    );
}
