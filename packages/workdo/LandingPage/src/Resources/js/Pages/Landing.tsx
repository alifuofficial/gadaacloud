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
    Fingerprint, KeyRound, FileCheck, Activity, Waypoints
} from 'lucide-react';
import { getAdminSetting, getImagePath } from '@/utils/helpers';
import CookieConsent from '@/components/cookie-consent';

interface LandingProps {
    settings?: any;
}

const THEMES = {
    primary: '#00A76F',   // Modern vibrant light green / emerald
    secondary: '#059669', // Rich medium emerald
    accent: '#34D399',    // Light mint accent
    cloud: '#0EA5E9',     // Sky blue accent
    ai: '#00A76F',        // Unified light green AI
    secure: '#059669'     // Emerald security
};

export default function Landing({ settings }: LandingProps) {
    const { t } = useTranslation();
    const { adminAllSetting } = usePage().props as any;

    const c = settings?.config_sections?.colors || THEMES;
    const colors = { ...THEMES, ...c, primary: '#00A76F', secondary: '#059669' };

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

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 16);
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    // -------- DATA --------
    const NAV_LINKS = useMemo(() => {
        const navSettings = settings?.config_sections?.sections?.header?.navigation_items || [];
        const customItems = customPages.map((p: any) => ({ text: p.title, href: `/page/${p.slug}` }));
        return [...navSettings, ...customItems];
    }, [settings, customPages]);

    const PILLARS = [
        {
            key: 'ai',
            icon: Brain,
            color: colors.primary,
            label: 'AI Copilot',
            title: 'AI at the Core',
            tagline: 'An ERP that thinks with you',
            points: [
                'Gadaa AI Copilot reads Amharic & English invoices, receipts and bank statements automatically',
                'Auto-categorizes transactions and predicts Birr cash flow 30 days ahead',
                'Drafts reports, suggests journal entries, answers natural-language queries ("How much VAT did we collect in Tikimt?")'
            ]
        },
        {
            key: 'cloud',
            icon: Cloud,
            color: '#0284C7',
            label: 'Cloud Infrastructure',
            title: 'Always-On Cloud',
            tagline: 'Built for Ethiopia\'s connectivity reality',
            points: [
                '99.9% uptime across Addis Ababa, Dire Dawa, Mekelle, Bahir Dar, Hawassa, Jimma, Gondar, Adama and more',
                'Offline-first POS and field modules sync automatically when connectivity returns',
                'Data hosted on redundant regional zones with automatic nightly Birr-format backups'
            ]
        },
        {
            key: 'secure',
            icon: ShieldCheck,
            color: colors.secondary,
            label: 'Secured Enterprise',
            title: 'Enterprise-Grade Security',
            tagline: 'Your business data, locked down',
            points: [
                'Role-based access control, approval workflows and full audit logs on every record',
                'AES-256 encryption at rest, TLS 1.3 in transit, and 2FA / passkey login',
                'VAT & TIN compliant exports and tax-authority ready audit trails in Birr'
            ]
        }
    ];

    const STATS = [
        { value: '5,000+', label: 'Ethiopian Businesses Running' },
        { value: '99.9%', label: 'Cloud Uptime SLA' },
        { value: '11', label: 'Regions Covered Nationwide' },
        { value: '24/7', label: 'Local Amharic & English Support' }
    ];

    const MODULES = [
        { icon: Calculator, title: 'Birr Accounting', text: 'VAT/TIN invoices, multi-currency books, CBE/Telebirr reconciliation, audit-ready reports.', tags: ['VAT', 'TIN', 'ETB'] },
        { icon: Bot,       title: 'Gadaa AI Copilot',  text: 'Parse invoices in Amharic, auto-categorize, predict cash flow and draft reports.', tags: ['AI', 'OCR', 'Forecast'] },
        { icon: HomeIcon,   title: 'Real Estate',       text: 'Leases, tenants, deposits, monthly Birr rent invoices and maintenance tickets.', tags: ['Leases', 'Rent'] },
        { icon: Boxes,      title: 'Inventory & Warehouses', text: 'Multi-depot stock, transfers, weighted-average valuation and reorder alerts.', tags: ['Multi-warehouse', 'Barcode'] },
        { icon: Coffee,     title: 'Coffee & Agribusiness',  text: 'Lot tracing from Yirgacheffe farms to ECX grading to port delivery.', tags: ['ECX', 'Lots', 'Export'] },
        { icon: Store,      title: 'Retail POS',         text: 'Offline-resilient cashier, Amharic receipts, Telebirr/CBE payments.', tags: ['POS', 'Telebirr', 'Offline'] },
        { icon: UserCheck,  title: 'HRM & Birr Payroll', text: 'Income tax, pension and allowances aligned with Ethiopian labour law.', tags: ['Payroll', 'Pension'] },
        { icon: FolderOpen, title: 'Projects & Tasks',   text: 'Kanban boards, billable timesheets, Gantt scheduling and client deliverables.', tags: ['Kanban', 'Gantt'] }
    ];

    const INDUSTRIES = [
        { icon: Coffee,         title: 'Coffee & Agribusiness',  text: 'From farm lots in Sidama to export shipments through Djibouti.' },
        { icon: Building2,      title: 'Real Estate & Property', text: 'Portfolios, leases and automated monthly Birr billing.' },
        { icon: Boxes,          title: 'Manufacturing & Goods',  text: 'Raw materials, multi-warehouse and barcoded inventory.' },
        { icon: ShoppingBag,    title: 'Retail & Supermarkets',  text: 'Offline-first point of sale for Addis stores and cafes.' },
        { icon: Globe,          title: 'Import & Export',        text: 'LC documentation, customs status and multi-currency sheets.' },
        { icon: Users,          title: 'NGOs & Cooperatives',    text: 'Grants, donor disbursements and audit-ready member files.' }
    ];

    const TESTIMONIALS = [
        { quote: 'GadaaCloud\'s AI Copilot reconciles our CBE and Telebirr statements in minutes. Monthly close is finally on time.', author: 'Selam Bekele', role: 'CFO', company: 'Bole Coffee Exporters' },
        { quote: 'Offline POS kept our shops running during power cuts. Amharic receipts print and sync automatically — a game changer.', author: 'Dawit Tesfaye', role: 'Owner', company: 'Merkato Retail Group' },
        { quote: 'Birr payroll aligned with Ethiopian labour law saves us days every month. Employees finally trust their payslips.', author: 'Hanna Girmachew', role: 'HR Director', company: 'Habesha Garments' },
        { quote: 'We trace coffee lots from farm to port in one system. ECX reporting has never been this easy for our export team.', author: 'Yonas Abebe', role: 'GM', company: 'Sidama Coffee Union' }
    ];

    const FAQS = [
        { q: 'Does GadaaCloud support Ethiopian Birr (ETB)?', a: 'Yes — accounting, invoices, payslips and reports run in ETB by default. Multi-currency support lets exporters work in USD/EUR while keeping base books in Birr.' },
        { q: 'Is it VAT & TIN compliant for ERCA audits?', a: 'VAT rates, TIN fields and tax-authority compliant invoice formats are pre-configured out of the box, plus audit-ready financial reports.' },
        { q: 'What does the Gadaa AI Copilot actually do?', a: 'Gadaa AI Copilot parses vendor bills and receipts (Amharic + English OCR), auto-categorizes accounting entries, predicts cash flow, and drafts reports in English & Amharic.' },
        { q: 'How secure is my business data?', a: 'AES-256 rest encryption, TLS 1.3 transit, role-based access, approval workflows, full audit logs, 2FA/passkey login, and nightly backups on redundant regional zones.' },
        { q: 'Does GadaaCloud work during internet interruptions?', a: 'Yes — POS and field modules are offline-first and auto-sync when connectivity returns. Cloud uptime is 99.9% across 11 regions.' },
        { q: 'Is the interface available in Amharic & local languages?', a: 'Users can toggle the entire dashboard between Amharic, English and Afaan Oromo instantly, including Ge\'ez date support alongside Gregorian.' }
    ];

    const TRUST = ['ETB Accounting', 'VAT & TIN Ready', 'Amharic AI', '11 Regions', '14-Day Free Trial'];

    // -------- COLORS HELPERS --------
    const grad = (a: string = colors.primary, b: string = colors.secondary) => `linear-gradient(135deg, ${a}, ${b})`;

    // -------- ACTIONS --------
    const go = (link: string) => { window.location.href = link; };
    const primaryHref = isAuthenticated ? route('dashboard') : route('register');
    const primaryLabel = isAuthenticated ? t('Dashboard') : (enableRegistration ? t('Start Free Trial') : t('Sign In'));

    // -------- RENDER HELPERS --------
    const renderNav = (mobile = false) => (
        <>
            {NAV_LINKS.map((item: any) => {
                const href = item.href?.startsWith('/page/')
                    ? route('custom-page.show', item.href.replace('/page/', ''))
                    : item.href;
                const cls = mobile
                    ? 'block px-4 py-3 text-base font-semibold text-slate-700 hover:text-[#00A76F] hover:bg-emerald-50/50 rounded-xl transition'
                    : 'px-4 py-2 text-sm font-semibold text-slate-700 hover:text-[#00A76F] transition-colors';
                return item.target === '_blank'
                    ? <a key={item.text} href={href} target="_blank" rel="noopener noreferrer" className={cls}>{item.text}</a>
                    : <Link key={item.text} href={href} className={cls}>{item.text}</Link>;
            })}
        </>
    );

    const renderCTAs = (mobile = false) => {
        if (isAuthenticated) {
            return (
                <Link href={route('dashboard')} className={`${mobile ? 'w-full' : ''} inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-bold text-white shadow-lg shadow-emerald-500/20 transition hover:shadow-xl hover:scale-[1.02]`} style={{ background: grad() }}>
                    {t('Dashboard')} <ArrowRight className="ml-1.5 h-4 w-4" />
                </Link>
            );
        }
        return (
            <div className={`flex ${mobile ? 'flex-col w-full space-y-2' : 'items-center space-x-3'}`}>
                <Link href={route('login')} className={`${mobile ? 'w-full' : ''} inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-slate-700 hover:text-[#00A76F] transition-colors`}>
                    {t('Log in')}
                </Link>
                {enableRegistration && (
                    <Link href={route('register')} className={`${mobile ? 'w-full' : ''} inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-bold text-white shadow-lg shadow-emerald-500/20 transition hover:shadow-xl hover:scale-[1.02]`} style={{ background: grad() }}>
                        {t('Get Started')} <ArrowRight className="ml-1.5 h-4 w-4" />
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
                @keyframes floaty { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
                @keyframes marquee { 0%{transform:translateX(0)} 100%{transform:translateX(-50%)} }
                .floaty { animation: floaty 6s ease-in-out infinite; }
                .grid-bg {
                    background-image: linear-gradient(to right, rgba(0, 167, 111, 0.05) 1px, transparent 1px), linear-gradient(to bottom, rgba(0, 167, 111, 0.05) 1px, transparent 1px);
                    background-size: 36px 36px;
                }
                .marquee-track { display:flex; width:max-content; animation: marquee 45s linear infinite; }
                .marquee-track:hover { animation-play-state: paused; }
            `}</style>

            {/* ============ HEADER ============ */}
            <header className={`sticky top-0 inset-x-0 z-50 transition-all duration-300 ${scrolled ? 'bg-white/90 backdrop-blur-md border-b border-emerald-100/80 shadow-sm' : 'bg-white/70 backdrop-blur-sm border-b border-slate-100'}`}>
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between h-20">
                        <Link href={route('landing.page')} className="flex items-center gap-2.5 group">
                            {logoUrl ? (
                                <img src={logoUrl} alt={companyName} className="h-9 w-auto" />
                            ) : (
                                <div className="flex items-center gap-2">
                                    <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-[#00A76F] to-[#059669] flex items-center justify-center text-white font-extrabold text-xl shadow-md shadow-emerald-500/20 group-hover:scale-105 transition-transform">
                                        G
                                    </div>
                                    <span className="font-extrabold text-xl tracking-tight text-slate-900">
                                        Gadaa<span className="text-[#00A76F]">Cloud</span> <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-[#00A76F] border border-emerald-200 uppercase tracking-widest ml-1">AI ERP</span>
                                    </span>
                                </div>
                            )}
                        </Link>

                        <nav className="hidden md:flex items-center gap-1">{renderNav()}</nav>

                        <div className="hidden md:flex items-center gap-2">
                            <Link href={route('pricing.page')} className="px-4 py-2 text-sm font-semibold text-slate-700 hover:text-[#00A76F] transition-colors">{t('Pricing')}</Link>
                            {renderCTAs()}
                        </div>

                        <button onClick={() => setMobileOpen(!mobileOpen)} className="md:hidden p-2.5 rounded-xl text-slate-700 hover:bg-emerald-50 transition">
                            {mobileOpen ? <X className="h-6 w-6"/> : <Menu className="h-6 w-6"/>}
                        </button>
                    </div>
                </div>

                {mobileOpen && (
                    <div className="md:hidden bg-white border-b border-emerald-100 shadow-xl animate-in slide-in-from-top duration-200">
                        <div className="px-5 py-4 space-y-2">
                            {renderNav(true)}
                            <Link href={route('pricing.page')} className="block px-4 py-3 text-base font-semibold text-slate-700 hover:text-[#00A76F] hover:bg-emerald-50/50 rounded-xl transition">{t('Pricing')}</Link>
                            <div className="pt-3 border-t border-slate-100 mt-2">{renderCTAs(true)}</div>
                        </div>
                    </div>
                )}
            </header>

            {/* ============ HERO SECTION ============ */}
            <section className="relative pt-12 pb-20 md:pt-20 md:pb-28 overflow-hidden bg-gradient-to-b from-emerald-50/70 via-emerald-50/20 to-white">
                {/* Ambient background glows */}
                <div className="absolute top-0 right-1/4 w-[500px] h-[500px] bg-[#00A76F]/10 rounded-full blur-3xl pointer-events-none -z-10" />
                <div className="absolute top-1/3 left-0 w-[400px] h-[400px] bg-emerald-400/10 rounded-full blur-3xl pointer-events-none -z-10" />
                <div className="absolute inset-0 grid-bg opacity-60 pointer-events-none -z-10" />

                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                    {/* Left Column - Headline & Copy */}
                    <div className="lg:col-span-6 space-y-8 text-center lg:text-left">
                        <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-100/70 border border-emerald-200 text-xs font-bold text-[#00A76F] shadow-sm">
                            <Sparkles className="h-4 w-4 text-[#00A76F]" />
                            <span>#1 Modern AI Cloud ERP for Ethiopian Enterprise</span>
                        </div>

                        <h1 className="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 tracking-tight leading-[1.12]">
                            The <span className="bg-gradient-to-r from-[#00A76F] via-[#059669] to-emerald-600 bg-clip-text text-transparent">modern AI cloud ERP</span> built for Ethiopian businesses.
                        </h1>

                        <p className="text-base sm:text-lg text-slate-600 max-w-xl mx-auto lg:mx-0 leading-relaxed font-normal">
                            Accounting in Birr with VAT & TIN, real estate, POS, HRM, payroll and agribusiness modules — unified on a secure cloud platform running across all 11 regions, supercharged by an AI copilot that speaks Amharic.
                        </p>

                        <div className="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4">
                            <button onClick={() => go(primaryHref)} className="w-full sm:w-auto px-8 py-4 rounded-xl text-base font-bold text-white shadow-xl shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:scale-[1.02] transition-all duration-200 flex items-center justify-center gap-2" style={{ background: grad() }}>
                                {primaryLabel} <ArrowRight className="h-5 w-5" />
                            </button>
                            <button onClick={() => go(route('login'))} className="w-full sm:w-auto px-8 py-4 rounded-xl text-base font-bold bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 hover:border-slate-300 transition-all duration-200 text-center shadow-sm">
                                {t('Request a Demo')}
                            </button>
                        </div>

                        {/* Trust highlights */}
                        <div className="flex flex-wrap items-center justify-center lg:justify-start gap-x-6 gap-y-3 pt-2 text-xs font-semibold text-slate-600">
                            {TRUST.map((item) => (
                                <div key={item} className="flex items-center gap-2">
                                    <div className="w-4 h-4 rounded-full bg-emerald-100 flex items-center justify-center text-[#00A76F]">
                                        <Check className="h-3 w-3 stroke-[3]" />
                                    </div>
                                    <span>{item}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Right Column - Interactive Dashboard Mock */}
                    <div className="lg:col-span-6 relative">
                        <div className="relative">
                            {/* Glowing backdrop shadow */}
                            <div className="absolute inset-0 bg-gradient-to-tr from-[#00A76F]/20 to-emerald-400/20 rounded-3xl blur-2xl transform rotate-2 -z-10" />
                            
                            <HeroMock colors={colors} grad={grad} />

                            {/* Floating AI Copilot Card */}
                            <div className="absolute -bottom-6 -left-4 sm:-left-6 flex items-center gap-3.5 bg-white/95 backdrop-blur-md text-slate-900 px-4 py-3.5 rounded-2xl shadow-xl border border-emerald-100 floaty z-20 max-w-[290px]">
                                <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-[#00A76F] to-[#059669] flex items-center justify-center text-white shadow-md shadow-emerald-500/20 flex-shrink-0">
                                    <Bot className="h-5 w-5" />
                                </div>
                                <div className="flex flex-col text-left">
                                    <span className="text-xs font-bold text-slate-900">Gadaa AI Copilot</span>
                                    <span className="text-[11px] text-slate-500 font-medium truncate">"Show VAT collected in Tikimt" · ETB 248,500</span>
                                </div>
                            </div>

                            {/* Floating Security Badge */}
                            <div className="absolute -top-6 -right-4 sm:-right-6 hidden sm:flex items-center gap-3 bg-white/95 backdrop-blur-md text-slate-900 px-4 py-3 rounded-2xl shadow-xl border border-emerald-100 floaty z-20" style={{ animationDelay: '2s' }}>
                                <div className="w-9 h-9 rounded-xl bg-emerald-50 flex items-center justify-center text-[#00A76F]">
                                    <ShieldCheck className="h-5 w-5" />
                                </div>
                                <div className="flex flex-col text-left">
                                    <span className="text-xs font-bold text-slate-900">AES-256 · TLS 1.3</span>
                                    <span className="text-[10px] text-emerald-600 font-bold">ERCA Audit Ready</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ============ STATS COUNTER BAND ============ */}
            <section className="bg-emerald-950 text-white py-12 relative overflow-hidden">
                <div className="absolute inset-0 bg-[radial-gradient(#00A76F_1px,transparent_1px)] [background-size:24px_24px] opacity-10 pointer-events-none" />
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-2 md:grid-cols-4 gap-8 text-center relative z-10">
                    {STATS.map((s) => (
                        <div key={s.label} className="space-y-1">
                            <div className="text-3xl sm:text-4xl font-black text-[#34D399] tracking-tight">{s.value}</div>
                            <div className="text-xs sm:text-sm font-medium text-emerald-200/80">{s.label}</div>
                        </div>
                    ))}
                </div>
            </section>

            {/* ============ THREE PILLARS ============ */}
            <section id="pillars" className="py-20 sm:py-28 bg-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="max-w-3xl mx-auto text-center mb-16 space-y-4">
                        <span className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-50 text-[#00A76F] border border-emerald-100">
                            <Waypoints className="h-3.5 w-3.5" /> {t('Platform Pillars')}
                        </span>
                        <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 tracking-tight">
                            Three pillars. One platform for Ethiopian growth.
                        </h2>
                        <p className="text-base sm:text-lg text-slate-600 leading-relaxed">
                            Built from the ground up around AI, cloud architecture, and enterprise security — specifically tailored for the Ethiopian market.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
                        {PILLARS.map((p) => {
                            const Icon = p.icon;
                            return (
                                <div key={p.key} className="group rounded-3xl p-8 bg-white border border-slate-200/80 shadow-sm hover:shadow-xl hover:border-emerald-200 transition-all duration-300 hover:-translate-y-1 space-y-6 flex flex-col justify-between">
                                    <div className="space-y-4">
                                        <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-[#00A76F] to-[#059669] flex items-center justify-center text-white shadow-lg shadow-emerald-500/20 group-hover:scale-110 transition-transform">
                                            <Icon className="h-7 w-7" />
                                        </div>
                                        <div>
                                            <span className="text-xs font-bold text-[#00A76F] uppercase tracking-wider">{p.label}</span>
                                            <h3 className="text-2xl font-extrabold text-slate-900 tracking-tight mt-1">{p.title}</h3>
                                            <p className="text-xs text-slate-500 font-medium">{p.tagline}</p>
                                        </div>
                                        <ul className="space-y-3 pt-2">
                                            {p.points.map((pt) => (
                                                <li key={pt} className="flex items-start gap-3 text-sm text-slate-700 leading-relaxed">
                                                    <div className="w-5 h-5 rounded-full bg-emerald-50 flex items-center justify-center text-[#00A76F] flex-shrink-0 mt-0.5">
                                                        <Check className="h-3.5 w-3.5 stroke-[3]" />
                                                    </div>
                                                    <span>{pt}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* ============ AI DEEP DIVE SECTION (DARK ENTERPRISE CONTRAST) ============ */}
            <section id="ai-copilot" className="py-20 sm:py-28 bg-gradient-to-br from-[#064E3B] via-[#043E2F] to-[#022C22] text-white relative overflow-hidden">
                {/* Glowing light green blur blobs */}
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
                        <p className="text-emerald-100/80 text-base sm:text-lg leading-relaxed">
                            Gadaa AI Copilot parses vendor bills and receipts (Amharic + English OCR), auto-categorizes accounting entries, predicts Birr cash flow, and drafts reports instantly.
                        </p>

                        <div className="space-y-4 pt-2">
                            {[
                                { icon: Receipt, text: 'OCR parses Amharic & English receipts and supplier invoices automatically' },
                                { icon: TrendingUp, text: 'Predicts cash flow 30 days ahead using ETB transaction history' },
                                { icon: FileCheck, text: 'Drafts management reports in Amharic or English in seconds' },
                                { icon: Fingerprint, text: 'Suggests journal entries and flags anomalies for one-click approval' }
                            ].map((item, idx) => {
                                const Icon = item.icon;
                                return (
                                    <div key={idx} className="flex items-start gap-3.5">
                                        <div className="w-9 h-9 rounded-xl bg-emerald-500/15 border border-emerald-400/20 flex items-center justify-center text-[#34D399] flex-shrink-0 mt-0.5">
                                            <Icon className="h-5 w-5" />
                                        </div>
                                        <span className="text-sm font-medium text-emerald-100/90 pt-1.5 leading-snug">{item.text}</span>
                                    </div>
                                );
                            })}
                        </div>

                        <div className="pt-4">
                            <button onClick={() => go(primaryHref)} className="px-8 py-4 rounded-xl text-base font-bold bg-[#00A76F] hover:bg-[#059669] text-white shadow-xl shadow-emerald-900/50 transition-all flex items-center gap-2">
                                {t('Try AI Copilot Now')} <ArrowRight className="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                    {/* AI Chat Demo Window */}
                    <div className="lg:col-span-6">
                        <div className="rounded-3xl bg-[#032017] border border-emerald-500/30 shadow-2xl overflow-hidden">
                            {/* Window Header */}
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
                            
                            {/* Chat Messages */}
                            <div className="p-6 space-y-4 text-left">
                                {/* User Prompt */}
                                <div className="flex justify-end">
                                    <div className="max-w-[85%] px-4 py-3 rounded-2xl rounded-tr-xs bg-emerald-700/40 text-sm text-white font-medium border border-emerald-500/30">
                                        በጥቅምት ወር ስንት ቫት (VAT) ተቀበልን?
                                    </div>
                                </div>

                                {/* AI Response */}
                                <div className="flex justify-start gap-3">
                                    <div className="w-7 h-7 rounded-lg bg-[#00A76F] flex items-center justify-center text-white flex-shrink-0 mt-1">
                                        <Bot className="h-4 w-4" />
                                    </div>
                                    <div className="max-w-[85%] px-4 py-3.5 rounded-2xl rounded-tl-xs bg-emerald-900/60 border border-emerald-500/25 text-sm text-emerald-100 space-y-3">
                                        <p className="leading-relaxed">
                                            በጥቅምት 2017 ዓ.ም. <strong className="text-white font-bold">ETB 248,500</strong> ቫት ተቀብሏል።
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

                                {/* Input bar */}
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

            {/* ============ MODULES GRID ============ */}
            <section id="modules" className="py-20 sm:py-28 bg-slate-50/50">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="max-w-3xl mx-auto text-center mb-16 space-y-4">
                        <span className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-50 text-[#00A76F] border border-emerald-100">
                            <Cpu className="h-3.5 w-3.5" /> {t('Enterprise Suite')}
                        </span>
                        <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 tracking-tight">
                            Every module your Ethiopian business needs — in Birr, built in.
                        </h2>
                        <p className="text-base sm:text-lg text-slate-600 leading-relaxed">
                            From Addis Ababa headquarters to regional branches, manage all business operations under one unified light green cloud platform.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        {MODULES.map((m, idx) => {
                            const Icon = m.icon;
                            return (
                                <div key={idx} className="bg-white rounded-2xl p-6 border border-slate-200/80 shadow-sm hover:shadow-lg hover:border-emerald-200 transition-all duration-300 space-y-4 group">
                                    <div className="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center text-[#00A76F] group-hover:scale-110 transition-transform">
                                        <Icon className="h-6 w-6" />
                                    </div>
                                    <h3 className="text-lg font-bold text-slate-900">{m.title}</h3>
                                    <p className="text-xs text-slate-600 leading-relaxed">{m.text}</p>
                                    <div className="flex flex-wrap gap-1.5 pt-2">
                                        {m.tags.map((tag) => (
                                            <span key={tag} className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-[#00A76F] border border-emerald-100">
                                                {tag}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* ============ INDUSTRIES SECTION ============ */}
            <section id="industries" className="py-20 sm:py-28 bg-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="max-w-3xl mx-auto text-center mb-16 space-y-4">
                        <span className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-50 text-[#00A76F] border border-emerald-100">
                            <Building2 className="h-3.5 w-3.5" /> {t('Industry Tailored')}
                        </span>
                        <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 tracking-tight">
                            Built for every Ethiopian sector.
                        </h2>
                        <p className="text-base sm:text-lg text-slate-600 leading-relaxed">
                            Tailored ERP workflows designed for key growth sectors across Ethiopia.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                        {INDUSTRIES.map((ind, idx) => {
                            const Icon = ind.icon;
                            return (
                                <div key={idx} className="p-8 rounded-2xl bg-white border border-slate-200/80 shadow-sm hover:shadow-md hover:border-emerald-200 transition-all space-y-4">
                                    <div className="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center text-[#00A76F]">
                                        <Icon className="h-6 w-6" />
                                    </div>
                                    <h3 className="text-xl font-bold text-slate-900">{ind.title}</h3>
                                    <p className="text-xs text-slate-600 leading-relaxed">{ind.text}</p>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* ============ SECURITY POSTURE SECTION ============ */}
            <section id="security" className="py-20 sm:py-28 bg-emerald-950 text-white relative overflow-hidden">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-12 items-center relative z-10">
                    <div className="lg:col-span-5 space-y-6">
                        <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold border border-emerald-500/30 bg-emerald-500/10 text-emerald-300">
                            <ShieldCheck className="h-4 w-4 text-[#34D399]" />
                            <span>Enterprise Security</span>
                        </div>
                        <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight leading-tight">
                            Your business data, <span className="text-[#34D399]">locked down</span>.
                        </h2>
                        <p className="text-emerald-100/80 text-base leading-relaxed">
                            GadaaCloud enforces enterprise-grade security protocols so your financial records and payroll remain encrypted, secure, and compliant.
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
                        <div className="rounded-3xl bg-[#032017] border border-emerald-500/30 p-8 shadow-2xl space-y-6">
                            <div className="flex items-center justify-between border-b border-emerald-800/40 pb-4">
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-xl bg-[#00A76F] flex items-center justify-center text-white">
                                        <ShieldCheck className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h4 className="text-sm font-bold text-white">Security Status</h4>
                                        <span className="text-[10px] text-emerald-400 font-medium">All systems nominal & active</span>
                                    </div>
                                </div>
                                <span className="px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/20 text-[#34D399] border border-emerald-500/30">
                                    System Guard 100%
                                </span>
                            </div>

                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                {[
                                    { label: 'Encryption', val: 'AES-256' },
                                    { label: 'Auth Status', val: '2FA Active' },
                                    { label: 'Cloud Uptime', val: '99.9%' },
                                    { label: 'Tax Audits', val: 'Passed' }
                                ].map((item, idx) => (
                                    <div key={idx} className="p-4 rounded-xl bg-emerald-950/70 border border-emerald-500/20 text-center space-y-1">
                                        <div className="text-lg font-black text-white">{item.val}</div>
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
                            <Star className="h-3.5 w-3.5 fill-[#00A76F]" /> {t('Customer Feedback')}
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

                    <div className="space-y-4">
                        {FAQS.map((item, idx) => {
                            const isOpen = openFaq === idx;
                            return (
                                <div key={idx} className="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden transition-all">
                                    <button 
                                        onClick={() => setOpenFq(isOpen ? -1 : idx)}
                                        className="w-full px-6 py-5 flex items-center justify-between text-left gap-4 hover:bg-emerald-50/30 transition-colors"
                                    >
                                        <span className="text-base font-bold text-slate-900">{item.q}</span>
                                        <ChevronDown className={`h-5 w-5 text-[#00A76F] transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`} />
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
                    <div className="rounded-3xl bg-gradient-to-r from-[#00A76F] via-[#059669] to-emerald-700 text-white p-10 sm:p-16 text-center shadow-2xl space-y-8 relative overflow-hidden">
                        <div className="absolute top-0 right-0 w-80 h-80 bg-white/10 rounded-full blur-3xl pointer-events-none" />
                        <div className="space-y-4 max-w-3xl mx-auto relative z-10">
                            <span className="px-3.5 py-1 rounded-full text-xs font-bold bg-white/20 text-white border border-white/20 uppercase tracking-widest inline-block">
                                GadaaCloud ERP
                            </span>
                            <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight leading-tight">
                                Ready to scale your Ethiopian enterprise?
                            </h2>
                            <p className="text-emerald-50 text-sm sm:text-base max-w-xl mx-auto font-normal">
                                Join thousands of businesses operating across Ethiopia. Get started in minutes with our 14-day free trial.
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

            {/* ============ FOOTER (FIXED STRUCTURE) ============ */}
            <Footer settings={settings} companyName={companyName} logoUrl={logoUrl} customPages={customPages} />

            <CookieConsent settings={adminAllSetting || {}} />
        </div>
    );
}

// =================== HERO MOCK DASHBOARD ===================
function HeroMock({ colors, grad }: { colors: any; grad: (a: string, b: string) => string }) {
    return (
        <div className="w-full rounded-2xl bg-white shadow-2xl border border-slate-200/80 overflow-hidden text-left">
            {/* Top Window Bar */}
            <div className="flex items-center gap-2 px-4 py-3 bg-slate-100/80 border-b border-slate-200/70">
                <div className="w-3 h-3 rounded-full bg-rose-400" />
                <div className="w-3 h-3 rounded-full bg-amber-400" />
                <div className="w-3 h-3 rounded-full bg-emerald-400" />
                <div className="ml-3 flex-1 max-w-xs px-3 py-1 rounded-full bg-white border border-slate-200 text-[11px] text-slate-500 font-medium truncate flex items-center gap-1.5">
                    <span className="w-1.5 h-1.5 rounded-full bg-[#00A76F]" />
                    app.gadaacloud.et/dashboard
                </div>
            </div>

            {/* Dashboard Workspace Mock */}
            <div className="p-5 sm:p-6 bg-slate-50/50 space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h4 className="text-sm sm:text-base font-bold text-slate-900">Welcome back, Ethio Trading 👋</h4>
                        <p className="text-[11px] text-slate-500 font-medium">Real-time Birr (ETB) Overview</p>
                    </div>
                    <span className="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-[#00A76F] border border-emerald-200 flex items-center gap-1">
                        <span className="w-1.5 h-1.5 rounded-full bg-[#00A76F] animate-pulse" /> Live
                    </span>
                </div>

                {/* Key Metrics */}
                <div className="grid grid-cols-3 gap-3">
                    <div className="p-3.5 rounded-xl bg-white border border-slate-200/70 shadow-xs space-y-1">
                        <span className="text-[10px] font-bold text-slate-400 uppercase block">Total Revenue</span>
                        <span className="text-sm sm:text-lg font-black text-slate-900 block">ETB 1.2M</span>
                        <span className="text-[10px] font-bold text-[#00A76F] flex items-center gap-0.5">
                            <TrendingUp className="h-3 w-3" /> +14.2%
                        </span>
                    </div>
                    <div className="p-3.5 rounded-xl bg-white border border-slate-200/70 shadow-xs space-y-1">
                        <span className="text-[10px] font-bold text-slate-400 uppercase block">Orders</span>
                        <span className="text-sm sm:text-lg font-black text-slate-900 block">3,420</span>
                        <span className="text-[10px] font-bold text-[#00A76F] flex items-center gap-0.5">
                            <TrendingUp className="h-3 w-3" /> +8.5%
                        </span>
                    </div>
                    <div className="p-3.5 rounded-xl bg-white border border-slate-200/70 shadow-xs space-y-1">
                        <span className="text-[10px] font-bold text-slate-400 uppercase block">VAT Filed</span>
                        <span className="text-sm sm:text-lg font-black text-slate-900 block">100%</span>
                        <span className="text-[10px] font-bold text-emerald-600">Compliant</span>
                    </div>
                </div>

                {/* Chart Mock */}
                <div className="p-4 rounded-xl bg-white border border-slate-200/70 shadow-xs space-y-3">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-bold text-slate-900">Monthly Cash Flow (Birr)</span>
                        <span className="text-[10px] font-bold text-[#00A76F]">ETB +24.5% vs Last Month</span>
                    </div>
                    <div className="flex items-end justify-between gap-2 h-20 pt-2">
                        {[40, 60, 50, 75, 65, 88, 100].map((h, i) => (
                            <div key={i} className="flex-1 bg-emerald-100 rounded-t-sm overflow-hidden flex items-end">
                                <div className="w-full bg-[#00A76F] rounded-t-sm" style={{ height: `${h}%` }} />
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}

// =================== FIXED FOOTER STRUCTURE ===================
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
        <footer className="bg-[#031B13] text-slate-300 pt-16 pb-12 border-t border-emerald-900/40">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* 12 Column Balanced Layout */}
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
                            The modern AI cloud ERP designed specifically for Ethiopian enterprises. Accounting in Birr, VAT/TIN compliance, inventory, POS, HRM, payroll and coffee export management.
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
                            <li><a href="#modules" className="hover:text-white transition-colors">{t('Birr Accounting')}</a></li>
                            <li><a href="#modules" className="hover:text-white transition-colors">{t('Gadaa AI Copilot')}</a></li>
                            <li><a href="#modules" className="hover:text-white transition-colors">{t('Retail POS')}</a></li>
                            <li><a href="#modules" className="hover:text-white transition-colors">{t('HRM & Payroll')}</a></li>
                            <li><a href="#modules" className="hover:text-white transition-colors">{t('Inventory Management')}</a></li>
                        </ul>
                    </div>

                    {/* Column 2: Solutions (2 Columns) */}
                    <div className="lg:col-span-2 space-y-3">
                        <h4 className="text-xs font-bold text-white uppercase tracking-wider">{t('Solutions')}</h4>
                        <ul className="space-y-2 text-xs text-slate-400">
                            <li><a href="#industries" className="hover:text-white transition-colors">{t('Coffee & Export')}</a></li>
                            <li><a href="#industries" className="hover:text-white transition-colors">{t('Real Estate')}</a></li>
                            <li><a href="#industries" className="hover:text-white transition-colors">{t('Manufacturing')}</a></li>
                            <li><a href="#industries" className="hover:text-white transition-colors">{t('Retail & Supermarkets')}</a></li>
                            <li><a href="#industries" className="hover:text-white transition-colors">{t('NGOs & Unions')}</a></li>
                        </ul>
                    </div>

                    {/* Column 3: Company & Custom Pages (2 Columns) */}
                    <div className="lg:col-span-2 space-y-3">
                        <h4 className="text-xs font-bold text-white uppercase tracking-wider">{t('Company')}</h4>
                        <ul className="space-y-2 text-xs text-slate-400">
                            <li><Link href={route('pricing.page')} className="hover:text-white transition-colors">{t('Pricing Plans')}</Link></li>
                            <li><a href="#pillars" className="hover:text-white transition-colors">{t('About Platform')}</a></li>
                            <li><a href="#faq" className="hover:text-white transition-colors">{t('Help & FAQs')}</a></li>
                            {customPages.map((p: any) => (
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
                <div className="mt-12 pt-8 border-t border-emerald-900/40 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
                    <p>© {year} {companyName}. {t('All rights reserved.')} · Built for Ethiopian Enterprise 🇪🇹</p>
                    <div className="flex items-center gap-6">
                        <span className="flex items-center gap-1.5 text-emerald-400">
                            <ShieldCheck className="h-4 w-4 text-[#34D399]" />
                            {t('VAT & TIN Compliant')}
                        </span>
                    </div>
                </div>
            </div>
        </footer>
    );
}