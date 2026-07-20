import { useState } from 'react';
import { getImagePath } from '@/utils/helpers';
import { useTranslation } from 'react-i18next';
import { Calendar, HelpCircle, Search, ShieldCheck, ArrowUpRight } from 'lucide-react';

interface GalleryProps {
    settings?: any;
}

const GALLERY_VARIANTS = {
    gallery1: { section: 'bg-white py-20', container: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8', title: 'text-3xl md:text-4xl font-bold text-gray-900 mb-6', subtitle: 'text-lg text-gray-600', layout: 'slider' },
    gallery2: { section: 'bg-gradient-to-b from-white via-gray-50/40 to-white py-24', container: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8', title: 'text-3xl md:text-4xl font-bold text-gray-900 mb-6', subtitle: 'text-lg text-gray-600', layout: 'bento' },
    gallery3: { section: 'bg-gradient-to-br from-gray-50 to-white py-20', container: 'max-w-6xl mx-auto px-4 sm:px-6 lg:px-8', title: 'text-3xl md:text-4xl font-bold text-gray-900 mb-6', subtitle: 'text-lg text-gray-600', layout: 'stacked' },
    gallery4: { section: 'bg-gray-900 py-20', container: 'max-w-full px-4 sm:px-6 lg:px-8', title: 'text-3xl md:text-4xl font-bold text-white mb-6', subtitle: 'text-lg text-gray-300', layout: 'carousel' },
    gallery5: { section: 'bg-white py-20', container: 'max-w-5xl mx-auto px-4 sm:px-6 lg:px-8', title: 'text-3xl md:text-4xl font-bold text-gray-900 mb-6', subtitle: 'text-lg text-gray-600', layout: 'lightbox' }
};

// Feature showcase set: maps each module screenshot to a real platform feature.
// Order/length matches the bento layout spans below — keep six features.
const FEATURE_ITEMS = [
    { image: 'packages/workdo/LandingPage/src/Resources/assets/img/accounting.png', title: 'Accounting & VAT', tag: 'Finance',     description: 'Birr ledger · CBE/Telebirr reconcile · TIN-ready invoices',        span: 'md:col-span-2 md:row-span-2', spanIdx: 0 },
    { image: 'packages/workdo/LandingPage/src/Resources/assets/img/pos.png',       title: 'Retail POS',       tag: 'Retail',      description: 'Offline-ready checkout · Amharic receipts · Telebirr payments',  span: 'md:col-span-2 md:row-span-1', spanIdx: 1 },
    { image: 'packages/workdo/LandingPage/src/Resources/assets/img/hrm.png',       title: 'HRM & Payroll',    tag: 'People',      description: 'Attendance · Birr payslips · labour-law compliant',                span: 'md:col-span-1 md:row-span-1', spanIdx: 2 },
    { image: 'packages/workdo/LandingPage/src/Resources/assets/img/crm.png',       title: 'CRM & Sales',      tag: 'Operations',  description: 'Leads · deals · customer history in Amharic',                     span: 'md:col-span-1 md:row-span-1', spanIdx: 3 },
    { image: 'packages/workdo/LandingPage/src/Resources/assets/img/project.png',   title: 'Projects & Tasks', tag: 'Operations',  description: 'Kanban · Gantt · time-tracking for service firms',                 span: 'md:col-span-2 md:row-span-1', spanIdx: 4 },
    { image: 'packages/workdo/LandingPage/src/Resources/assets/img/hero.png',      title: 'Unified Dashboard', tag: 'Finance',     description: 'Live ETB KPIs · Birr revenue charts · AI Copilot insights',         span: 'md:col-span-2 md:row-span-1', spanIdx: 5 }
];

const FILTER_CHIPS = ['All', 'Finance', 'Retail', 'People', 'Operations'] as const;

export default function Gallery({ settings }: GalleryProps) {
    const { t } = useTranslation();
    const sectionData = settings?.config_sections?.sections?.gallery || {};
    const variant = sectionData.variant || 'gallery2';
    const config = GALLERY_VARIANTS[variant as keyof typeof GALLERY_VARIANTS] || GALLERY_VARIANTS.gallery2;

    const title = sectionData.title || 'See GadaaCloud in Action';
    const subtitle = sectionData.subtitle || 'Real screenshots from the modules Ethiopian businesses use every day — from Birr accounting to retail POS across Addis Ababa and the regions.';
    const colors = settings?.config_sections?.colors || { primary: '#078930', secondary: '#054a2b', accent: '#fcdd09' };

    const [activeFilter, setActiveFilter] = useState<typeof FILTER_CHIPS[number]>('All');

    // Admin-overridden images take precedence (classic image grid, no captions).
    const adminImages = (sectionData.images?.filter((img: string) => img) || []);
    const hasAdminImages = adminImages.length > 0;

    const renderBento = () => {
        if (hasAdminImages) {
            return (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {adminImages.map((image, index) => (
                        <div key={index} className="group relative overflow-hidden rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-500">
                            <img
                                src={image.startsWith('http') ? image : getImagePath(image)}
                                alt={`Screenshot ${index + 1}`}
                                className="w-full h-64 object-cover group-hover:scale-105 transition-transform duration-500"
                            />
                            <div className="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity" />
                        </div>
                    ))}
                </div>
            );
        }

        // Feature bento grid (default showcase)
        return (
            <div className="grid grid-cols-1 md:grid-cols-4 md:auto-rows-[220px] gap-4 md:gap-5">
                {FEATURE_ITEMS.map((feature, index) => {
                    const isFiltered = activeFilter !== 'All' && feature.tag !== activeFilter;
                    return (
                        <div
                            key={index}
                            className={`group relative overflow-hidden rounded-3xl border border-gray-100 shadow-sm hover:shadow-2xl transition-all duration-500 transform-gpu hover:-translate-y-1 ${feature.span} ${
                                isFiltered ? 'opacity-30 saturate-50' : 'opacity-100'
                            }`}
                            style={index === 0 ? { boxShadow: `0 20px 50px -20px ${colors.primary}40` } : undefined}
                        >
                            {/* Image */}
                            <img
                                src={getImagePath(feature.image)}
                                alt={feature.title}
                                className="absolute inset-0 w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-110"
                            />

                            {/* Always-on label corner badge */}
                            <div className="absolute top-4 left-4 z-20 flex items-center gap-2">
                                <span
                                    className="w-7 h-7 rounded-xl flex items-center justify-center text-[11px] font-black text-white shadow-md"
                                    style={{ background: `linear-gradient(135deg, ${colors.primary}, ${colors.secondary})` }}
                                >
                                    {index + 1}
                                </span>
                                <span className="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-white/95 backdrop-blur-md text-gray-800 border border-white/40 shadow-sm">
                                    {feature.tag}
                                </span>
                            </div>

                            {/* Hover-only "expand" pill */}
                            <div className="absolute top-4 right-4 z-20 w-8 h-8 rounded-full bg-white/90 backdrop-blur-md border border-white/40 shadow-md flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 transform group-hover:rotate-0 -rotate-45 group-hover:translate-x-0 translate-x-2">
                                <ArrowUpRight className="h-4 w-4" style={{ color: colors.primary }} />
                            </div>

                            {/* Gradient overlay */}
                            <div
                                className="absolute inset-0 z-10 transition-opacity duration-500"
                                style={{
                                    background: `linear-gradient(180deg, rgba(0,0,0,0) 35%, rgba(0,0,0,0.45) 70%, rgba(0,0,0,0.85) 100%)`
                                }}
                            />

                            {/* Caption */}
                            <div className="absolute bottom-0 left-0 right-0 p-5 z-20">
                                <div
                                    className="text-[10px] font-bold tracking-widest uppercase mb-1.5 opacity-90"
                                    style={{ color: colors.accent }}
                                >
                                    {feature.tag}
                                </div>
                                <h3
                                    className="text-white font-bold leading-tight mb-1.5 transition-transform duration-500 group-hover:-translate-y-0.5"
                                    style={{ fontSize: index === 0 ? '1.6rem' : '1.15rem' }}
                                >
                                    {feature.title}
                                </h3>
                                <p
                                    className="text-gray-200/95 leading-snug opacity-0 group-hover:opacity-100 transition-all duration-500 transform translate-y-2 group-hover:translate-y-0"
                                    style={{ fontSize: '0.78rem' }}
                                >
                                    {feature.description}
                                </p>
                                <div className="mt-3 h-0.5 w-10 rounded-full transition-all duration-500 group-hover:w-20" style={{ background: `linear-gradient(90deg, ${colors.accent}, ${colors.primary})` }} />
                            </div>
                        </div>
                    );
                })}
            </div>
        );
    };

    const renderFilterChips = () => {
        if (hasAdminImages) return null;
        return (
            <div className="flex flex-wrap items-center justify-center gap-2 mb-10">
                {FILTER_CHIPS.map((chip) => {
                    const isActive = activeFilter === chip;
                    return (
                        <button
                            key={chip}
                            onClick={() => setActiveFilter(chip)}
                            className={`px-4 py-2 rounded-full text-xs font-bold transition-all duration-300 border ${
                                isActive
                                    ? 'text-white shadow-lg transform scale-105'
                                    : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300 hover:text-gray-900'
                            }`}
                            style={isActive
                                ? { border: 'none', background: `linear-gradient(135deg, ${colors.primary}, ${colors.secondary})` }
                                : undefined
                            }
                        >
                            {chip}
                        </button>
                    );
                })}
            </div>
        );
    };

    const renderTrustBar = () => {
        if (hasAdminImages) return null;
        return (
            <div className="mt-14 grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
                {[
                    { icon: ShieldCheck, label: t('Data security'),     value: t('Bank-grade encryption') },
                    { icon: Calendar,     label: t('Uptime'),            value: '99.9% · all regions' },
                    { icon: HelpCircle,   label: t('Onboarding'),        value: t('Free migration & training') },
                    { icon: Search,        label: t('Search & filter'),    value: t('Powered by Gadaa AI') }
                ].map(({ icon: Icon, label, value }) => (
                    <div key={label} className="flex items-center gap-3 p-4 rounded-2xl bg-white border border-gray-100 shadow-sm hover:shadow-md transition-shadow">
                        <span
                            className="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                            style={{ backgroundColor: `${colors.primary}12` }}
                        >
                            <Icon className="h-5 w-5" style={{ color: colors.primary }} />
                        </span>
                        <div className="min-w-0">
                            <div className="text-xs text-gray-500 truncate">{label}</div>
                            <div className="text-sm font-bold text-gray-900 truncate">{value}</div>
                        </div>
                    </div>
                ))}
            </div>
        );
    };

    return (
        <section className={config.section}>
            <div className={config.container}>
                <div className="max-w-3xl mx-auto text-center mb-10">
                    <span
                        className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-semibold tracking-wide uppercase mb-4"
                        style={{ backgroundColor: `${colors.primary}15`, color: colors.primary }}
                    >
                        <span className="w-1.5 h-1.5 rounded-full" style={{ backgroundColor: colors.accent }} />
                        {t('Platform Tour')}
                    </span>
                    <h2 className="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900 mb-5 leading-tight tracking-tight">
                        {title}
                    </h2>
                    <p className="text-lg text-gray-600 leading-relaxed">{subtitle}</p>
                </div>

                {renderFilterChips()}
                {renderBento()}
                {renderTrustBar()}
            </div>
        </section>
    );
}