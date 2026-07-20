import { Quote, Star } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface TestimonialsProps {
    settings?: any;
}

const DEFAULT_ITEMS = [
    { quote: 'GadaaCloud lets us reconcile CBE and Telebirr transactions in minutes instead of days. Our monthly close is finally on time.', author: 'Selam Bekele',    role: 'CFO',          company: 'Bole Coffee Exporters' },
    { quote: 'The offline POS kept our shops running during power cuts. Receipts print in Amharic and sync automatically — game changer.',     author: 'Dawit Tesfaye',   role: 'Owner',        company: 'Merkato Retail Group' },
    { quote: 'Birr payroll aligned with Ethiopian labour law saves us days every month. Employees finally trust their payslips.',              author: 'Hanna Girmachew', role: 'HR Director',  company: 'Habesha Garments' },
    { quote: 'We track coffee lots from farm to port in one system. ECX reporting has never been this easy for our export team.',             author: 'Yonas Abebe',      role: 'GM',           company: 'Sidama Coffee Union' }
];

function initials(name: string) {
    return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
}

export default function Testimonials({ settings }: TestimonialsProps) {
    const { t } = useTranslation();
    const sectionData = settings?.config_sections?.sections?.testimonials || {};
    const colors = settings?.config_sections?.colors || { primary: '#078930', secondary: '#054a2b', accent: '#fcdd09' };

    const title = sectionData.title || 'Trusted by Ethiopian Businesses';
    const subtitle = sectionData.subtitle || 'Real stories from real enterprises running on GadaaCloud.';
    const items = sectionData.items?.length > 0 ? sectionData.items : DEFAULT_ITEMS;

    return (
        <section
            className="py-24 bg-white dark:bg-gray-950 relative overflow-hidden border-t border-gray-100 dark:border-gray-900"
        >
            <style dangerouslySetInnerHTML={{__html: `
                @keyframes marquee {
                    0% { transform: translateX(0); }
                    100% { transform: translateX(-50%); }
                }
                .animate-marquee-infinite {
                    display: flex;
                    width: max-content;
                    animation: marquee 40s linear infinite;
                }
                .animate-marquee-infinite:hover {
                    animation-play-state: paused;
                }
            `}} />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-12">
                <div className="max-w-3xl mx-auto text-center">
                    <span
                        className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-semibold tracking-wide uppercase mb-4"
                        style={{ backgroundColor: `${colors.accent}25`, color: colors.secondary }}
                    >
                        <Star className="h-3 w-3" style={{ fill: colors.accent, stroke: colors.accent }} />
                        {t('Testimonials')}
                    </span>
                    <h2 className="text-3xl md:text-4xl lg:text-5xl font-black text-gray-905 dark:text-white mb-6 leading-tight tracking-tight">
                        {title}
                    </h2>
                    <p className="text-base md:text-lg text-gray-600 dark:text-gray-400 leading-relaxed">{subtitle}</p>
                </div>
            </div>

            {/* Creative Horizontal Marquee Testimonials */}
            <div className="relative w-full overflow-hidden py-4 select-none">
                {/* Left & Right gradient edge fades */}
                <div className="absolute left-0 top-0 bottom-0 w-20 md:w-40 bg-gradient-to-r from-white dark:from-gray-950 to-transparent z-10 pointer-events-none"></div>
                <div className="absolute right-0 top-0 bottom-0 w-20 md:w-40 bg-gradient-to-l from-white dark:from-gray-950 to-transparent z-10 pointer-events-none"></div>

                <div className="animate-marquee-infinite gap-6 px-4">
                    {[...items, ...items, ...items].map((item: any, index: number) => (
                        <div
                            key={index}
                            className="w-[320px] md:w-[400px] flex-shrink-0 bg-white dark:bg-gray-900 p-8 rounded-3xl border border-gray-150/40 dark:border-gray-800/40 shadow-md hover:shadow-xl dark:shadow-black/20 hover:border-emerald-500/20 transition-all duration-500 relative overflow-hidden"
                        >
                            <Quote
                                className="absolute top-6 right-6 h-10 w-10 opacity-5"
                                style={{ color: colors.primary }}
                            />
                            <div className="flex gap-1 mb-5">
                                {Array.from({ length: 5 }).map((_, i) => (
                                    <Star
                                        key={i}
                                        className="h-3.5 w-3.5"
                                        style={{ fill: colors.accent, stroke: colors.accent }}
                                    />
                                ))}
                            </div>
                            <blockquote className="text-sm md:text-base text-gray-700 dark:text-gray-300 leading-relaxed mb-6 italic">
                                “{item.quote}”
                            </blockquote>
                            <div className="flex items-center gap-4 mt-auto">
                                <div
                                    className="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-xs"
                                    style={{ background: `linear-gradient(135deg, ${colors.primary}, ${colors.secondary})` }}
                                >
                                    {initials(item.author)}
                                </div>
                                <div>
                                    <div className="font-bold text-sm text-gray-900 dark:text-white">{item.author}</div>
                                    <div className="text-xs text-gray-500 dark:text-gray-400">
                                        {item.role} · {item.company}
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}