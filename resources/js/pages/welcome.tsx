import { Link, Head } from "@inertiajs/react";
import { PageProps } from "@/types";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import { 
    Check, ArrowRight, Star, Globe, Building, Briefcase, Shield, DollarSign, 
    Layers, Target, LineChart, BookOpen, Heart, Smile, Menu, X, ChevronRight, 
    Calculator, Users, ShoppingBag, Headphones, ClipboardList, ChevronDown, ChevronUp, Send,
    HelpCircle, Sparkles, MessageSquare, AlertCircle, FolderKanban
} from 'lucide-react';

export default function Welcome({ auth }: PageProps) {
    const { t } = useTranslation();
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [activeFaq, setActiveFaq] = useState<number | null>(null);

    const toggleFaq = (index: number) => {
        setActiveFaq(activeFaq === index ? null : index);
    };

    const faqs = [
        {
            q: "What is GadaaCloud ERP?",
            a: "GadaaCloud ERP is a comprehensive, cloud-based business management solution designed specifically to streamline operations, compliance, and growth for Ethiopian enterprises."
        },
        {
            q: "Is my data safe with GadaaCloud?",
            a: "Yes, GadaaCloud utilizes enterprise-grade security protocols, automatic daily backups, and encrypted cloud hosting to guarantee that your business information remains fully secure and accessible 24/7."
        },
        {
            q: "Can I try GadaaCloud before purchasing?",
            a: "Absolutely! We offer a 14-day fully featured free trial so you can evaluate the platform and see how it fits your business workflow before making a commitment."
        },
        {
            q: "Do you provide local support?",
            a: "Yes, we provide 24/7 dedicated local support in Ethiopia, including on-site setup, training, and virtual troubleshooting in Amharic, Afaan Oromoo, Tigrinya, and English."
        },
        {
            q: "How much does GadaaCloud cost?",
            a: "GadaaCloud features flexible plans tailored to business sizes. Our Free Plan starts at 0 Br/mo for basic operations, and Premium tiers offer scale-up capabilities with predictable monthly pricing."
        },
        {
            q: "Can GadaaCloud grow with my business?",
            a: "Yes, GadaaCloud is modular. You can start with basic invoicing or POS modules and dynamically activate advanced packages like Payroll, Double-Entry Accounting, or CRM as your company grows."
        }
    ];

    const testimonials = [
        {
            name: "Abebe Kebede",
            role: "CEO, Ethio Trading PLC",
            text: "GadaaCloud has transformed how we manage our business. The reports and insights are incredible.",
            img: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?fit=crop&w=80&h=80&q=80"
        },
        {
            name: "Hirut Tesfaye",
            role: "Operations Manager",
            text: "Finally, an ERP that understands Ethiopian business needs. Excellent support and easy to use.",
            img: "https://images.unsplash.com/photo-1534528741775-53994a69daeb?fit=crop&w=80&h=80&q=80"
        },
        {
            name: "Daniel Lema",
            role: "Finance Director",
            text: "The best investment we made for our company. Highly recommended for Ethiopian businesses.",
            img: "https://images.unsplash.com/photo-1500648767791-00dcc994a43e?fit=crop&w=80&h=80&q=80"
        }
    ];

    return (
        <div className="min-h-screen bg-[#FDFDFD] text-gray-900 font-sans selection:bg-[#008B5B] selection:text-white relative overflow-x-hidden">
            {/* Background Blob Accents */}
            <div className="absolute top-0 right-0 w-[500px] h-[500px] bg-gradient-to-br from-[#008B5B]/5 to-transparent rounded-full filter blur-3xl -z-10 pointer-events-none" />
            <div className="absolute top-[400px] left-0 w-[400px] h-[400px] bg-gradient-to-tr from-emerald-500/5 to-transparent rounded-full filter blur-3xl -z-10 pointer-events-none" />

            <Head title="GadaaCloud - Transform Your Ethiopian Business" />

            {/* Dotted Grid Pattern Helper */}
            <div className="absolute top-12 left-12 w-24 h-24 text-gray-200 pointer-events-none opacity-60">
                <svg width="100%" height="100%" fill="currentColor">
                    <pattern id="dots-1" x="0" y="0" width="16" height="16" patternUnits="userSpaceOnUse">
                        <circle cx="2" cy="2" r="1.5" />
                    </pattern>
                    <rect width="100%" height="100%" fill="url(#dots-1)" />
                </svg>
            </div>

            {/* Header Navigation */}
            <header className="sticky top-0 bg-white/80 backdrop-blur-md border-b border-gray-100 z-50 transition-all duration-200">
                <div className="max-w-7xl mx-auto px-6 h-18 flex items-center justify-between">
                    {/* Logo */}
                    <Link href="/" className="flex items-center gap-2 group">
                        <div className="w-9 h-9 rounded-lg bg-[#008B5B] flex items-center justify-center text-white font-bold text-lg shadow-md group-hover:scale-105 transition-transform duration-200">
                            G
                        </div>
                        <span className="font-extrabold text-xl tracking-tight text-gray-900">
                            Gadaa<span className="text-[#008B5B]">Cloud</span> <span className="text-xs font-semibold px-2 py-0.5 rounded bg-emerald-50 text-[#008B5B] border border-emerald-100 uppercase tracking-widest ml-1">ERP</span>
                        </span>
                    </Link>

                    {/* Desktop Menu */}
                    <nav className="hidden md:flex items-center gap-8 font-medium text-sm text-gray-600">
                        <a href="#features" className="hover:text-[#008B5B] transition-colors">{t('Products')}</a>
                        <a href="#solutions" className="hover:text-[#008B5B] transition-colors">{t('Solutions')}</a>
                        <a href="#industries" className="hover:text-[#008B5B] transition-colors">{t('Industries')}</a>
                        <Link href={route('plans.index')} className="hover:text-[#008B5B] transition-colors">{t('Pricing')}</Link>
                        <a href="#faq" className="hover:text-[#008B5B] transition-colors">{t('Resources')}</a>
                    </nav>

                    {/* Auth CTAs */}
                    <div className="hidden md:flex items-center gap-4">
                        {auth.user ? (
                            <Link href={route('dashboard')} className="flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#008B5B] text-white hover:bg-[#00754C] font-semibold text-sm shadow-md transition-all duration-200">
                                {t('Dashboard')} <ArrowRight className="w-4 h-4" />
                            </Link>
                        ) : (
                            <>
                                <Link href={route('login')} className="font-semibold text-sm text-gray-700 hover:text-[#008B5B] transition-colors">
                                    {t('Log in')}
                                </Link>
                                <Link href={route('register')} className="px-4 py-2 rounded-lg bg-[#008B5B] text-white hover:bg-[#00754C] font-semibold text-sm shadow-md transition-all duration-200 transform hover:translate-y-[-1px]">
                                    {t('Get Started')}
                                </Link>
                            </>
                        )}
                    </div>

                    {/* Mobile Menu Button */}
                    <button 
                        onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                        className="md:hidden p-2 rounded-lg hover:bg-gray-50 text-gray-600 focus:outline-none"
                    >
                        {mobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
                    </button>
                </div>

                {/* Mobile Menu Dropdown */}
                {mobileMenuOpen && (
                    <div className="md:hidden border-t border-gray-100 bg-white p-6 space-y-4 shadow-xl absolute top-18 left-0 w-full z-40 animate-in slide-in-from-top-4 duration-200">
                        <nav className="flex flex-col gap-4 font-medium text-sm text-gray-700">
                            <a href="#features" onClick={() => setMobileMenuOpen(false)} className="hover:text-[#008B5B]">{t('Products')}</a>
                            <a href="#solutions" onClick={() => setMobileMenuOpen(false)} className="hover:text-[#008B5B]">{t('Solutions')}</a>
                            <a href="#industries" onClick={() => setMobileMenuOpen(false)} className="hover:text-[#008B5B]">{t('Industries')}</a>
                            <Link href={route('plans.index')} onClick={() => setMobileMenuOpen(false)} className="hover:text-[#008B5B]">{t('Pricing')}</Link>
                            <a href="#faq" onClick={() => setMobileMenuOpen(false)} className="hover:text-[#008B5B]">{t('Resources')}</a>
                        </nav>
                        <div className="border-t border-gray-100 pt-4 flex flex-col gap-3">
                            {auth.user ? (
                                <Link href={route('dashboard')} className="w-full text-center py-2.5 rounded-lg bg-[#008B5B] text-white font-semibold text-sm">
                                    {t('Go to Dashboard')}
                                </Link>
                            ) : (
                                <>
                                    <Link href={route('login')} className="w-full text-center py-2.5 rounded-lg border border-gray-200 font-semibold text-sm text-gray-700">
                                        {t('Log in')}
                                    </Link>
                                    <Link href={route('register')} className="w-full text-center py-2.5 rounded-lg bg-[#008B5B] text-white font-semibold text-sm">
                                        {t('Get Started')}
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                )}
            </header>

            {/* Hero Section */}
            <section className="relative pt-8 pb-16 md:pt-16 md:pb-24">
                <div className="max-w-7xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                    {/* Left Column - Intro */}
                    <div className="lg:col-span-6 space-y-8 text-center lg:text-left">
                        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-100 text-xs font-semibold text-[#008B5B]">
                            <Sparkles className="w-3.5 h-3.5" /> {t('#1 Cloud ERP for Ethiopian Businesses')}
                        </div>
                        <h1 className="text-4xl md:text-5xl lg:text-6xl font-extrabold text-gray-900 tracking-tight leading-tight">
                            Transform Your <span className="text-[#008B5B] bg-gradient-to-r from-[#008B5B] to-emerald-600 bg-clip-text text-transparent">Ethiopian Business</span> with GadaaCloud
                        </h1>
                        <p className="text-lg text-gray-600 max-w-2xl mx-auto lg:mx-0 leading-relaxed">
                            The all-in-one cloud business management platform built for Ethiopian enterprises. Streamline operations, increase efficiency, and grow your business with confidence.
                        </p>
                        <div className="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4">
                            <Link href={route('register')} className="w-full sm:w-auto px-8 py-4 rounded-xl bg-[#008B5B] text-white hover:bg-[#00754C] font-bold shadow-lg shadow-[#008B5B]/20 hover:shadow-xl transition-all duration-200 text-center transform hover:scale-[1.01]">
                                {t('Start Free Trial')} →
                            </Link>
                            <Link href={route('plans.index')} className="w-full sm:w-auto px-8 py-4 rounded-xl bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 font-bold transition-all duration-200 text-center">
                                {t('Book a Demo')}
                            </Link>
                        </div>
                        {/* Benefits list */}
                        <div className="flex flex-wrap items-center justify-center lg:justify-start gap-x-6 gap-y-2 pt-2 text-xs font-medium text-gray-500">
                            <div className="flex items-center gap-1.5">
                                <Check className="w-4 h-4 text-[#008B5B] font-bold" /> {t('No Credit Card Required')}
                            </div>
                            <div className="flex items-center gap-1.5">
                                <Check className="w-4 h-4 text-[#008B5B] font-bold" /> {t('Easy Setup')}
                            </div>
                            <div className="flex items-center gap-1.5">
                                <Check className="w-4 h-4 text-[#008B5B] font-bold" /> {t('Local Support in Ethiopia')}
                            </div>
                        </div>
                    </div>

                    {/* Right Column - Mockup Display */}
                    <div className="lg:col-span-6 relative">
                        {/* Glowing Background Blob */}
                        <div className="absolute inset-0 bg-[#008B5B]/10 rounded-3xl filter blur-2xl transform rotate-3 -z-10" />
                        <div className="bg-white rounded-2xl border border-gray-100 shadow-2xl p-2 relative">
                            <img 
                                src="/images/hero-dashboard.png" 
                                alt="GadaaCloud SaaS Dashboard Mockup" 
                                className="w-full h-auto rounded-xl object-cover" 
                            />
                            {/* Floating Assistance Box */}
                            <div className="absolute -bottom-6 -right-4 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-100 p-3.5 flex items-center gap-3 animate-bounce duration-1000 max-w-[280px]">
                                <div className="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center text-[#008B5B]">
                                    <MessageSquare className="w-5 h-5" />
                                </div>
                                <div className="text-left">
                                    <span className="text-[11px] font-bold text-[#008B5B] block uppercase tracking-wider">{t('GadaaCloud Assistant')}</span>
                                    <span className="text-xs font-medium text-gray-600 block">{t('How can we help you today?')}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* Stat Counters Section */}
            <section className="bg-white py-12 border-y border-gray-100">
                <div className="max-w-7xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                    <div className="space-y-1">
                        <div className="text-3xl md:text-4xl font-extrabold text-[#008B5B]">5,000+</div>
                        <div className="text-sm font-semibold text-gray-500">{t('Happy Customers')}</div>
                    </div>
                    <div className="space-y-1">
                        <div className="text-3xl md:text-4xl font-extrabold text-[#008B5B]">11+</div>
                        <div className="text-sm font-semibold text-gray-500">{t('Industries Served')}</div>
                    </div>
                    <div className="space-y-1">
                        <div className="text-3xl md:text-4xl font-extrabold text-[#008B5B]">99.9%</div>
                        <div className="text-sm font-semibold text-gray-500">{t('Uptime Guarantee')}</div>
                    </div>
                    <div className="space-y-1">
                        <div className="text-3xl md:text-4xl font-extrabold text-[#008B5B]">24/7</div>
                        <div className="text-sm font-semibold text-gray-500">{t('Local Support')}</div>
                    </div>
                </div>
            </section>

            {/* Powerful Features Section */}
            <section id="features" className="py-20 bg-slate-50/50">
                <div className="max-w-7xl mx-auto px-6 space-y-12">
                    <div className="text-center space-y-4 max-w-3xl mx-auto">
                        <span className="text-xs font-bold text-[#008B5B] uppercase tracking-widest">{t('Powerful Features')}</span>
                        <h2 className="text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight">
                            Powerful Features Built for Ethiopian Enterprises
                        </h2>
                        <p className="text-sm text-gray-600">
                            Everything you need to run your business efficiently. Built specifically for Ethiopia.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                        {/* Accounting */}
                        <div className="bg-white rounded-xl border border-gray-100 p-6 hover:shadow-lg transition-all duration-300 space-y-4">
                            <div className="w-12 h-12 rounded-lg bg-emerald-50 flex items-center justify-center text-[#008B5B]">
                                <Calculator className="w-6 h-6" />
                            </div>
                            <h3 className="text-lg font-bold text-gray-900">{t('Accounting & Finance')}</h3>
                            <p className="text-sm text-gray-600 leading-relaxed">
                                Manage your finances with precision and real-time insights. Formatted to support Birr transactions and local bank imports.
                            </p>
                        </div>
                        {/* POS */}
                        <div className="bg-white rounded-xl border border-gray-100 p-6 hover:shadow-lg transition-all duration-300 space-y-4">
                            <div className="w-12 h-12 rounded-lg bg-emerald-50 flex items-center justify-center text-[#008B5B]">
                                <ShoppingBag className="w-6 h-6" />
                            </div>
                            <h3 className="text-lg font-bold text-gray-900">{t('Sales & POS')}</h3>
                            <p className="text-sm text-gray-600 leading-relaxed">
                                Streamline sales operations with smart POS and inventory sync. Multi-store ready with offline billing support.
                            </p>
                        </div>
                        {/* Inventory */}
                        <div className="bg-white rounded-xl border border-gray-100 p-6 hover:shadow-lg transition-all duration-300 space-y-4">
                            <div className="w-12 h-12 rounded-lg bg-emerald-50 flex items-center justify-center text-[#008B5B]">
                                <ClipboardList className="w-6 h-6" />
                            </div>
                            <h3 className="text-lg font-bold text-gray-900">{t('Inventory & Warehouse')}</h3>
                            <p className="text-sm text-gray-600 leading-relaxed">
                                Track inventory in real-time across multiple locations. Automate stock replenishments and track serials easily.
                            </p>
                        </div>
                        {/* CRM */}
                        <div className="bg-white rounded-xl border border-gray-100 p-6 hover:shadow-lg transition-all duration-300 space-y-4">
                            <div className="w-12 h-12 rounded-lg bg-emerald-50 flex items-center justify-center text-[#008B5B]">
                                <Heart className="w-6 h-6" />
                            </div>
                            <h3 className="text-lg font-bold text-gray-900">{t('CRM & Sales')}</h3>
                            <p className="text-sm text-gray-600 leading-relaxed">
                                Build stronger customer relationships and close more deals. Lead assignment, activity tracking, and client logs.
                            </p>
                        </div>
                        {/* HRM */}
                        <div className="bg-white rounded-xl border border-gray-100 p-6 hover:shadow-lg transition-all duration-300 space-y-4">
                            <div className="w-12 h-12 rounded-lg bg-emerald-50 flex items-center justify-center text-[#008B5B]">
                                <Users className="w-6 h-6" />
                            </div>
                            <h3 className="text-lg font-bold text-gray-900">{t('HRM & Payroll')}</h3>
                            <p className="text-sm text-gray-600 leading-relaxed">
                                Manage your workforce and payroll with ease. Integrated with Ethiopian income tax structures and pension laws.
                            </p>
                        </div>
                        {/* Projects */}
                        <div className="bg-white rounded-xl border border-gray-100 p-6 hover:shadow-lg transition-all duration-300 space-y-4">
                            <div className="w-12 h-12 rounded-lg bg-emerald-50 flex items-center justify-center text-[#008B5B]">
                                <FolderKanban className="w-6 h-6" />
                            </div>
                            <h3 className="text-lg font-bold text-gray-900">{t('Projects & Tasks')}</h3>
                            <p className="text-sm text-gray-600 leading-relaxed">
                                Plan, execute, and deliver projects on time. Time-tracking, task assignees, and Gantt charts for operational visibility.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            {/* Complete Business Solutions Section */}
            <section id="solutions" className="py-20 bg-white">
                <div className="max-w-7xl mx-auto px-6 space-y-12">
                    <div className="text-center space-y-4 max-w-3xl mx-auto">
                        <span className="text-xs font-bold text-[#008B5B] uppercase tracking-widest">{t('Complete Solutions')}</span>
                        <h2 className="text-3xl md:text-4xl font-extrabold text-gray-900 tracking-tight">
                            Complete Business Solutions for Ethiopia
                        </h2>
                        <p className="text-sm text-gray-600">
                            Integrated suites that work together seamlessly to grow your business.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                        {/* Accounting Suite */}
                        <div className="p-6 bg-slate-50 rounded-xl space-y-3">
                            <h3 className="text-base font-bold text-gray-900">{t('Accounting & Finance Suite')}</h3>
                            <p className="text-xs text-gray-600 leading-relaxed">Complete financial management with reporting and analytics. Includes double-entry journal mappings.</p>
                            <a href="#" className="inline-flex items-center gap-1 text-xs font-bold text-[#008B5B] hover:underline">{t('Learn more')} →</a>
                        </div>
                        {/* Retail Suite */}
                        <div className="p-6 bg-slate-50 rounded-xl space-y-3">
                            <h3 className="text-base font-bold text-gray-900">{t('Retail & Distribution Suite')}</h3>
                            <p className="text-xs text-gray-600 leading-relaxed">End-to-end retail and distribution management. Perfect for wholesale and multi-outlet supply chains.</p>
                            <a href="#" className="inline-flex items-center gap-1 text-xs font-bold text-[#008B5B] hover:underline">{t('Learn more')} →</a>
                        </div>
                        {/* CRM Suite */}
                        <div className="p-6 bg-slate-50 rounded-xl space-y-3">
                            <h3 className="text-base font-bold text-gray-900">{t('Customer Relationship Management')}</h3>
                            <p className="text-xs text-gray-600 leading-relaxed">Manage leads, customers, and relationships effectively. Track communication history effortlessly.</p>
                            <a href="#" className="inline-flex items-center gap-1 text-xs font-bold text-[#008B5B] hover:underline">{t('Learn more')} →</a>
                        </div>
                        {/* HR Suite */}
                        <div className="p-6 bg-slate-50 rounded-xl space-y-3">
                            <h3 className="text-base font-bold text-gray-900">{t('HR & Payroll')}</h3>
                            <p className="text-xs text-gray-600 leading-relaxed">Streamline HR operations, track employees attendance, and automate monthly payroll calculations.</p>
                            <a href="#" className="inline-flex items-center gap-1 text-xs font-bold text-[#008B5B] hover:underline">{t('Learn more')} →</a>
                        </div>
                        {/* Project Suite */}
                        <div className="p-6 bg-slate-50 rounded-xl space-y-3">
                            <h3 className="text-base font-bold text-gray-900">{t('Project & Task Management')}</h3>
                            <p className="text-xs text-gray-600 leading-relaxed">Plan, execute, and track projects with confidence. Manage milestones and billable hours.</p>
                            <a href="#" className="inline-flex items-center gap-1 text-xs font-bold text-[#008B5B] hover:underline">{t('Learn more')} →</a>
                        </div>
                    </div>
                </div>
            </section>

            {/* Built for Every Industry Section */}
            <section id="industries" className="py-16 bg-slate-50/50 border-t border-gray-100">
                <div className="max-w-7xl mx-auto px-6 text-center space-y-10">
                    <div className="space-y-2">
                        <span className="text-xs font-bold text-[#008B5B] uppercase tracking-widest">{t('Built for Every Industry')}</span>
                        <h2 className="text-2xl md:text-3xl font-extrabold text-gray-900 tracking-tight">Tailored Solutions for Your Specific Requirements</h2>
                    </div>

                    <div className="flex flex-wrap items-center justify-center gap-4">
                        <div className="px-5 py-3 rounded-lg bg-white border border-gray-100 text-sm font-semibold text-gray-700 shadow-sm flex items-center gap-2">
                            <ShoppingBag className="w-4 h-4 text-[#008B5B]" /> {t('Trading & Distribution')}
                        </div>
                        <div className="px-5 py-3 rounded-lg bg-white border border-gray-100 text-sm font-semibold text-gray-700 shadow-sm flex items-center gap-2">
                            <Building className="w-4 h-4 text-[#008B5B]" /> {t('Manufacturing')}
                        </div>
                        <div className="px-5 py-3 rounded-lg bg-white border border-gray-100 text-sm font-semibold text-gray-700 shadow-sm flex items-center gap-2">
                            <Globe className="w-4 h-4 text-[#008B5B]" /> {t('Retail & E-commerce')}
                        </div>
                        <div className="px-5 py-3 rounded-lg bg-white border border-gray-100 text-sm font-semibold text-gray-700 shadow-sm flex items-center gap-2">
                            <Layers className="w-4 h-4 text-[#008B5B]" /> {t('Construction')}
                        </div>
                        <div className="px-5 py-3 rounded-lg bg-white border border-gray-100 text-sm font-semibold text-gray-700 shadow-sm flex items-center gap-2">
                            <LineChart className="w-4 h-4 text-[#008B5B]" /> {t('Transport & Logistics')}
                        </div>
                        <div className="px-5 py-3 rounded-lg bg-white border border-gray-100 text-sm font-semibold text-gray-700 shadow-sm flex items-center gap-2">
                            <Users className="w-4 h-4 text-[#008B5B]" /> {t('NGOs & Cooperatives')}
                        </div>
                    </div>

                    <a href="#" className="inline-flex items-center gap-1.5 text-sm font-bold text-[#008B5B] hover:underline">
                        {t('View all industries')} →
                    </a>
                </div>
            </section>

            {/* Why Choose Us (Dark Green Section with Dots Pattern) */}
            <section className="bg-[#04281A] text-white py-20 relative overflow-hidden">
                {/* Dotted Grid Accents */}
                <div className="absolute right-8 top-8 w-24 h-24 text-emerald-800 pointer-events-none opacity-40">
                    <svg width="100%" height="100%" fill="currentColor">
                        <pattern id="dots-dark" x="0" y="0" width="16" height="16" patternUnits="userSpaceOnUse">
                            <circle cx="2" cy="2" r="1.5" />
                        </pattern>
                        <rect width="100%" height="100%" fill="url(#dots-dark)" />
                    </svg>
                </div>

                <div className="max-w-7xl mx-auto px-6 space-y-12 relative">
                    <div className="text-center space-y-4 max-w-3xl mx-auto">
                        <span className="text-xs font-bold text-emerald-400 uppercase tracking-widest">{t('Why Choose GadaaCloud?')}</span>
                        <h2 className="text-3xl md:text-4xl font-extrabold tracking-tight">
                            Built for Ethiopian Business — Why Choose Us?
                        </h2>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                        {/* Card 1 */}
                        <div className="space-y-3">
                            <div className="w-10 h-10 rounded-lg bg-emerald-900/50 border border-emerald-800 flex items-center justify-center text-emerald-400">
                                <Shield className="w-5 h-5" />
                            </div>
                            <h4 className="text-base font-bold">{t('Local Compliance')}</h4>
                            <p className="text-xs text-emerald-200/80 leading-relaxed">Built to meet Ethiopian tax laws and regulations (ERCA rules). Safe and audit-ready.</p>
                        </div>
                        {/* Card 2 */}
                        <div className="space-y-3">
                            <div className="w-10 h-10 rounded-lg bg-emerald-900/50 border border-emerald-800 flex items-center justify-center text-emerald-400">
                                <Globe className="w-5 h-5" />
                            </div>
                            <h4 className="text-base font-bold">{t('Amharic & Multi-Language')}</h4>
                            <p className="text-xs text-emerald-200/80 leading-relaxed">Support for Amharic, Afaan Oromoo, Tigrinya, and English interfaces seamlessly.</p>
                        </div>
                        {/* Card 3 */}
                        <div className="space-y-3">
                            <div className="w-10 h-10 rounded-lg bg-emerald-900/50 border border-emerald-800 flex items-center justify-center text-emerald-400">
                                <Calculator className="w-5 h-5" />
                            </div>
                            <h4 className="text-base font-bold">{t('Accounting by Ethiopian')}</h4>
                            <p className="text-xs text-emerald-200/80 leading-relaxed">Formatted to align with local workflows, including Birr currency formats and calendars.</p>
                        </div>
                        {/* Card 4 */}
                        <div className="space-y-3">
                            <div className="w-10 h-10 rounded-lg bg-emerald-900/50 border border-emerald-800 flex items-center justify-center text-emerald-400">
                                <DollarSign className="w-5 h-5" />
                            </div>
                            <h4 className="text-base font-bold">{t('Cost-effective')}</h4>
                            <p className="text-xs text-emerald-200/80 leading-relaxed">Flexible scaling options that let you activate only the packages you currently need.</p>
                        </div>
                        {/* Card 5 */}
                        <div className="space-y-3">
                            <div className="w-10 h-10 rounded-lg bg-emerald-900/50 border border-emerald-800 flex items-center justify-center text-emerald-400">
                                <Layers className="w-5 h-5" />
                            </div>
                            <h4 className="text-base font-bold">{t('Cloud Based & Secure')}</h4>
                            <p className="text-xs text-emerald-200/80 leading-relaxed">Enjoy high-speed backups and encrypted connections from any location or device.</p>
                        </div>
                        {/* Card 6 */}
                        <div className="space-y-3">
                            <div className="w-10 h-10 rounded-lg bg-emerald-900/50 border border-emerald-800 flex items-center justify-center text-emerald-400">
                                <Headphones className="w-5 h-5" />
                            </div>
                            <h4 className="text-base font-bold">{t('Local Support')}</h4>
                            <p className="text-xs text-emerald-200/80 leading-relaxed">Get 24/7 priority support from our dedicated engineer team located right in Addis Ababa.</p>
                        </div>
                        {/* Card 7 */}
                        <div className="space-y-3">
                            <div className="w-10 h-10 rounded-lg bg-emerald-900/50 border border-emerald-800 flex items-center justify-center text-emerald-400">
                                <Target className="w-5 h-5" />
                            </div>
                            <h4 className="text-base font-bold">{t('Scalable')}</h4>
                            <p className="text-xs text-emerald-200/80 leading-relaxed">Upgrade your users or data limits dynamically as your company operations expand.</p>
                        </div>
                        {/* Card 8 */}
                        <div className="space-y-3">
                            <div className="w-10 h-10 rounded-lg bg-emerald-900/50 border border-emerald-800 flex items-center justify-center text-emerald-400">
                                <Users className="w-5 h-5" />
                            </div>
                            <h4 className="text-base font-bold">{t('Trusted by Businesses')}</h4>
                            <p className="text-xs text-emerald-200/80 leading-relaxed">Used daily by thousands of local developers, consultants, and companies across Ethiopia.</p>
                        </div>
                    </div>
                </div>
            </section>

            {/* Testimonials Section */}
            <section className="py-20 bg-white">
                <div className="max-w-7xl mx-auto px-6 space-y-12">
                    <div className="text-center space-y-2">
                        <span className="text-xs font-bold text-[#008B5B] uppercase tracking-widest">{t('Trusted by Ethiopian Businesses')}</span>
                        <h2 className="text-3xl font-extrabold text-gray-900 tracking-tight">{t('What Our Customers Say')}</h2>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
                        {testimonials.map((t, idx) => (
                            <div key={idx} className="bg-slate-50 rounded-xl p-6 border border-gray-100 flex flex-col justify-between space-y-6">
                                <div className="space-y-3">
                                    <div className="flex text-amber-400">
                                        {[...Array(5)].map((_, i) => (
                                            <Star key={i} className="w-4 h-4 fill-current" />
                                        ))}
                                    </div>
                                    <p className="text-sm text-gray-700 italic leading-relaxed">"{t.text}"</p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <img src={t.img} alt={t.name} className="w-10 h-10 rounded-full object-cover" />
                                    <div className="text-left">
                                        <h4 className="text-xs font-bold text-gray-900">{t.name}</h4>
                                        <span className="text-[10px] text-gray-500 font-semibold">{t.role}</span>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* UI Previews (Powerful. Intuitive. Made for Ethiopia) */}
            <section className="py-20 bg-slate-50/50">
                <div className="max-w-7xl mx-auto px-6 space-y-10 text-center">
                    <div className="space-y-3">
                        <span className="text-xs font-bold text-[#008B5B] uppercase tracking-widest">{t('See GadaaCloud In Action')}</span>
                        <h2 className="text-3xl font-extrabold text-gray-900 tracking-tight">{t('Powerful. Intuitive. Made for Ethiopia.')}</h2>
                        <p className="text-sm text-gray-600 max-w-2xl mx-auto">{t('Explore GadaaCloud\'s modern interface and powerful features.')}</p>
                    </div>

                    {/* Miniature interface cards row */}
                    <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <div className="bg-white border rounded-xl p-4 shadow-sm space-y-2 hover:shadow-md transition-shadow">
                            <div className="h-16 bg-blue-50/50 rounded-lg flex items-center justify-center text-blue-500"><LineChart className="w-7 h-7" /></div>
                            <span className="text-xs font-bold block">{t('Dashboard Overview')}</span>
                        </div>
                        <div className="bg-white border rounded-xl p-4 shadow-sm space-y-2 hover:shadow-md transition-shadow">
                            <div className="h-16 bg-emerald-50/50 rounded-lg flex items-center justify-center text-emerald-500"><Calculator className="w-7 h-7" /></div>
                            <span className="text-xs font-bold block">{t('Financial Reports')}</span>
                        </div>
                        <div className="bg-white border rounded-xl p-4 shadow-sm space-y-2 hover:shadow-md transition-shadow">
                            <div className="h-16 bg-orange-50/50 rounded-lg flex items-center justify-center text-orange-500"><ClipboardList className="w-7 h-7" /></div>
                            <span className="text-xs font-bold block">{t('Inventory Management')}</span>
                        </div>
                        <div className="bg-white border rounded-xl p-4 shadow-sm space-y-2 hover:shadow-md transition-shadow">
                            <div className="h-16 bg-indigo-50/50 rounded-lg flex items-center justify-center text-indigo-500"><ShoppingBag className="w-7 h-7" /></div>
                            <span className="text-xs font-bold block">{t('Sales & POS')}</span>
                        </div>
                        <div className="bg-white border rounded-xl p-4 shadow-sm space-y-2 hover:shadow-md transition-shadow col-span-2 md:col-span-1">
                            <div className="h-16 bg-pink-50/50 rounded-lg flex items-center justify-center text-pink-500"><Users className="w-7 h-7" /></div>
                            <span className="text-xs font-bold block">{t('HR & Payroll')}</span>
                        </div>
                    </div>
                </div>
            </section>

            {/* FAQs Section */}
            <section id="faq" className="py-20 bg-white">
                <div className="max-w-7xl mx-auto px-6 space-y-12">
                    <div className="text-center space-y-2 max-w-2xl mx-auto">
                        <span className="text-xs font-bold text-[#008B5B] uppercase tracking-widest">{t('Frequently Asked Questions')}</span>
                        <h2 className="text-3xl font-extrabold text-gray-900 tracking-tight">{t('Got Questions? We\'ve Got Answers')}</h2>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
                        {/* FAQs Accordion Column 1 */}
                        <div className="space-y-4">
                            {faqs.slice(0, 3).map((item, idx) => (
                                <div key={idx} className="border border-gray-150 rounded-xl bg-white overflow-hidden shadow-sm">
                                    <button 
                                        onClick={() => toggleFaq(idx)}
                                        className="w-full flex items-center justify-between p-5 text-left text-sm font-bold text-gray-800 hover:bg-slate-50 transition-colors"
                                    >
                                        <span>{t(item.q)}</span>
                                        {activeFaq === idx ? <ChevronUp className="w-4 h-4 text-gray-500" /> : <ChevronDown className="w-4 h-4 text-gray-500" />}
                                    </button>
                                    {activeFaq === idx && (
                                        <div className="p-5 pt-0 text-xs leading-relaxed text-gray-600 border-t border-gray-50 bg-slate-50/30 text-left">
                                            {t(item.a)}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>

                        {/* FAQs Accordion Column 2 */}
                        <div className="space-y-4">
                            {faqs.slice(3, 6).map((item, idx) => {
                                const realIdx = idx + 3;
                                return (
                                    <div key={realIdx} className="border border-gray-150 rounded-xl bg-white overflow-hidden shadow-sm">
                                        <button 
                                            onClick={() => toggleFaq(realIdx)}
                                            className="w-full flex items-center justify-between p-5 text-left text-sm font-bold text-gray-800 hover:bg-slate-50 transition-colors"
                                        >
                                            <span>{t(item.q)}</span>
                                            {activeFaq === realIdx ? <ChevronUp className="w-4 h-4 text-gray-500" /> : <ChevronDown className="w-4 h-4 text-gray-500" />}
                                        </button>
                                        {activeFaq === realIdx && (
                                            <div className="p-5 pt-0 text-xs leading-relaxed text-gray-600 border-t border-gray-50 bg-slate-50/30 text-left">
                                                {t(item.a)}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>
            </section>

            {/* Bottom Hero / Call to Action */}
            <section className="py-16 bg-[#04281A] text-white">
                <div className="max-w-5xl mx-auto px-6 text-center space-y-8">
                    <h2 className="text-3xl md:text-4xl font-extrabold tracking-tight">Ready to Transform Your Ethiopian Business?</h2>
                    <p className="text-sm text-emerald-200/80 max-w-xl mx-auto">Join thousands of Ethiopian businesses already growing with GadaaCloud ERP.</p>
                    <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
                        <Link href={route('register')} className="w-full sm:w-auto px-8 py-4 rounded-xl bg-[#008B5B] text-white hover:bg-[#00754C] font-bold shadow-lg transition-all">
                            {t('Start Free Trial')}
                        </Link>
                        <Link href={route('plans.index')} className="w-full sm:w-auto px-8 py-4 rounded-xl bg-transparent border border-emerald-800 hover:bg-emerald-950/20 font-bold transition-all">
                            {t('Book a Demo')}
                        </Link>
                    </div>
                    {/* Trial checklists */}
                    <div className="flex justify-center items-center gap-6 text-xs text-emerald-300 font-semibold">
                        <span className="flex items-center gap-1.5"><Check className="w-3.5 h-3.5" /> {t('Free 14-Day Trial')}</span>
                        <span className="flex items-center gap-1.5"><Check className="w-3.5 h-3.5" /> {t('No Credit Card Required')}</span>
                        <span className="flex items-center gap-1.5"><Check className="w-3.5 h-3.5" /> {t('Setup in Minutes')}</span>
                    </div>
                </div>
            </section>

            {/* Footer */}
            <footer className="bg-[#0B1511] text-gray-400 py-16 border-t border-emerald-950/20">
                <div className="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-12 gap-10">
                    {/* Column 1 - Brand info */}
                    <div className="md:col-span-4 space-y-4 text-left">
                        <Link href="/" className="flex items-center gap-2">
                            <div className="w-8 h-8 rounded bg-[#008B5B] flex items-center justify-center text-white font-bold text-base shadow">G</div>
                            <span className="font-extrabold text-lg text-white">GadaaCloud ERP</span>
                        </Link>
                        <p className="text-xs leading-relaxed text-gray-400">
                            The leading cloud ERP solution built specifically for Ethiopian businesses.
                        </p>
                    </div>

                    {/* Column 2 - Products links */}
                    <div className="md:col-span-2 space-y-3 text-left">
                        <h4 className="text-xs font-bold text-white uppercase tracking-wider">{t('Products')}</h4>
                        <ul className="space-y-2 text-xs">
                            <li><a href="#features" className="hover:text-white transition-colors">{t('Accounting')}</a></li>
                            <li><a href="#features" className="hover:text-white transition-colors">{t('POS System')}</a></li>
                            <li><a href="#features" className="hover:text-white transition-colors">{t('Inventory')}</a></li>
                            <li><a href="#features" className="hover:text-white transition-colors">{t('HR & Payroll')}</a></li>
                            <li><a href="#features" className="hover:text-white transition-colors">{t('CRM')}</a></li>
                        </ul>
                    </div>

                    {/* Column 3 - Solutions links */}
                    <div className="md:col-span-2 space-y-3 text-left">
                        <h4 className="text-xs font-bold text-white uppercase tracking-wider">{t('Solutions')}</h4>
                        <ul className="space-y-2 text-xs">
                            <li><a href="#solutions" className="hover:text-white transition-colors">{t('Small Business')}</a></li>
                            <li><a href="#solutions" className="hover:text-white transition-colors">{t('Enterprise')}</a></li>
                            <li><a href="#solutions" className="hover:text-white transition-colors">{t('Retail')}</a></li>
                            <li><a href="#solutions" className="hover:text-white transition-colors">{t('Manufacturing')}</a></li>
                            <li><a href="#solutions" className="hover:text-white transition-colors">{t('Distribution')}</a></li>
                        </ul>
                    </div>

                    {/* Column 4 - Resources links */}
                    <div className="md:col-span-2 space-y-3 text-left">
                        <h4 className="text-xs font-bold text-white uppercase tracking-wider">{t('Resources')}</h4>
                        <ul className="space-y-2 text-xs">
                            <li><a href="#" className="hover:text-white transition-colors">{t('Documentation')}</a></li>
                            <li><a href="#" className="hover:text-white transition-colors">{t('Blog')}</a></li>
                            <li><a href="#" className="hover:text-white transition-colors">{t('Help Center')}</a></li>
                            <li><a href="#" className="hover:text-white transition-colors">{t('API')}</a></li>
                        </ul>
                    </div>

                    {/* Column 5 - Company links */}
                    <div className="md:col-span-2 space-y-3 text-left">
                        <h4 className="text-xs font-bold text-white uppercase tracking-wider">{t('Company')}</h4>
                        <ul className="space-y-2 text-xs">
                            <li><a href="#" className="hover:text-white transition-colors">{t('About Us')}</a></li>
                            <li><a href="#" className="hover:text-white transition-colors">{t('Careers')}</a></li>
                            <li><a href="#" className="hover:text-white transition-colors">{t('Partners')}</a></li>
                            <li><a href="#" className="hover:text-white transition-colors">{t('Contact Us')}</a></li>
                        </ul>
                    </div>
                </div>

                <div className="max-w-7xl mx-auto px-6 border-t border-emerald-950/20 mt-12 pt-8 flex flex-col sm:flex-row justify-between items-center gap-4 text-xs">
                    <span>&copy; {new Date().getFullYear()} GadaaCloud ERP. All rights reserved.</span>
                    <div className="flex gap-6">
                        <a href="#" className="hover:text-white transition-colors">{t('Privacy Policy')}</a>
                        <a href="#" className="hover:text-white transition-colors">{t('Terms of Service')}</a>
                    </div>
                </div>
            </footer>
        </div>
    );
}
