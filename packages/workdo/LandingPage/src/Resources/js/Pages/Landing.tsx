import { Head, Link, usePage, router } from '@inertiajs/react';
import { useMemo, useState, useEffect } from 'react';
import { toast } from 'sonner';
import { useTranslation } from 'react-i18next';
import {
    ArrowRight, Check, Menu, X, ChevronDown, Star, Quote,
    Brain, Cloud, ShieldCheck, Sparkles, Lock, Server, Cpu,
    Wallet, Users, ShoppingCart, TrendingUp, Calculator, Home as HomeIcon,
    Boxes, Wheat, Store, UserCheck, FolderOpen, Receipt,
    Coffee, ShoppingBag, Factory, Ship, HeartHandshake, Building2,
    Mail, Phone, MapPin, Globe, Zap, BarChart3, Bot, Send,
    Fingerprint, KeyRound, FileCheck, Activity, Waypoints
} from 'lucide-react';
import { getAdminSetting, getImagePath } from '@/utils/helpers';
import CookieConsent from '@/components/cookie-consent';

interface LandingProps {
    settings?: any;
}

const THEMES = {
    primary: '#0B6B4F',   // deep emerald (modern, trustworthy)
    secondary: '#06382B', // deep pine
    accent: '#FFD23F',    // amber gold (Ethiopian accent)
    cloud: '#0EA5E9',     // sky blue (cloud pillar)
    ai: '#8B5CF6',        // violet (AI pillar)
    secure: '#0B6B4F'     // emerald (security pillar)
};

export default function Landing({ settings }: LandingProps) {
    const { t } = useTranslation();
    const { adminAllSetting } = usePage().props as any;

    const c = settings?.config_sections?.colors || THEMES;
    const colors = { ...THEMES, ...c };

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
    }, [settings]);

    const PILLARS = [
        {
            key: 'ai',
            icon: Brain,
            color: colors.ai,
            label: 'AI',
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
            color: colors.cloud,
            label: 'Cloud',
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
            color: colors.secure,
            label: 'Secured',
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
        { value: '5,000+', label: 'Ethiopian businesses running' },
        { value: '99.9%', label: 'Cloud uptime guarantee' },
        { value: '11', label: 'Regions covered nationwide' },
        { value: '24/7', label: 'Local Amharic support' }
    ];

    const MODULES = [
        { icon: Calculator, title: 'Birr Accounting', text: 'VAT/TIN invoices, multi-currency books, CBE/Telebirr reconciliation, audit-ready reports.', tags: ['VAT', 'TIN', 'ETB'] },
        { icon: Bot,       title: 'Gadaa AI Copilot',  text: 'Parse invoices in Amharic, auto-categorize, predict cash flow and draft reports.', tags: ['AI', 'OCR', 'Forecast'] },
        { icon: HomeIcon,   title: 'Real Estate',       text: 'Leases, tenants, deposits, monthly Birr rent invoices and maintenance tickets.', tags: ['Leases', 'Rent'] },
        { icon: Boxes,      title: 'Inventory & Warehouses', text: 'Multi-depot stock, transfers, weighted-average valuation and reorder alerts.', tags: ['Multi-warehouse', 'Barcode'] },
        { icon: Coffee,     title: 'Coffee & Agribusiness',  text: 'Lot tracing from Yirgacheffe farms to ECX grading to port delivery.', tags: ['ECX', 'Lots', 'Export'] },
        { icon: Store,      title: 'Retail POS',         text: 'Offline-resilient cashier, Amharic receipts, Telebirr/CBE payments.', tags: ['POS', 'Telebirr', 'Offline'] },
        { icon: UserCheck,  title: 'HRM & Birr Payroll', text: 'Income tax, pension and allowances aligned with Ethiopian labour law.', tags: ['Payroll', 'Pension'] },
        { icon: FolderOpen, title: 'Projects',           text: 'Kanban boards, billable timesheets, Gantt scheduling and client deliverables.', tags: ['Kanban', 'Gantt'] }
    ];

    const INDUSTRIES = [
        { icon: Coffee,         title: 'Coffee & Agribusiness',  text: 'From farm lots in Sidama to export shipments through Djibouti.' },
        { icon: Building2,      title: 'Real Estate & Property', text: 'Portfolios, leases and automated monthly Birr billing.' },
        { icon: Boxes,          title: 'Manufacturing',          text: 'Raw materials, multi-warehouse and barcoded inventory.' },
        { icon: ShoppingBag,    title: 'Retail & POS',           text: 'Offline-first point of sale for Addis stores and cafes.' },
        { icon: Ship,           title: 'Import / Export',        text: 'LC documentation, customs status and multi-currency sheets.' },
        { icon: HeartHandshake, title: 'NGOs & Cooperatives',    text: 'Grants, donor disbursements and audit-ready member files.' }
    ];

    const TESTIMONIALS = [
        { quote: 'GadaaCloud\'s AI Copilot reconciles our CBE and Telebirr statements in minutes. Monthly close is finally on time.', author: 'Selam Bekele', role: 'CFO', company: 'Bole Coffee Exporters' },
        { quote: 'Offline POS kept our shops running during power cuts. Amharic receipts print and sync automatically — a game changer.', author: 'Dawit Tesfaye', role: 'Owner', company: 'Merkato Retail Group' },
        { quote: 'Birr payroll aligned with Ethiopian labour law saves us days every month. Employees finally trust their payslips.', author: 'Hanna Girmachew', role: 'HR Director', company: 'Habesha Garments' },
        { quote: 'We trace coffee lots from farm to port in one system. ECX reporting has never been this easy for our export team.', author: 'Yonas Abebe', role: 'GM', company: 'Sidama Coffee Union' }
    ];

    const FAQS = [
        { q: 'Does it support Ethiopian Birr (ETB)?', a: 'Yes — accounting, invoices, payslips and reports run in ETB by default. Multi-currency support lets exporters work in USD/EUR while keeping base books in Birr.' },
        { q: 'Is it VAT & TIN compliant?', a: 'VAT rates, TIN fields and tax-authority compliant invoice formats are pre-configured out of the box, plus audit-ready financial reports.' },
        { q: 'What does the AI actually do?', a: 'Gadaa AI Copilot parses vendor bills and receipts (Amharic + English OCR), auto-categorizes accounting entries, predicts cash flow, and drafts reports in English & Amharic.' },
        { q: 'How secure is my business data?', a: 'AES-256 rest encryption, TLS 1.3 transit, role-based access, approval workflows, full audit logs, 2FA/passkey login, and nightly backups on redundant regional zones.' },
        { q: 'Does it work on patchy Ethiopian internet?', a: 'Yes — POS and field modules are offline-first and auto-sync when connectivity returns. Cloud uptime is 99.9% across 11 regions.' },
        { q: 'Is the interface available in Amharic?', a: 'Users can toggle the entire dashboard between Amharic, English and Afaan Oromo instantly, including Ge\'ez date support alongside Gregorian.' }
    ];

    const TRUST = ['ETB accounting', 'VAT & TIN ready', 'Amharic interface', '11 regions', 'No credit card required'];

    // -------- COLORS HELPERS --------
    const grad = (a: string, b: string) => `linear-gradient(135deg, ${a}, ${b})`;
    const pillarGrad = (key: string) => {
        if (key === 'ai') return grad('#8B5CF6', '#6366F1');
        if (key === 'cloud') return grad('#0EA5E9', '#0284C7');
        return grad(colors.primary, colors.secondary);
    };

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
                    ? 'block px-4 py-3 text-base font-medium text-gray-700 hover:bg-gray-50 rounded-lg transition'
                    : 'px-4 py-2 text-sm font-medium text-gray-700 hover:text-[color:var(--brand)] transition';
                return item.target === '_blank'
                    ? <a key={item.text} href={href} target="_blank" rel="noopener noreferrer" className={cls}>{item.text}</a>
                    : <Link key={item.text} href={href} className={cls}>{item.text}</Link>;
            })}
        </>
    );

    const renderCTAs = (mobile = false) => {
        if (isAuthenticated) {
            return (
                <Link href={route('dashboard')} className={`${mobile ? 'w-full' : ''} inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-bold text-white shadow-lg transition hover:-translate-y-0.5`} style={{ background: grad(colors.primary, colors.secondary) }}>
                    {t('Dashboard')}
                </Link>
            );
        }
        return (
            <div className={`flex ${mobile ? 'flex-col w-full space-y-2' : 'space-x-2'}`}>
                <Link href={route('login')} className={`${mobile ? 'w-full' : ''} inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-bold border-2 transition hover:bg-gray-50`} style={{ borderColor: colors.primary, color: colors.primary }}>
                    {t('Sign In')}
                </Link>
                {enableRegistration && (
                    <Link href={route('register')} className={`${mobile ? 'w-full' : ''} inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-bold text-white shadow-lg transition hover:-translate-y-0.5`} style={{ background: grad(colors.primary, colors.secondary) }}>
                        {t('Get Started')} <ArrowRight className="ml-1.5 h-4 w-4" />
                    </Link>
                )}
            </div>
        );
    };

    return (
        <div className="min-h-screen bg-white text-gray-900" style={{ ['--brand' as any]: colors.primary }}>
            <Head title={`${companyName} — Modern AI Cloud ERP for Ethiopian Business`}>
                {faviconUrl && <link rel="icon" type="image/x-icon" href={faviconUrl} />}
            </Head>

            <style>{`
                :root { --brand: ${colors.primary}; }
                @keyframes floaty { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-12px)} }
                @keyframes pulseRing { 0%{transform:scale(.8);opacity:.6} 100%{transform:scale(1.6);opacity:0} }
                @keyframes marquee { 0%{transform:translateX(0)} 100%{transform:translateX(-50%)} }
                @keyframes shimmer { 0%{background-position:-200% 0} 100%{background-position:200% 0} }
                .floaty { animation: floaty 6s ease-in-out infinite; }
                .grid-bg {
                    background-image: linear-gradient(to right, rgba(120,120,120,.06) 1px, transparent 1px), linear-gradient(to bottom, rgba(120,120,120,.06) 1px, transparent 1px);
                    background-size: 32px 32px;
                    mask-image: radial-gradient(ellipse at center, black 0%, transparent 75%);
                }
                .marquee-track { display:flex; width:max-content; animation: marquee 42s linear infinite; }
                .marquee-track:hover { animation-play-state: paused; }
                .shimmer {
                    background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,.5) 50%, rgba(255,255,255,0) 100%);
                    background-size: 200% 100%;
                    animation: shimmer 3s linear infinite;
                }
            `}</style>

            {/* ============ HEADER ============ */}
            <header className={`fixed top-0 inset-x-0 z-50 transition-all duration-300 ${scrolled ? 'bg-white/85 backdrop-blur-xl border-b border-gray-100 shadow-sm' : 'bg-transparent'}`}>
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between h-18 py-4">
                        <Link href={route('landing.page')} className="flex items-center gap-2.5">
                            {logoUrl
                                ? <img src={logoUrl} alt={companyName} className="h-8 w-auto" />
                                : <span className="text-2xl font-black tracking-tight" style={{ color: colors.primary }}>{companyName}</span>}
                        </Link>

                        <nav className="hidden md:flex items-center gap-1">{renderNav()}</nav>

                        <div className="hidden md:flex items-center gap-2">
                            <Link href={route('pricing.page')} className="px-4 py-2 text-sm font-medium text-gray-700 hover:text-[color:var(--brand)] transition">{t('Pricing')}</Link>
                            {renderCTAs()}
                        </div>

                        <button onClick={() => setMobileOpen(!mobileOpen)} className="md:hidden p-2 rounded-lg hover:bg-gray-100 transition">
                            {mobileOpen ? <X className="h-6 w-6"/> : <Menu className="h-6 w-6"/>}
                        </button>
                    </div>
                </div>

                {mobileOpen && (
                    <div className="md:hidden bg-white border-t border-gray-100 shadow-xl">
                        <div className="px-4 py-3 space-y-1">
                            {renderNav(true)}
                            <Link href={route('pricing.page')} className="block px-4 py-3 text-base font-medium text-gray-700 hover:bg-gray-50 rounded-lg transition">{t('Pricing')}</Link>
                            <div className="pt-2 border-t border-gray-100 mt-2">{renderCTAs(true)}</div>
                        </div>
                    </div>
                )}
            </header>

            {/* ============ HERO — AI / CLOUD / SECURE ============ */}
            <section className="relative pt-36 pb-24 md:pt-44 md:pb-32 overflow-hidden bg-[#0A1A14] text-white">
                {/* Background layers */}
                <div className="absolute inset-0 grid-bg opacity-20 pointer-events-none" />
                <div className="absolute -top-32 -left-24 w-[28rem] h-[28rem] rounded-full blur-[130px] opacity-30 floaty pointer-events-none" style={{ backgroundColor: colors.primary }} />
                <div className="absolute -bottom-32 -right-24 w-[32rem] h-[32rem] rounded-full blur-[140px] opacity-25 floaty pointer-events-none" style={{ backgroundColor: colors.ai, animationDelay: '2s' }} />
                <div className="absolute top-1/3 right-1/3 w-72 h-72 rounded-full blur-[120px] opacity-15 pointer-events-none" style={{ backgroundColor: colors.cloud }} />

                <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                    {/* Left — copy */}
                    <div className="lg:col-span-6">
                        <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold mb-6 border border-white/10 bg-white/5 backdrop-blur">
                            <Sparkles className="h-3.5 w-3.5" style={{ color: colors.accent }} />
                            <span style={{ color: colors.accent }}>New</span>
                            <span className="text-white/80">· Gadaa AI Copilot reads Amharic invoices</span>
                        </div>

                        <h1 className="text-4xl md:text-5xl lg:text-[3.6rem] font-black leading-[1.08] tracking-tight">
                            The <span className="bg-clip-text text-transparent" style={{ backgroundImage: grad(colors.primary, colors.accent) }}>modern AI cloud ERP</span> built for Ethiopian businesses.
                        </h1>

                        <p className="mt-5 text-base md:text-lg text-white/75 max-w-xl leading-relaxed">
                            Accounting in Birr with VAT & TIN, real estate, POS, HRM, payroll and agribusiness modules — unified on a secured cloud platform running across all 11 regions, supercharged by an AI copilot that speaks Amharic.
                        </p>

                        <div className="mt-8 flex flex-col sm:flex-row gap-3">
                            <button onClick={() => go(primaryHref)} className="group inline-flex items-center justify-center px-7 py-4 rounded-xl text-sm font-bold text-white shadow-2xl transition hover:-translate-y-0.5" style={{ background: grad(colors.primary, colors.accent) }}>
                                {primaryLabel}
                                <ArrowRight className="ml-2 h-4 w-4 transition-transform group-hover:translate-x-1" />
                            </button>
                            <button onClick={() => go(route('login'))} className="inline-flex items-center justify-center px-7 py-4 rounded-xl text-sm font-bold border border-white/15 text-white hover:bg-white/10 backdrop-blur transition">
                                {t('Request a Demo')}
                            </button>
                        </div>

                        <div className="mt-7 flex flex-wrap gap-x-5 gap-y-2.5">
                            {TRUST.map((b) => (
                                <div key={b} className="flex items-center gap-1.5 text-xs font-medium text-white/75">
                                    <Check className="h-3.5 w-3.5 flex-shrink-0" style={{ stroke: colors.accent, strokeWidth: 3 }} />
                                    {b}
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Right — live dashboard mock with AI spotlight */}
                    <div className="lg:col-span-6 relative">
                        <HeroMock colors={colors} grad={grad} />

                        {/* Floating AI Copilot card */}
                        <div className="absolute -bottom-6 -left-6 hidden sm:flex items-center gap-3 bg-white text-gray-900 px-4 py-3 rounded-2xl shadow-2xl border border-gray-100 floaty">
                            <div className="w-10 h-10 rounded-xl flex items-center justify-center text-white shadow-md" style={{ background: grad('#8B5CF6', '#6366F1') }}>
                                <Bot className="h-5 w-5" />
                            </div>
                            <div className="flex flex-col">
                                <span className="text-xs font-bold">Gadaa AI Copilot</span>
                                <span className="text-[10px] text-gray-500">"Show me VAT collected in Tikimt" · ✓ ETB 248,500</span>
                            </div>
                        </div>

                        {/* Floating security card */}
                        <div className="absolute -top-6 -right-6 hidden sm:flex items-center gap-3 bg-white text-gray-900 px-4 py-3 rounded-2xl shadow-2xl border border-gray-100 floaty" style={{ animationDelay: '1.5s' }}>
                            <div className="w-10 h-10 rounded-xl flex items-center justify-center text-white" style={{ background: grad(colors.primary, colors.secondary) }}>
                                <ShieldCheck className="h-5 w-5" />
                            </div>
                            <div className="flex flex-col">
                                <span className="text-xs font-bold">AES-256 · TLS 1.3</span>
                                <span className="text-[10px] text-gray-500">Data encrypted end-to-end</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Trust strip */}
                <div className="relative mt-20 border-t border-white/10 pt-6">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                        {STATS.map((s) => (
                            <div key={s.label} className="group">
                                <div className="text-3xl md:text-4xl font-black tracking-tight bg-clip-text text-transparent" style={{ backgroundImage: grad(colors.primary, colors.accent) }}>{s.value}</div>
                                <div className="mt-1 text-xs text-white/60 font-semibold uppercase tracking-wider">{s.label}</div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ============ THREE PILLARS — AI / CLOUD / SECURE ============ */}
            <section className="py-24 bg-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="max-w-3xl mx-auto text-center mb-16">
                        <span className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold tracking-wide uppercase" style={{ backgroundColor: `${colors.primary}12`, color: colors.primary }}>
                            <Waypoints className="h-3.5 w-3.5" /> {t('Why GadaaCloud')}
                        </span>
                        <h2 className="mt-4 text-3xl md:text-4xl lg:text-5xl font-black tracking-tight">Three pillars. One platform for Ethiopian growth.</h2>
                        <p className="mt-4 text-lg text-gray-600 leading-relaxed">Not an old ERP with AI bolted on. We built GadaaCloud from the ground up around AI, cloud and security — tailored for Ethiopian businesses.</p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
                        {PILLARS.map((p, idx) => {
                            const Icon = p.icon;
                            return (
                                <div key={p.key} className="group relative rounded-3xl p-8 bg-white border border-gray-100 shadow-[0_8px_30px_-12px_rgba(0,0,0,0.12)] hover:shadow-2xl transition-all duration-500 hover:-translate-y-2 overflow-hidden">
                                    <div className="absolute -top-16 -right-16 w-40 h-40 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500" style={{ backgroundColor: `${p.color}10` }} />
                                    <div className="relative">
                                        <div className="w-14 h-14 rounded-2xl flex items-center justify-center text-white shadow-lg transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-3" style={{ background: pillarGrad(p.key) }}>
                                            <Icon className="h-7 w-7" />
                                        </div>
                                        <div className="mt-5 text-xs font-bold uppercase tracking-widest" style={{ color: p.color }}>{p.label}</div>
                                        <h3 className="mt-1 text-2xl font-extrabold tracking-tight">{p.title}</h3>
                                        <p className="text-sm text-gray-500 mb-5">{p.tagline}</p>
                                        <ul className="space-y-3">
                                            {p.points.map((pt) => (
                                                <li key={pt} className="flex gap-3 text-sm text-gray-700 leading-relaxed">
                                                    <Check className="h-4 w-4 mt-0.5 flex-shrink-0" style={{ stroke: p.color, strokeWidth: 3 }} />
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

            {/* ============ AI DEEP DIVE — dark ============ */}
            <section className="relative py-24 overflow-hidden bg-[#0B0F1A] text-white">
                <div className="absolute inset-0 grid-bg opacity-15 pointer-events-none" />
                <div className="absolute top-0 left-1/4 w-80 h-80 rounded-full blur-[120px] opacity-30 pointer-events-none" style={{ backgroundColor: colors.ai }} />
                <div className="absolute bottom-0 right-1/4 w-80 h-80 rounded-full blur-[120px] opacity-20 pointer-events-none" style={{ backgroundColor: colors.primary }} />

                <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                    <div>
                        <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold border border-white/10 bg-white/5">
                            <Brain className="h-3.5 w-3.5" style={{ color: colors.ai }} /> Gadaa AI Copilot
                        </div>
                        <h2 className="mt-5 text-3xl md:text-4xl lg:text-5xl font-black tracking-tight leading-tight">
                            Speak to your ERP in <span className="bg-clip-text text-transparent" style={{ backgroundImage: grad('#8B5CF6', '#EC4899') }}>Amharic or English</span>.
                        </h2>
                        <p className="mt-4 text-white/70 text-lg leading-relaxed max-w-xl">
                            Gadaa AI Copilot reads supplier invoices and bank statements, auto-categorizes transactions, predicts Birr cash flow, and answers natural-language questions — so your team spends less time on bookkeeping and more on the business.
                        </p>

                        <div className="mt-8 space-y-3">
                            {[
                                { icon: Receipt,   text: 'OCR parses Amharic & English receipts and supplier invoices automatically' },
                                { icon: TrendingUp, text: 'Predicts cash flow 30 days ahead using ETB transaction history' },
                                { icon: FileCheck, text: 'Drafts management reports in Amharic or English in seconds' },
                                { icon: Fingerprint,text: 'Suggests journal entries and flags anomalies for approval' }
                            ].map((r, i) => {
                                const Icon = r.icon;
                                return (
                                    <div key={i} className="flex items-start gap-3 group">
                                        <div className="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center border border-white/10 bg-white/5 group-hover:scale-110 transition-transform" style={{ color: colors.ai }}>
                                            <Icon className="h-5 w-5" />
                                        </div>
                                        <p className="text-white/85 text-sm leading-relaxed pt-2">{r.text}</p>
                                    </div>
                                );
                            })}
                        </div>

                        <button onClick={() => go(primaryHref)} className="mt-8 inline-flex items-center gap-2 px-7 py-3.5 rounded-xl text-sm font-bold text-white shadow-2xl transition hover:-translate-y-0.5" style={{ background: grad('#8B5CF6', '#6366F1') }}>
                            {t('Try AI Copilot')} <ArrowRight className="h-4 w-4" />
                        </button>
                    </div>

                    {/* AI chat mock */}
                    <div className="relative">
                        <div className="rounded-3xl bg-[#11172A] border border-white/10 shadow-2xl overflow-hidden">
                            <div className="flex items-center gap-2 px-5 py-4 border-b border-white/5 bg-white/5">
                                <div className="w-8 h-8 rounded-lg flex items-center justify-center text-white" style={{ background: grad('#8B5CF6', '#6366F1') }}><Bot className="h-4 w-4"/></div>
                                <div className="flex flex-col">
                                    <span className="text-sm font-bold">Gadaa AI Copilot</span>
                                    <span className="text-[10px] text-white/50 flex items-center gap-1">
                                        <span className="w-1.5 h-1.5 rounded-full animate-pulse" style={{ backgroundColor: '#22c55e' }}/> online · አማርኛ + English
                                    </span>
                                </div>
                                <Zap className="ml-auto h-4 w-4 text-white/40" />
                            </div>
                            <div className="p-5 space-y-4 max-h-[420px] overflow-hidden">
                                {/* user */}
                                <div className="flex justify-end">
                                    <div className="max-w-[80%] px-4 py-2.5 rounded-2xl rounded-br-md bg-white/10 text-sm text-white/90">
                                        በጥቅምት ወር ስንት ቫት ተቀበልን?
                                                    </div>
                                </div>
                                {/* ai */}
                                <div className="flex justify-start gap-2.5">
                                    <div className="flex-shrink-0 w-7 h-7 rounded-lg flex items-center justify-center text-white" style={{ background: grad('#8B5CF6', '#6366F1') }}><Bot className="h-3.5 w-3.5"/></div>
                                    <div className="max-w-[80%] px-4 py-3 rounded-2xl rounded-bl-md bg-white/5 border border-white/10 text-sm text-white/85 leading-relaxed space-y-2">
                                        <p>በጥቅምት 2017 ዓ.ም. <strong className="text-white">ETB 248,500</strong> ቫት ተቀብሏል (15% የተመዘገበ)።</p>
                                        <p className="text-white/60 text-xs">Did reconcile against CBE statement #8821 · 99% match.</p>
                                        <div className="mt-2 grid grid-cols-3 gap-2">
                                            {[[ 'VAT Collected', '248,500'], ['VAT Paid', '132,000'], ['Net Payable', '116,500']].map((x) => (
                                                <div key={x[0]} className="px-2 py-2 rounded-lg bg-white/5 border border-white/10">
                                                    <div className="text-[9px] text-white/50 uppercase">{x[0]}</div>
                                                    <div className="text-xs font-bold text-white">{x[1]}</div>
                                                </div>
                                            ))}
                                        </div>
                                        <button className="mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold" style={{ background: grad('#8B5CF6', '#6366F1') }}>
                                            <FileCheck className="h-3.5 w-3.5"/> {t('Generate VAT report')}
                                        </button>
                                    </div>
                                </div>
                                {/* typing */}
                                <div className="flex justify-start gap-2.5">
                                    <div className="flex-shrink-0 w-7 h-7 rounded-lg flex items-center justify-center text-white" style={{ background: grad('#8B5CF6', '#6366F1') }}><Bot className="h-3.5 w-3.5"/></div>
                                    <div className="px-4 py-3 rounded-2xl rounded-bl-md bg-white/5 border border-white/10">
                                        <div className="flex gap-1">
                                            <span className="w-1.5 h-1.5 rounded-full bg-white/40 animate-bounce" style={{ animationDelay: '0ms' }}/>
                                            <span className="w-1.5 h-1.5 rounded-full bg-white/40 animate-bounce" style={{ animationDelay: '150ms' }}/>
                                            <span className="w-1.5 h-1.5 rounded-full bg-white/40 animate-bounce" style={{ animationDelay: '300ms' }}/>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div className="px-5 py-4 border-t border-white/5">
                                <div className="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/5 border border-white/10">
                                    <span className="flex-1 text-xs text-white/40 truncate">Ask in Amharic or English…</span>
                                    <Send className="h-4 w-4 text-white/40" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ============ CLOUD & SECURITY STATS BAND ============ */}
            <section className="py-16 bg-gray-50 border-y border-gray-100">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-2 md:grid-cols-4 gap-6">
                    {[
                        { icon: Cloud,    val: '99.9%',  label: 'Cloud uptime SLA' },
                        { icon: Server,   val: '11',     label: 'Regional zones in Ethiopia' },
                        { icon: Lock,     val: 'AES-256',label: 'Encryption at rest' },
                        { icon: Activity, val: '<50ms',  label: 'Avg. POS sync latency' }
                    ].map((s) => {
                        const Icon = s.icon;
                        return (
                            <div key={s.label} className="flex items-center gap-4 p-5 rounded-2xl bg-white border border-gray-100 shadow-sm">
                                <div className="w-12 h-12 rounded-xl flex items-center justify-center text-white" style={{ background: grad(colors.primary, colors.secondary) }}>
                                    <Icon className="h-6 w-6" />
                                </div>
                                <div>
                                    <div className="text-2xl font-black tracking-tight" style={{ color: colors.primary }}>{s.val}</div>
                                    <div className="text-xs text-gray-500 font-semibold uppercase tracking-wider">{s.label}</div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </section>

            {/* ============ MODULES ============ */}
            <section className="py-24 bg-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="max-w-3xl mx-auto text-center mb-16">
                        <span className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold tracking-wide uppercase" style={{ backgroundColor: `${colors.primary}12`, color: colors.primary }}>
                            <Cpu className="h-3.5 w-3.5" /> {t('Modules')}
                        </span>
                        <h2 className="mt-4 text-3xl md:text-4xl lg:text-5xl font-black tracking-tight">Every module your Ethiopian business needs — in Birr, built in.</h2>
                        <p className="mt-4 text-lg text-gray-600 leading-relaxed">From Addis Ababa HQ to regional branches, manage accounting, real estate, inventory, POS, HRM, payroll, projects and coffee exports in one secured platform.</p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        {MODULES.map((m, i) => {
                            const Icon = m.icon;
                            const isAi = m.icon === Bot;
                            return (
                                <div key={i} className="group relative rounded-2xl p-6 bg-white border border-gray-100 shadow-sm hover:shadow-2xl transition-all duration-500 hover:-translate-y-2 overflow-hidden">
                                    <div className="absolute -top-12 -right-12 w-24 h-24 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500" style={{ backgroundColor: `${isAi ? '#8B5CF6' : colors.primary}10` }} />
                                    <div className="relative">
                                        <div className="w-12 h-12 rounded-xl flex items-center justify-center text-white shadow-md transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3" style={{ background: isAi ? grad('#8B5CF6', '#6366F1') : grad(colors.primary, colors.secondary) }}>
                                            <Icon className="h-6 w-6" />
                                        </div>
                                        <h3 className="mt-5 text-lg font-extrabold tracking-tight">{m.title}</h3>
                                        <p className="mt-2 text-sm text-gray-600 leading-relaxed">{m.text}</p>
                                        <div className="mt-4 flex flex-wrap gap-1.5">
                                            {m.tags.map((tag) => (
                                                <span key={tag} className="px-2 py-0.5 rounded-md text-[10px] font-semibold border" style={{ borderColor: `${isAi ? '#8B5CF6' : colors.primary}30`, color: isAi ? '#8B5CF6' : colors.primary, backgroundColor: `${isAi ? '#8B5CF6' : colors.primary}08` }}>{tag}</span>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* ============ INDUSTRIES ============ */}
            <section className="py-24 bg-gradient-to-b from-white to-gray-50">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="max-w-3xl mx-auto text-center mb-16">
                        <span className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold tracking-wide uppercase" style={{ backgroundColor: `${colors.accent}25`, color: colors.secondary }}>
                            <Building2 className="h-3.5 w-3.5" /> {t('Industries')}
                        </span>
                        <h2 className="mt-4 text-3xl md:text-4xl lg:text-5xl font-black tracking-tight">Built for every Ethiopian sector.</h2>
                        <p className="mt-4 text-lg text-gray-600 leading-relaxed">Purpose-built workflows for the sectors that drive Ethiopia's economy.</p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {INDUSTRIES.map((it, i) => {
                            const Icon = it.icon;
                            return (
                                <div key={i} className="group relative bg-white p-8 rounded-2xl border border-gray-100 shadow-sm hover:shadow-2xl transition-all duration-500 hover:-translate-y-2 overflow-hidden">
                                    <div className="absolute -top-12 -right-12 w-32 h-32 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500" style={{ backgroundColor: `${colors.primary}08` }} />
                                    <div className="relative">
                                        <div className="w-14 h-14 rounded-2xl flex items-center justify-center mb-6 transition-transform group-hover:scale-110 group-hover:rotate-3 text-white" style={{ background: grad(colors.primary, colors.secondary) }}>
                                            <Icon className="h-7 w-7" />
                                        </div>
                                        <h3 className="text-xl font-bold tracking-tight">{it.title}</h3>
                                        <p className="mt-2 text-gray-600 leading-relaxed">{it.text}</p>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* ============ SECURITY DEEP DIVE ============ */}
            <section className="py-24 bg-[#0B1A14] text-white relative overflow-hidden">
                <div className="absolute inset-0 grid-bg opacity-15 pointer-events-none" />
                <div className="absolute -top-20 -left-20 w-80 h-80 rounded-full blur-[120px] opacity-25 pointer-events-none" style={{ backgroundColor: colors.primary }} />

                <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                    <div className="lg:col-span-5">
                        <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold border border-white/10 bg-white/5">
                            <ShieldCheck className="h-3.5 w-3.5" style={{ color: colors.primary }} /> Secured by design
                        </div>
                        <h2 className="mt-5 text-3xl md:text-4xl lg:text-5xl font-black tracking-tight leading-tight">
                            Your business data, <span className="bg-clip-text text-transparent" style={{ backgroundImage: grad(colors.primary, colors.accent) }}>locked down</span>.
                        </h2>
                        <p className="mt-4 text-white/70 text-lg leading-relaxed max-w-xl">
                            GadaaCloud is built on enterprise-grade security foundations so you can run Birr accounting and payroll with confidence — and satisfy audits for the Ethiopian tax authority.
                        </p>

                        <div className="mt-8 grid grid-cols-2 gap-3">
                            {[
                                { icon: Lock,        label: 'AES-256 at rest' },
                                { icon: KeyRound,    label: '2FA / Passkey' },
                                { icon: Fingerprint, label: 'RBAC + approvals' },
                                { icon: FileCheck,   label: 'Audit-ready logs' },
                                { icon: Server,      label: 'TLS 1.3 in transit' },
                                { icon: Cloud,       label: 'Nightly ETB backups' }
                            ].map((s, i) => {
                                const Icon = s.icon;
                                return (
                                    <div key={i} className="flex items-center gap-3 px-4 py-3 rounded-xl border border-white/10 bg-white/5">
                                        <Icon className="h-5 w-5" style={{ color: colors.accent }} />
                                        <span className="text-sm font-semibold text-white/90">{s.label}</span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <div className="lg:col-span-7">
                        <div className="rounded-3xl bg-[#0E2A20] border border-emerald-500/20 shadow-2xl p-8 md:p-10 relative overflow-hidden">
                            <div className="absolute -top-32 -right-32 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none" />
                            <div className="relative">
                                <div className="flex items-center gap-3 mb-6">
                                    <div className="w-10 h-10 rounded-xl flex items-center justify-center text-white" style={{ background: grad(colors.primary, colors.secondary) }}>
                                        <ShieldCheck className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <div className="text-sm font-bold">Security Posture · Live</div>
                                        <div className="text-[10px] text-white/40">Updated just now</div>
                                    </div>
                                    <span className="ml-auto inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/20">
                                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"/> All systems secure
                                    </span>
                                </div>

                                <div className="grid grid-cols-2 gap-4 mb-6">
                                    {[
                                        { icon: Lock,     label: 'Encryption', val: 'Active', color: '#22c55e' },
                                        { icon: KeyRound, label: '2FA Coverage', val: '94%', color: '#0ea5e9' },
                                        { icon: FileCheck,label: 'Audit Events', val: '12.4k', color: colors.accent },
                                        { icon: Server,   label: 'Backups Today', val: '3', color: '#8B5CF6' }
                                    ].map((s, i) => {
                                        const Icon = s.icon;
                                        return (
                                            <div key={i} className="p-4 rounded-2xl bg-white/5 border border-white/10">
                                                <Icon className="h-5 w-5 mb-2" style={{ color: s.color }} />
                                                <div className="text-2xl font-black tracking-tight">{s.val}</div>
                                                <div className="text-[10px] text-white/50 font-semibold uppercase tracking-wider">{s.label}</div>
                                            </div>
                                        );
                                    })}
                                </div>

                                {/* mini activity chart */}
                                <div className="p-4 rounded-2xl bg-white/5 border border-white/10">
                                    <div className="flex items-center justify-between mb-3">
                                        <div className="text-sm font-bold">Audit events · last 24h</div>
                                        <span className="text-xs text-emerald-400 flex items-center gap-1"><Activity className="h-3 w-3"/> nominal</span>
                                    </div>
                                    <div className="flex items-end gap-1.5 h-20">
                                        {[40, 65, 50, 80, 60, 90, 70, 95, 75, 85, 60, 88].map((h, i) => (
                                            <div key={i} className="flex-1 rounded-t-sm" style={{ height: `${h}%`, background: `linear-gradient(180deg, ${colors.primary}, ${colors.primary}40)` }} />
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ============ TESTIMONIALS MARQUEE ============ */}
            <section className="py-24 bg-white overflow-hidden">
                <div className="max-w-3xl mx-auto text-center mb-14 px-4">
                    <span className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold tracking-wide uppercase" style={{ backgroundColor: `${colors.accent}25`, color: colors.secondary }}>
                        <Star className="h-3 w-3" style={{ fill: colors.accent, stroke: colors.accent }} /> {t('Testimonials')}
                    </span>
                    <h2 className="mt-4 text-3xl md:text-4xl lg:text-5xl font-black tracking-tight">Trusted by Ethiopian businesses.</h2>
                    <p className="mt-4 text-lg text-gray-600 leading-relaxed">Real stories from real enterprises running on GadaaCloud.</p>
                </div>

                <div className="relative w-full overflow-hidden py-4">
                    <div className="absolute left-0 top-0 bottom-0 w-20 md:w-40 bg-gradient-to-r from-white to-transparent z-10 pointer-events-none" />
                    <div className="absolute right-0 top-0 bottom-0 w-20 md:w-40 bg-gradient-to-l from-white to-transparent z-10 pointer-events-none" />
                    <div className="marquee-track gap-6 px-3">
                        {[...TESTIMONIALS, ...TESTIMONIALS, ...TESTIMONIALS].map((item, i) => (
                            <div key={i} className="w-[320px] md:w-[400px] flex-shrink-0 bg-white p-8 rounded-3xl border border-gray-100 shadow-md hover:shadow-xl transition-all duration-500 relative overflow-hidden">
                                <Quote className="absolute top-6 right-6 h-10 w-10 opacity-5" style={{ color: colors.primary }} />
                                <div className="flex gap-1 mb-5">
                                    {Array.from({ length: 5 }).map((_, k) => <Star key={k} className="h-3.5 w-3.5" style={{ fill: colors.accent, stroke: colors.accent }} />)}
                                </div>
                                <blockquote className="text-sm md:text-base text-gray-700 leading-relaxed mb-6 italic">“{item.quote}”</blockquote>
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-xs" style={{ background: grad(colors.primary, colors.secondary) }}>
                                        {item.author.split(' ').map((n) => n[0]).slice(0, 2).join('')}
                                    </div>
                                    <div>
                                        <div className="font-bold text-sm text-gray-900">{item.author}</div>
                                        <div className="text-xs text-gray-500">{item.role} · {item.company}</div>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ============ FAQ ============ */}
            <section className="py-24 bg-gray-50 border-t border-gray-100">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center mb-14">
                        <span className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold tracking-wide uppercase" style={{ backgroundColor: `${colors.primary}12`, color: colors.primary }}>
                            {t('FAQ')}
                        </span>
                        <h2 className="mt-4 text-3xl md:text-4xl lg:text-5xl font-black tracking-tight">Frequently asked questions.</h2>
                    </div>

                    <div className="space-y-3">
                        {FAQS.map((item, i) => {
                            const open = openFaq === i;
                            return (
                                <div key={i} className={`rounded-2xl border bg-white transition-all overflow-hidden ${open ? 'border-transparent shadow-xl' : 'border-gray-150 hover:border-gray-200'}`} style={open ? { boxShadow: `0 10px 30px -10px ${colors.primary}30` } : undefined}>
                                    <button onClick={() => setOpenFq(open ? -1 : i)} className="w-full px-6 md:px-8 py-5 md:py-6 flex items-center justify-between gap-4 text-left">
                                        <span className="text-lg font-bold tracking-tight text-gray-900">{item.q}</span>
                                        <span className="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center transition-all" style={{ backgroundColor: open ? colors.primary : `${colors.primary}10`, color: open ? '#fff' : colors.primary }}>
                                            <ChevronDown className={`h-5 w-5 transition-transform ${open ? 'rotate-180' : ''}`} />
                                        </span>
                                    </button>
                                    <div className={`grid transition-all duration-300 ${open ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'}`}>
                                        <div className="overflow-hidden">
                                            <p className="px-6 md:px-8 pb-6 text-gray-600 leading-relaxed text-sm">{item.a}</p>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* ============ CTA ============ */}
            <section className="py-24 bg-white">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="relative overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-[#0B1A14] via-[#0B1A14] to-emerald-950 border border-emerald-500/20 shadow-2xl p-12 md:p-20 text-center">
                        <div className="absolute -top-40 -left-40 w-96 h-96 rounded-full blur-3xl pointer-events-none" style={{ backgroundColor: `${colors.primary}20` }} />
                        <div className="absolute -bottom-40 -right-40 w-96 h-96 rounded-full blur-3xl pointer-events-none" style={{ backgroundColor: `${colors.accent}15` }} />

                        <div className="relative z-10">
                            <div className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold border border-white/10 bg-white/5 text-white/90">
                                <span className="w-1.5 h-1.5 rounded-full animate-pulse" style={{ backgroundColor: colors.accent }} />
                                <Zap className="h-3 w-3" style={{ color: colors.accent }} /> GadaaCloud Enterprise ERP
                            </div>
                            <h2 className="mt-5 text-3xl md:text-5xl font-black text-white leading-tight tracking-tight max-w-3xl mx-auto">
                                ቢዝኔስዎን ዛሬ በዘመናዊ AI ያሳድጉ — Ready to scale your enterprise?
                            </h2>
                            <p className="mt-5 text-base md:text-lg text-white/70 max-w-2xl mx-auto leading-relaxed">
                                Join 5,000+ Ethiopian businesses running accounting, payroll, POS and exports on GadaaCloud — modern AI, reliable cloud, secured by design.
                            </p>
                            <div className="mt-10 flex flex-col sm:flex-row gap-4 justify-center items-center">
                                <button onClick={() => go(primaryHref)} className="inline-flex items-center justify-center text-white px-8 py-4 rounded-xl font-bold shadow-2xl transition hover:-translate-y-1" style={{ background: grad(colors.primary, colors.accent) }}>
                                    {primaryLabel} <ArrowRight className="ml-2 h-5 w-5" />
                                </button>
                                <button onClick={() => go(route('login'))} className="inline-flex items-center justify-center border-2 border-white/20 text-white px-8 py-4 rounded-xl font-bold hover:bg-white/10 backdrop-blur transition">
                                    {t('Contact Sales')}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ============ FOOTER ============ */}
            <Footer settings={settings} colors={colors} grad={grad} companyName={companyName} logoUrl={logoUrl} customPages={customPages} />

            <CookieConsent settings={adminAllSetting || {}} />
        </div>
    );
}

// =================== HERO MOCK DASHBOARD ===================
function HeroMock({ colors, grad }: { colors: any; grad: (a: string, b: string) => string }) {
    return (
        <div className="relative w-full">
            <div className="relative bg-white rounded-2xl shadow-2xl border border-white/20 overflow-hidden">
                {/* browser bar */}
                <div className="flex items-center gap-1.5 px-4 py-3 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                    <span className="w-2.5 h-2.5 rounded-full bg-red-400" />
                    <span className="w-2.5 h-2.5 rounded-full bg-yellow-400" />
                    <span className="w-2.5 h-2.5 rounded-full bg-green-400" />
                    <div className="ml-3 flex-1 max-w-xs px-3 py-1.5 rounded-full bg-white border border-gray-200 text-[10px] text-gray-400 truncate flex items-center gap-1.5">
                        <span className="w-1.5 h-1.5 rounded-full" style={{ backgroundColor: colors.primary }} />
                        app.gadaacloud.et/dashboard
                    </div>
                </div>

                {/* body */}
                <div className="flex">
                    {/* mini sidebar */}
                    <div className="hidden sm:flex flex-col w-14 bg-gradient-to-b from-gray-50 to-white py-4 border-r border-gray-100">
                        {[0,1,2,3,4,5,6].map((i) => {
                            const active = i === 0;
                            return <div key={i} className="mx-auto mb-3 w-7 h-7 rounded-lg transition-all" style={active ? { background: grad(colors.primary, colors.secondary), boxShadow:`0 4px 12px ${colors.primary}40` } : { backgroundColor: `${colors.primary}10` }} />;
                        })}
                    </div>

                    <div className="flex-1 p-5 md:p-6 bg-gradient-to-br from-gray-50/30 to-white">
                        <div className="flex items-center justify-between mb-5">
                            <div>
                                <div className="text-sm font-bold text-gray-900">Welcome back, Selam 👋</div>
                                <div className="text-[10px] text-gray-400">Today's snapshot · Live ETB data</div>
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="px-2.5 py-1 rounded-full text-[10px] font-semibold flex items-center gap-1" style={{ backgroundColor: `${colors.accent}25`, color: colors.secondary }}>
                                    <span className="w-1.5 h-1.5 rounded-full animate-pulse" style={{ backgroundColor: colors.primary }} /> Live
                                </div>
                                <div className="w-8 h-8 rounded-full" style={{ background: grad(colors.primary, colors.secondary) }} />
                            </div>
                        </div>

                        <div className="grid grid-cols-3 gap-3 mb-5">
                            {[
                                { label: 'Revenue (ETB)', value: '1,245,300', icon: Wallet, delta: '+12%' },
                                { label: 'Customers',     value: '3,420',     icon: Users,  delta: '+8%'  },
                                { label: 'Orders',        value: '892',       icon: ShoppingCart, delta: '+23%' }
                            ].map((k) => {
                                const Icon = k.icon;
                                return (
                                    <div key={k.label} className="p-3.5 rounded-xl border border-gray-100 bg-white shadow-sm">
                                        <div className="flex items-center justify-between mb-2.5">
                                            <div className="w-8 h-8 rounded-lg flex items-center justify-center" style={{ backgroundColor: `${colors.primary}15` }}>
                                                <Icon className="h-4 w-4" style={{ color: colors.primary }} />
                                            </div>
                                            <span className="text-[10px] font-bold flex items-center px-1.5 py-0.5 rounded-md" style={{ backgroundColor: `${colors.primary}10`, color: colors.primary }}>
                                                <TrendingUp className="h-2.5 w-2.5 mr-0.5" /> {k.delta}
                                            </span>
                                        </div>
                                        <div className="text-lg font-black text-gray-900 leading-none">{k.value}</div>
                                        <div className="text-[10px] text-gray-500 mt-1">{k.label}</div>
                                    </div>
                                );
                            })}
                        </div>

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
                                {[42,56,48,72,64,88,96].map((h, i) => (
                                    <div key={i} className="flex-1 flex flex-col items-center">
                                        <div className="w-full rounded-t-md opacity-80" style={{ height: `${h}%`, background: `linear-gradient(180deg, ${colors.primary}, ${colors.primary}40)` }} />
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="rounded-xl border border-gray-100 overflow-hidden bg-white">
                            {[
                                { t: 'CBE Bank · Telebirr reconciled', amt: '+ETB 245,000', dot: colors.primary },
                                { t: 'New order #8821 · Sidama Coffee', amt: 'ETB 84,300',   dot: colors.accent  },
                                { t: 'Habesha Garments · payslip run',   amt: 'ETB 32,150', dot: colors.primary }
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
        </div>
    );
}

// =================== FOOTER (inline) ===================
function Footer({ settings, colors, grad, companyName, logoUrl, customPages }: any) {
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
    const navItems = [
        { label: t('Product'), links: [
            { text: t('Modules'), href: '#' },
            { text: t('Pricing'), href: route('pricing.page') },
            { text: t('Industries'), href: '#' },
            { text: t('AI Copilot'), href: '#' }
        ]},
        { label: t('Company'), links: [
            { text: t('About'), href: '#' },
            { text: t('Contact'), href: '#' },
            ...customPages.map((p:any) => ({ text: p.title, href: route('custom-page.show', p.slug) }))
        ]},
        { label: t('Resources'), links: [
            { text: t('Documentation'), href: '#' },
            { text: t('Support'), href: '#' },
            { text: t('Security'), href: '#' },
            { text: t('Status'), href: '#' }
        ]}
    ];

    return (
        <footer className="bg-[#0A1A14] text-white pt-20 pb-10">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-12">
                    {/* brand + newsletter */}
                    <div className="lg:col-span-5">
                        <Link href={route('landing.page')} className="flex items-center gap-2.5">
                            {logoUrl
                                ? <img src={logoUrl} alt={companyName} className="h-9 w-auto" />
                                : <span className="text-2xl font-black tracking-tight" style={{ color: colors.primary }}>{companyName}</span>}
                        </Link>
                        <p className="mt-5 text-white/60 leading-relaxed max-w-md">
                            The modern AI cloud ERP built for Ethiopian businesses. Accounting in Birr, VAT/TIN compliance, real estate, POS, HRM, payroll, inventory and coffee exports — secured by design.
                        </p>

                        <form onSubmit={submit} className="mt-6">
                            <label className="text-sm font-semibold text-white/80">{t('Subscribe to our newsletter')}</label>
                            <div className="mt-2 flex gap-2 max-w-md">
                                <input
                                    type="email" value={email} onChange={(e) => setEmail(e.target.value)} required
                                    placeholder={t('you@business.et')}
                                    className="flex-1 px-4 py-2.5 rounded-xl bg-white/10 border border-white/15 text-sm text-white placeholder:text-white/40 focus:outline-none focus:border-[color:var(--brand)]"
                                />
                                <button type="submit" disabled={sending} className="inline-flex items-center justify-center px-5 py-2.5 rounded-xl text-sm font-bold text-white shadow-lg transition hover:-translate-y-0.5 disabled:opacity-60" style={{ background: grad(colors.primary, colors.secondary) }}>
                                    {sending ? '…' : t('Subscribe')}
                                </button>
                            </div>
                        </form>
                    </div>

                    {/* nav columns */}
                    {navItems.map((col) => (
                        <div key={col.label} className="lg:col-span-2">
                            <div className="text-sm font-bold mb-4">{col.label}</div>
                            <ul className="space-y-3">
                                {col.links.map((l) => (
                                    <li key={l.text}>
                                        <Link href={l.href} className="text-sm text-white/60 hover:text-white transition">{l.text}</Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}

                    {/* contact */}
                    <div className="lg:col-span-3">
                        <div className="text-sm font-bold mb-4">{t('Contact')}</div>
                        <ul className="space-y-3 text-sm text-white/60">
                            <li className="flex items-center gap-2.5"><MapPin className="h-4 w-4" style={{ color: colors.accent }} /> Addis Ababa, Ethiopia</li>
                            <li className="flex items-center gap-2.5"><Phone className="h-4 w-4" style={{ color: colors.accent }} /> +251 11 xxx xxxx</li>
                            <li className="flex items-center gap-2.5"><Mail className="h-4 w-4" style={{ color: colors.accent }} /> hello@gadaacloud.et</li>
                            <li className="flex items-center gap-2.5"><Globe className="h-4 w-4" style={{ color: colors.accent }} /> gadaacloud.et</li>
                        </ul>
                    </div>
                </div>

                <div className="mt-12 pt-8 border-t border-white/10 flex flex-col md:flex-row items-center justify-between gap-4">
                    <p className="text-xs text-white/50">© {year} {companyName}. {t('All rights reserved.')} · {t('Built in Ethiopia')} 🇪🇹</p>
                    <div className="flex items-center gap-2 text-xs text-white/50">
                        <ShieldCheck className="h-3.5 w-3.5" style={{ color: colors.primary }} />
                        {t('Secured by AES-256 · TLS 1.3 · VAT/TIN compliant')}
                    </div>
                </div>
            </div>
        </footer>
    );
}