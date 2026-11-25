import React, { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { ShieldCheck, LockKeyhole, CloudCog, MapPin, Phone, Mail, Globe } from 'lucide-react';
import { getAdminSetting, getImagePath } from '@/utils/helpers';
import { SecurityHexPattern } from './CloudSecurityPatterns';

interface SiteFooterProps {
    settings?: any;
}

export default function SiteFooter({ settings = {} }: SiteFooterProps) {
    const { t } = useTranslation();
    const [email, setEmail] = useState('');
    const [sending, setSending] = useState(false);

    const companyName = settings?.company_name || 'GadaaCloud';
    const customPages = settings?.custom_pages || [];
    const themeMode = getAdminSetting('theme_mode') || 'light';
    const logoKey = themeMode === 'dark' ? 'logo_light' : 'logo_dark';
    const logoPath = getAdminSetting(logoKey);
    const logoUrl = logoPath ? getImagePath(logoPath) : null;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!email) return;
        setSending(true);
        router.post(route('newsletter.subscribe'), { email }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('Subscribed!'));
                setEmail('');
            },
            onError: () => toast.error(t('Subscription failed')),
            onFinish: () => setSending(false)
        });
    };

    const year = new Date().getFullYear();

    return (
        <footer className="bg-[#031B13] text-slate-300 pt-10 pb-8 border-t border-emerald-900/50 relative overflow-hidden">
            <SecurityHexPattern className="absolute inset-0 opacity-60" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-8 text-left">
                    {/* Brand Info & Newsletter (4 Columns) */}
                    <div className="lg:col-span-4 space-y-3">
                        <Link href={route('landing.page')} className="flex items-center gap-2">
                            {logoUrl ? (
                                <img src={logoUrl} alt={companyName} className="h-8 w-auto" />
                            ) : (
                                <div className="flex items-center gap-2">
                                    <div className="w-8 h-8 rounded-lg bg-[#00A76F] flex items-center justify-center text-white font-extrabold text-base shadow-sm">
                                        G
                                    </div>
                                    <span className="font-extrabold text-lg text-white tracking-tight">
                                        Gadaa<span className="text-[#34D399]">Cloud</span> ERP
                                    </span>
                                </div>
                            )}
                        </Link>
                        <p className="text-xs text-slate-400 leading-relaxed max-w-sm">
                            Modern AI cloud ERP for Ethiopian enterprises. Birr accounting, MoR EIMS Directive 1142 e-invoicing, Ge'ez dual calendar, offline POS, and coffee export logistics.
                        </p>

                        <form onSubmit={submit} className="pt-1">
                            <label className="text-xs font-bold text-white block mb-1.5">{t('Subscribe to Updates')}</label>
                            <div className="flex gap-2 max-w-sm">
                                <input
                                    type="email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    required
                                    placeholder={t('you@company.et')}
                                    className="flex-1 px-3 py-2 rounded-xl bg-emerald-950/70 border border-emerald-800/60 text-xs text-white placeholder:text-slate-500 focus:outline-none focus:border-[#00A76F]"
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

                    {/* Column 1: Platform */}
                    <div className="lg:col-span-2 space-y-2.5">
                        <h4 className="text-xs font-bold text-white uppercase tracking-wider">{t('Platform')}</h4>
                        <ul className="space-y-1.5 text-xs text-slate-400">
                            <li><Link href={`${route('landing.page')}#pillars`} className="hover:text-[#34D399] transition-colors">{t('MoR EIMS Invoicing')}</Link></li>
                            <li><Link href={`${route('landing.page')}#ai-copilot`} className="hover:text-[#34D399] transition-colors">{t('Amharic AI Copilot')}</Link></li>
                            <li><Link href={`${route('landing.page')}#pillars`} className="hover:text-[#34D399] transition-colors">{t('Ge\'ez Dual Calendar')}</Link></li>
                            <li><Link href={`${route('landing.page')}#workflows`} className="hover:text-[#34D399] transition-colors">{t('Workflow Engine')}</Link></li>
                        </ul>
                    </div>

                    {/* Column 2: Solutions */}
                    <div className="lg:col-span-2 space-y-2.5">
                        <h4 className="text-xs font-bold text-white uppercase tracking-wider">{t('Solutions')}</h4>
                        <ul className="space-y-1.5 text-xs text-slate-400">
                            <li><Link href={`${route('landing.page')}#industries`} className="hover:text-[#34D399] transition-colors">{t('Coffee & Export')}</Link></li>
                            <li><Link href={`${route('landing.page')}#industries`} className="hover:text-[#34D399] transition-colors">{t('Real Estate')}</Link></li>
                            <li><Link href={`${route('landing.page')}#industries`} className="hover:text-[#34D399] transition-colors">{t('Manufacturing')}</Link></li>
                            <li><Link href={`${route('landing.page')}#industries`} className="hover:text-[#34D399] transition-colors">{t('Retail & Cafes')}</Link></li>
                        </ul>
                    </div>

                    {/* Column 3: Company */}
                    <div className="lg:col-span-2 space-y-2.5">
                        <h4 className="text-xs font-bold text-white uppercase tracking-wider">{t('Company')}</h4>
                        <ul className="space-y-1.5 text-xs text-slate-400">
                            <li><Link href={route('pricing.page')} className="hover:text-[#34D399] transition-colors">{t('Pricing Plans')}</Link></li>
                            <li><Link href={`${route('landing.page')}#security`} className="hover:text-[#34D399] transition-colors">{t('Cloud Security')}</Link></li>
                            <li><Link href={`${route('landing.page')}#faq`} className="hover:text-[#34D399] transition-colors">{t('Help & FAQs')}</Link></li>
                            {(customPages || []).map((p: any) => (
                                <li key={p.slug}>
                                    <Link href={route('custom-page.show', p.slug)} className="hover:text-[#34D399] transition-colors">
                                        {p.title}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>

                    {/* Column 4: Contact Info */}
                    <div className="lg:col-span-2 space-y-2.5">
                        <h4 className="text-xs font-bold text-white uppercase tracking-wider">{t('Contact')}</h4>
                        <ul className="space-y-2 text-xs text-slate-400">
                            <li className="flex items-center gap-2">
                                <MapPin className="h-3.5 w-3.5 text-[#34D399] flex-shrink-0" />
                                <span>{settings?.contact_address || 'Addis Ababa, Ethiopia'}</span>
                            </li>
                            <li className="flex items-center gap-2">
                                <Phone className="h-3.5 w-3.5 text-[#34D399] flex-shrink-0" />
                                <span>{settings?.contact_phone || '+251 911 00 00 00'}</span>
                            </li>
                            <li className="flex items-center gap-2">
                                <Mail className="h-3.5 w-3.5 text-[#34D399] flex-shrink-0" />
                                <span>{settings?.contact_email || 'info@gadaacloud.et'}</span>
                            </li>
                            <li className="flex items-center gap-2">
                                <Globe className="h-3.5 w-3.5 text-[#34D399] flex-shrink-0" />
                                <span>www.gadaacloud.et</span>
                            </li>
                        </ul>
                    </div>
                </div>

                {/* Bottom Trust Bar */}
                <div className="mt-8 pt-6 border-t border-emerald-900/50 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-400">
                    <p>© {year} {companyName}. {t('All rights reserved.')} · Built for Ethiopian Enterprise 🇪🇹</p>
                    <div className="flex flex-wrap items-center gap-4 text-[11px]">
                        <span className="flex items-center gap-1.5 text-emerald-400 font-semibold">
                            <CloudCog className="h-3.5 w-3.5 text-[#34D399]" />
                            99.9% Cloud SLA
                        </span>
                        <span className="flex items-center gap-1.5 text-emerald-400 font-semibold">
                            <LockKeyhole className="h-3.5 w-3.5 text-[#34D399]" />
                            AES-256 Encrypted
                        </span>
                        <span className="flex items-center gap-1.5 text-emerald-400 font-semibold">
                            <ShieldCheck className="h-3.5 w-3.5 text-[#34D399]" />
                            MoR EIMS 1142
                        </span>
                    </div>
                </div>
            </div>
        </footer>
    );
}
