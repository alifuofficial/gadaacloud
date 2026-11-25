import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    ArrowRight, Check, ChevronDown, Star,
    Brain, Cloud, ShieldCheck, LockKeyhole, ServerCog,
    TrendingUp, Building2, Bot, Send,
    Fingerprint, KeyRound, FileCheck, Receipt,
    CloudCog, DatabaseBackup, GlobeLock, FileLock2, ShieldPlus
} from 'lucide-react';
import { getAdminSetting, getImagePath } from '@/utils/helpers';
import CookieConsent from '@/components/cookie-consent';

// Shared & Section Subcomponents
import SiteHeader from './components/SiteHeader';
import SiteFooter from './components/SiteFooter';
import InteractiveStudio from './components/InteractiveStudio';
import WorkflowEngine from './components/WorkflowEngine';
import BentoPillars from './components/BentoPillars';
import IndustrySolutions from './components/IndustrySolutions';
import RoiCalculator from './components/RoiCalculator';
import {
    CloudTopologyPattern,
    SecurityHexPattern,
    CircuitDotPattern,
    CloudSecurityWatermark
} from './components/CloudSecurityPatterns';

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
    const { t } = useTranslation();
    const { adminAllSetting } = usePage().props as any;

    const c = settings?.config_sections?.colors || THEMES;
    const colors = { ...THEMES, ...c, primary: '#00A76F', secondary: '#059669', accent: '#34D399', dark: '#031B13' };

    const companyName = settings?.company_name || 'GadaaCloud';
    const isAuthenticated = !!settings?.is_authenticated;
    const enableRegistration = settings?.enable_registration !== false;

    const favicon = getAdminSetting('favicon');
    const faviconUrl = favicon ? getImagePath(favicon) : null;

    const [openFaq, setOpenFq] = useState<number>(0);

    // -------- DATA --------
    const STATS = [
        { value: 'ETB 14.8B+', label: 'Annual Business Volume Processed', icon: TrendingUp },
        { value: '99.9%', label: 'Secured Cloud Uptime SLA', icon: CloudCog },
        { value: '100%', label: 'MoR Directive 1142 Compliance', icon: ShieldCheck },
        { value: 'AES-256', label: 'Bank-Grade Vault Encryption', icon: LockKeyhole }
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

    const TRUST_BADGES = [
        { label: 'MoR Directive 1142', icon: ShieldCheck },
        { label: '99.9% Secured Cloud', icon: CloudCog },
        { label: 'AES-256 Encrypted', icon: LockKeyhole },
        { label: 'Ge\'ez Dual Calendar', icon: Check },
    ];

    // -------- COLORS HELPERS --------
    const grad = (a: string = colors.primary, b: string = colors.secondary) => `linear-gradient(135deg, ${a}, ${b})`;

    // -------- CMS CONFIG OVERRIDES --------
    const heroSec = settings?.config_sections?.sections?.hero || {};
    const heroTitle = heroSec.title || 'Run your entire enterprise in Birr. Compliant, secured, and powered by AI.';
    const heroSubtitle = heroSec.subtitle || 'Unified Birr accounting, MoR EIMS Directive 1142 e-invoicing, Ge\'ez dual calendar, offline POS, and coffee export logistics — hosted on a hardened multi-tenant cloud with an Amharic AI copilot.';

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

    return (
        <div className="min-h-screen bg-[#FDFDFD] text-slate-900 font-sans selection:bg-[#00A76F] selection:text-white relative overflow-x-hidden">
            <Head title={`${companyName} — Modern AI Cloud ERP for Ethiopian Enterprise`}>
                {faviconUrl && <link rel="icon" type="image/x-icon" href={faviconUrl} />}
            </Head>

            <style>{`
                @keyframes floaty { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
                @keyframes marquee { 0%{transform:translateX(0)} 100%{transform:translateX(-50%)} }
                .floaty { animation: floaty 6s ease-in-out infinite; }
                .marquee-track { display:flex; width:max-content; animation: marquee 35s linear infinite; }
                .marquee-track:hover { animation-play-state: paused; }
            `}</style>

            {/* ============ MINIMAL STICKY HEADER ============ */}
            <SiteHeader settings={settings} activePage="home" />

            {/* ============ HERO SECTION WITH CLOUD TOPOLOGY PATTERN ============ */}
            <section id="studio" className="relative pt-6 pb-10 md:pt-8 md:pb-12 overflow-hidden bg-gradient-to-b from-emerald-50/75 via-emerald-50/20 to-white">
                {/* Architectural Cloud & Circuit Patterns */}
                <CloudTopologyPattern className="absolute inset-0 opacity-85" />
                <div className="absolute top-0 right-1/4 w-[420px] h-[420px] bg-[#00A76F]/10 rounded-full blur-3xl pointer-events-none" />
                <div className="absolute top-1/3 left-0 w-[340px] h-[340px] bg-emerald-400/10 rounded-full blur-3xl pointer-events-none" />

                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-8 items-center relative z-10">
                    {/* Left Column - Headline & Copy */}
                    <div className="lg:col-span-5 space-y-5 text-center lg:text-left">
                        <div className="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-emerald-100/80 border border-emerald-200 text-xs font-bold text-[#00A76F] shadow-2xs">
                            <CloudCog className="h-3.5 w-3.5 text-[#00A76F]" />
                            <span>Secured AI Cloud ERP · Ethiopia</span>
                        </div>

                        <h1 className="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 tracking-tight leading-[1.12]">
                            {heroTitle}
                        </h1>

                        <p className="text-sm sm:text-base text-slate-600 max-w-xl mx-auto lg:mx-0 leading-relaxed font-normal">
                            {heroSubtitle}
                        </p>

                        <div className="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-3 pt-1">
                            <button onClick={() => go(primaryBtnLink)} className="w-full sm:w-auto px-6 py-3 rounded-xl text-sm font-bold text-white shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:scale-[1.02] transition-all duration-200 flex items-center justify-center gap-2" style={{ background: grad() }}>
                                {primaryBtnText} <ArrowRight className="h-4 w-4" />
                            </button>
                            <button onClick={() => go(secondaryBtnLink)} className="w-full sm:w-auto px-5 py-3 rounded-xl text-sm font-bold bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 transition-all duration-200 shadow-2xs">
                                {secondaryBtnText}
                            </button>
                        </div>

                        {/* Secured Cloud Trust Highlights */}
                        <div className="flex flex-wrap items-center justify-center lg:justify-start gap-x-4 gap-y-2 pt-1 text-xs font-semibold text-slate-600">
                            {TRUST_BADGES.map((item) => {
                                const BadgeIcon = item.icon;
                                return (
                                    <div key={item.label} className="flex items-center gap-1.5">
                                        <div className="w-4 h-4 rounded-full bg-emerald-100 flex items-center justify-center text-[#00A76F]">
                                            <BadgeIcon className="h-2.5 w-2.5 stroke-[2.5]" />
                                        </div>
                                        <span>{item.label}</span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Right Column - Interactive ERP Simulation Studio */}
                    <div className="lg:col-span-7 relative">
                        <div className="relative">
                            <div className="absolute inset-0 bg-gradient-to-tr from-[#00A76F]/20 to-emerald-400/20 rounded-3xl blur-2xl transform rotate-1 -z-10" />
                            <InteractiveStudio colors={colors} grad={grad} />
                        </div>
                    </div>
                </div>
            </section>

            {/* ============ COMPACT TRUST & CLOUD STATS BAND ============ */}
            <section className="bg-[#031B13] text-white py-8 relative overflow-hidden border-y border-emerald-900/60">
                <SecurityHexPattern className="absolute inset-0 opacity-75" />
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 space-y-6">
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-5 text-center">
                        {STATS.map((s) => {
                            const StatIcon = s.icon;
                            return (
                                <div key={s.label} className="p-3 rounded-2xl bg-emerald-950/50 border border-emerald-500/20 space-y-1">
                                    <div className="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-emerald-500/15 text-[#34D399] mb-1">
                                        <StatIcon className="h-3.5 w-3.5" />
                                    </div>
                                    <div className="text-2xl sm:text-3xl font-black text-[#34D399] tracking-tight">{s.value}</div>
                                    <div className="text-[11px] sm:text-xs font-medium text-emerald-200/80">{s.label}</div>
                                </div>
                            );
                        })}
                    </div>

                    {/* Ethiopian Economic Hubs Marquee */}
                    <div className="pt-3 border-t border-emerald-900/50 overflow-hidden">
                        <div className="marquee-track flex items-center gap-6 text-xs font-bold text-slate-300">
                            {[...TRUST_MARKETS, ...TRUST_MARKETS].map((hub, i) => (
                                <div key={i} className="flex items-center gap-2 whitespace-nowrap bg-emerald-950/70 px-3.5 py-1 rounded-full border border-emerald-800/50">
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

            {/* ============ BENTO GRID (4 CORE CLOUD & SECURITY PILLARS) ============ */}
            <BentoPillars colors={colors} grad={grad} />

            {/* ============ AI DEEP DIVE SECTION (DARK ENTERPRISE CONTRAST) ============ */}
            <section id="ai-copilot" className="py-10 sm:py-14 bg-gradient-to-br from-[#064E3B] via-[#043E2F] to-[#022C22] text-white relative overflow-hidden">
                <SecurityHexPattern className="absolute inset-0 opacity-65" />
                <div className="absolute top-0 right-0 w-[400px] h-[400px] bg-[#00A76F]/20 rounded-full blur-3xl pointer-events-none" />

                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-8 items-center relative z-10">
                    <div className="lg:col-span-6 space-y-4">
                        <div className="inline-flex items-center gap-2 px-3.5 py-1 rounded-full text-xs font-bold border border-emerald-400/30 bg-emerald-500/10 text-emerald-300">
                            <Brain className="h-3.5 w-3.5 text-[#34D399]" />
                            <span>Secured Gadaa AI Copilot</span>
                        </div>
                        <h2 className="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight leading-tight">
                            Speak to your ERP in <span className="text-[#34D399] bg-gradient-to-r from-[#34D399] to-emerald-200 bg-clip-text text-transparent">Amharic or English</span>.
                        </h2>
                        <p className="text-emerald-100/85 text-xs sm:text-sm leading-relaxed">
                            Gadaa AI Copilot parses vendor bills (Amharic + English OCR), auto-categorizes ledger entries, predicts Birr cash flow, and reconciles Commercial Bank of Ethiopia (CBE) & Telebirr inside your private tenant enclave.
                        </p>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-1">
                            {[
                                { icon: Receipt, text: 'Amharic & English OCR receipt parsing' },
                                { icon: TrendingUp, text: '30-day Birr cash flow prediction' },
                                { icon: FileCheck, text: 'Bilingual audit-ready VAT reports' },
                                { icon: Fingerprint, text: 'Anomaly & duplicate bill flagging' }
                            ].map((item, idx) => {
                                const Icon = item.icon;
                                return (
                                    <div key={idx} className="flex items-center gap-2.5 p-2.5 rounded-xl bg-emerald-950/50 border border-emerald-500/20">
                                        <div className="w-7 h-7 rounded-lg bg-emerald-500/15 border border-emerald-400/20 flex items-center justify-center text-[#34D399] flex-shrink-0">
                                            <Icon className="h-3.5 w-3.5" />
                                        </div>
                                        <span className="text-xs font-medium text-emerald-100/90 leading-snug">{item.text}</span>
                                    </div>
                                );
                            })}
                        </div>

                        <div className="pt-2">
                            <button onClick={() => go(primaryHref)} className="px-6 py-3 rounded-xl text-sm font-bold bg-[#00A76F] hover:bg-[#059669] text-white shadow-xl shadow-emerald-900/50 transition-all inline-flex items-center gap-2 hover:scale-[1.02]">
                                {t('Try AI Copilot Now')} <ArrowRight className="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    {/* AI Chat Demo Visualizer */}
                    <div className="lg:col-span-6">
                        <div className="rounded-3xl bg-[#032017] border border-emerald-500/30 shadow-2xl overflow-hidden text-left">
                            <div className="flex items-center justify-between px-5 py-3 border-b border-emerald-800/40 bg-emerald-950/60">
                                <div className="flex items-center gap-2.5">
                                    <div className="w-8 h-8 rounded-xl bg-[#00A76F] flex items-center justify-center text-white">
                                        <Bot className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <span className="text-xs sm:text-sm font-bold text-white block">Gadaa AI Assistant</span>
                                        <span className="text-[10px] text-emerald-400 flex items-center gap-1 font-medium">
                                            <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" /> Online · አማርኛ + English
                                        </span>
                                    </div>
                                </div>
                                <span className="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-[#34D399] border border-emerald-500/30 flex items-center gap-1">
                                    <LockKeyhole className="h-3 w-3" /> Private AI
                                </span>
                            </div>

                            <div className="p-5 space-y-3.5">
                                <div className="flex justify-end">
                                    <div className="max-w-[85%] px-3.5 py-2.5 rounded-2xl rounded-tr-xs bg-emerald-700/40 text-xs sm:text-sm text-white font-medium border border-emerald-500/30">
                                        በጥቅምት ወር ስንት ቫት (VAT) ተቀበልን?
                                    </div>
                                </div>

                                <div className="flex justify-start gap-2.5">
                                    <div className="w-7 h-7 rounded-lg bg-[#00A76F] flex items-center justify-center text-white flex-shrink-0 mt-1">
                                        <Bot className="h-4 w-4" />
                                    </div>
                                    <div className="max-w-[88%] px-3.5 py-3 rounded-2xl rounded-tl-xs bg-emerald-900/60 border border-emerald-500/25 text-xs text-emerald-100 space-y-2.5">
                                        <p className="leading-relaxed">
                                            በጥቅምት 2017 ዓ.ም. <strong className="text-white font-bold">ETB 248,500.00</strong> ቫት ተቀብሏል።
                                        </p>
                                        <div className="grid grid-cols-3 gap-2 text-center">
                                            <div className="p-2 rounded-lg bg-emerald-950/70 border border-emerald-500/20">
                                                <span className="text-[9px] text-emerald-300/70 uppercase font-bold block">VAT Collected</span>
                                                <span className="text-xs font-bold text-white">248,500</span>
                                            </div>
                                            <div className="p-2 rounded-lg bg-emerald-950/70 border border-emerald-500/20">
                                                <span className="text-[9px] text-emerald-300/70 uppercase font-bold block">VAT Paid</span>
                                                <span className="text-xs font-bold text-white">132,000</span>
                                            </div>
                                            <div className="p-2 rounded-lg bg-emerald-950/70 border border-emerald-500/20">
                                                <span className="text-[9px] text-emerald-300/70 uppercase font-bold block">Net Tax Due</span>
                                                <span className="text-xs font-bold text-[#34D399]">116,500</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="pt-1">
                                    <div className="flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-emerald-950/80 border border-emerald-500/30 text-xs text-emerald-300/60">
                                        <span className="flex-1 truncate">Ask any business query in Amharic or English...</span>
                                        <Send className="h-3.5 w-3.5 text-[#34D399]" />
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

            {/* ============ SECURITY POSTURE & CLOUD VAULT SECTION ============ */}
            <section id="security" className="py-10 sm:py-14 bg-[#031B13] text-white relative overflow-hidden border-t border-emerald-900/60">
                <SecurityHexPattern className="absolute inset-0 opacity-80" />
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-8 items-center relative z-10">
                    <div className="lg:col-span-5 space-y-4 text-left">
                        <div className="inline-flex items-center gap-2 px-3.5 py-1 rounded-full text-xs font-bold border border-emerald-500/30 bg-emerald-500/10 text-emerald-300">
                            <ShieldPlus className="h-3.5 w-3.5 text-[#34D399]" />
                            <span>Secured Enterprise Cloud</span>
                        </div>
                        <h2 className="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight leading-tight">
                            Your business data, <span className="text-[#34D399]">cryptographically locked</span>.
                        </h2>
                        <p className="text-emerald-100/80 text-xs sm:text-sm leading-relaxed">
                            GadaaCloud enforces defense-in-depth security protocols so your ledgers, MoR EIMS fiscal keys, and payroll remain isolated and encrypted.
                        </p>

                        <div className="grid grid-cols-2 gap-2.5 pt-1">
                            {[
                                { icon: LockKeyhole, label: 'AES-256 Encryption' },
                                { icon: KeyRound, label: '2FA & Passkey Login' },
                                { icon: GlobeLock, label: 'TLS 1.3 Transport' },
                                { icon: FileLock2, label: 'Immutable Audit Log' },
                                { icon: ServerCog, label: 'Multi-Zone Cloud' },
                                { icon: DatabaseBackup, label: 'Nightly Geo-Backups' }
                            ].map((sec, idx) => {
                                const Icon = sec.icon;
                                return (
                                    <div key={idx} className="flex items-center gap-2 p-2.5 rounded-xl bg-emerald-900/40 border border-emerald-500/20 text-xs font-semibold text-emerald-100">
                                        <Icon className="h-4 w-4 text-[#34D399] flex-shrink-0" />
                                        <span className="truncate">{sec.label}</span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <div className="lg:col-span-7">
                        <div className="rounded-3xl bg-[#022419] border border-emerald-500/30 p-6 shadow-2xl space-y-5 text-left relative overflow-hidden">
                            <CloudSecurityWatermark className="absolute -right-6 -bottom-6 w-48 h-40 text-[#34D399] opacity-25" />
                            <div className="flex items-center justify-between border-b border-emerald-800/40 pb-3.5 relative z-10">
                                <div className="flex items-center gap-3">
                                    <div className="w-10 h-10 rounded-xl bg-[#00A76F] flex items-center justify-center text-white shadow-md">
                                        <ShieldCheck className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h4 className="text-sm font-bold text-white">Cloud Security Vault</h4>
                                        <span className="text-[10px] text-emerald-400 font-medium">All compliance & tenant isolation gates active</span>
                                    </div>
                                </div>
                                <span className="px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-500/20 text-[#34D399] border border-emerald-500/30">
                                    100% Hardened
                                </span>
                            </div>

                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 relative z-10">
                                {[
                                    { label: 'At-Rest Vault', val: 'AES-256', icon: LockKeyhole },
                                    { label: 'Tenant Scope', val: 'creatorId', icon: Fingerprint },
                                    { label: 'Cloud Uptime', val: '99.9%', icon: Cloud },
                                    { label: 'MoR EIMS', val: 'Signed', icon: ShieldCheck }
                                ].map((item, idx) => {
                                    const VIcon = item.icon;
                                    return (
                                        <div key={idx} className="p-3 rounded-xl bg-emerald-950/75 border border-emerald-500/20 text-center space-y-1">
                                            <VIcon className="h-4 w-4 text-[#34D399] mx-auto" />
                                            <div className="text-sm font-black text-white">{item.val}</div>
                                            <div className="text-[10px] font-bold text-emerald-400/80 uppercase">{item.label}</div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* ============ TESTIMONIALS ============ */}
            <section className="py-10 sm:py-14 bg-white overflow-hidden relative">
                <CircuitDotPattern className="absolute inset-0 opacity-40" />
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-7 relative z-10">
                    <div className="space-y-2 max-w-2xl mx-auto">
                        <span className="inline-flex items-center gap-2 px-3.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-50 text-[#00A76F] border border-emerald-100">
                            <Star className="h-3.5 w-3.5 fill-[#00A76F]" /> {t('Verified Customer Feedback')}
                        </span>
                        <h2 className="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                            Trusted by Ethiopian enterprises.
                        </h2>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                        {TESTIMONIALS.map((item, idx) => (
                            <div key={idx} className="bg-slate-50/85 rounded-2xl p-5 border border-slate-200/80 flex flex-col justify-between text-left space-y-3 shadow-2xs">
                                <div className="space-y-2.5">
                                    <div className="flex text-amber-400 gap-1">
                                        {[...Array(5)].map((_, i) => (
                                            <Star key={i} className="w-3.5 h-3.5 fill-amber-400 text-amber-400" />
                                        ))}
                                    </div>
                                    <p className="text-xs text-slate-700 italic leading-relaxed">"{item.quote}"</p>
                                </div>
                                <div className="pt-2.5 border-t border-slate-200/60 flex items-center gap-2.5">
                                    <div className="w-8 h-8 rounded-full bg-gradient-to-br from-[#00A76F] to-[#059669] text-white flex items-center justify-center text-xs font-bold shadow-sm">
                                        {item.author.charAt(0)}
                                    </div>
                                    <div>
                                        <h4 className="text-xs font-bold text-slate-900">{item.author}</h4>
                                        <span className="text-[10px] text-slate-500 font-medium">{item.role} · {item.company}</span>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ============ FAQ SECTION ============ */}
            <section id="faq" className="py-10 sm:py-14 bg-slate-50/50 border-t border-slate-200/50">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-7">
                    <div className="text-center space-y-2">
                        <span className="inline-flex items-center gap-2 px-3.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-50 text-[#00A76F] border border-emerald-100">
                            {t('FAQ')}
                        </span>
                        <h2 className="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                            Frequently asked questions.
                        </h2>
                    </div>

                    <div className="space-y-2.5">
                        {FAQS.map((item, idx) => {
                            const isOpen = openFaq === idx;
                            return (
                                <div key={idx} className="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden transition-all text-left">
                                    <button
                                        onClick={() => setOpenFq(isOpen ? -1 : idx)}
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

            {/* ============ COMPACT CTA CALLOUT ============ */}
            <section className="py-10 sm:py-12 bg-white">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="rounded-3xl bg-gradient-to-r from-[#00A76F] via-[#059669] to-emerald-800 text-white p-8 sm:p-10 text-center shadow-2xl space-y-5 relative overflow-hidden">
                        <CloudSecurityWatermark className="absolute -right-8 -top-6 w-52 h-44 text-white opacity-20" />
                        <div className="space-y-2.5 max-w-2xl mx-auto relative z-10">
                            <span className="px-3 py-1 rounded-full text-[11px] font-bold bg-white/20 text-white border border-white/20 uppercase tracking-widest inline-flex items-center gap-1.5">
                                <CloudCog className="h-3.5 w-3.5" /> GadaaCloud Secured ERP
                            </span>
                            <h2 className="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight leading-tight">
                                Ready to scale your Ethiopian enterprise?
                            </h2>
                            <p className="text-emerald-50 text-xs sm:text-sm max-w-xl mx-auto font-normal">
                                Join leading businesses operating across Ethiopia. Launch in minutes with our 14-day free trial.
                            </p>
                        </div>
                        <div className="flex flex-col sm:flex-row items-center justify-center gap-3 relative z-10">
                            <button onClick={() => go(primaryHref)} className="w-full sm:w-auto px-7 py-3 rounded-xl text-sm font-bold bg-white text-[#00A76F] hover:bg-slate-50 shadow-xl transition-all">
                                {primaryLabel}
                            </button>
                            <button onClick={() => go(route('pricing.page'))} className="w-full sm:w-auto px-7 py-3 rounded-xl text-sm font-bold bg-transparent border-2 border-white text-white hover:bg-white/10 transition-all">
                                {t('View Birr Pricing')}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            {/* ============ UNIFIED FOOTER ============ */}
            <SiteFooter settings={settings} />

            <CookieConsent settings={adminAllSetting || {}} />
        </div>
    );
}
