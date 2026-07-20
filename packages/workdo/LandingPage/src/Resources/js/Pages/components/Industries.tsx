import { Coffee, ShoppingBag, Factory, Briefcase, Ship, HeartHandshake, Building2, LucideIcon, ArrowRight, Boxes, Home } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface IndustriesProps {
    settings?: any;
}

const ICONS: Record<string, LucideIcon> = {
    Coffee,
    ShoppingBag,
    Factory,
    Briefcase,
    Ship,
    HeartHandshake,
    Building2,
    Boxes,
    Home
};

const DEFAULT_ITEMS = [
    { title: 'Coffee & Agribusiness Export', description: 'Track coffee lots from farm to port. Manage ECX lots, export shipping logs, and foreign exchange receipts in Birr & USD.', icon: 'Coffee' },
    { title: 'Real Estate & Property',    description: 'Track property portfolios, manage tenant lease contracts, automate monthly billing, and track maintenance tickets.',                  icon: 'Building2' },
    { title: 'Manufacturing & Inventory',         description: 'Coordinate raw materials, multi-warehouse stock locations, purchase order requisitions, and barcoded inventory tracking.',                  icon: 'Boxes' },
    { title: 'Retail chains & POS', description: 'Offline-resilient point of sale for retail stores and cafes. Live dashboard sync with Addis Ababa headquarters.',                          icon: 'ShoppingBag' },
    { title: 'Import / Export Trading', description: 'Manage LC bank documentation, tariff calculations, customs clearance status, and multi-currency account sheets.', icon: 'Ship' },
    { title: 'NGOs & Cooperatives',    description: 'Track grant allocations, donor fund disbursements, project milestones, and member dividends with audit-ready files.',                  icon: 'HeartHandshake' }
];

export default function Industries({ settings }: IndustriesProps) {
    const { t } = useTranslation();
    const sectionData = settings?.config_sections?.sections?.industries || {};
    const colors = settings?.config_sections?.colors || { primary: '#078930', secondary: '#054a2b', accent: '#fcdd09' };

    const title = sectionData.title || 'Built for Every Ethiopian Industry';
    const subtitle = sectionData.subtitle || "Purpose-built workflows for the sectors that drive Ethiopia's economy.";
    const items = sectionData.items?.length > 0 ? sectionData.items : DEFAULT_ITEMS;

    return (
        <section id="industries" className="bg-gradient-to-b from-white to-gray-50 py-24">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="max-w-3xl mx-auto text-center mb-16">
                    <span
                        className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-semibold tracking-wide uppercase mb-4"
                        style={{ backgroundColor: `${colors.primary}15`, color: colors.primary }}
                    >
                        <span className="w-1.5 h-1.5 rounded-full" style={{ backgroundColor: colors.accent }} />
                        {t('Industries')}
                    </span>
                    <h2 className="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900 mb-6 leading-tight tracking-tight">
                        {title}
                    </h2>
                    <p className="text-lg text-gray-600 leading-relaxed">{subtitle}</p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {items.map((item: any, index: number) => {
                        const Icon = ICONS[item.icon] || Coffee;
                        return (
                            <div
                                key={index}
                                className="group relative bg-white p-8 rounded-2xl border border-gray-100 shadow-sm hover:shadow-2xl transition-all duration-500 transform hover:-translate-y-2 overflow-hidden"
                            >
                                <div
                                    className="absolute -top-12 -right-12 w-32 h-32 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500"
                                    style={{ backgroundColor: `${colors.primary}08` }}
                                />
                                <div className="relative">
                                    <div
                                        className="w-14 h-14 rounded-2xl flex items-center justify-center mb-6 transition-all duration-300 group-hover:scale-110 group-hover:rotate-3"
                                        style={{
                                            background: `linear-gradient(135deg, ${colors.primary}, ${colors.secondary})`
                                        }}
                                    >
                                        <Icon className="h-7 w-7 text-white" />
                                    </div>
                                    <h3 className="text-xl font-bold text-gray-900 mb-3">{item.title}</h3>
                                    <p className="text-gray-600 leading-relaxed mb-6">{item.description}</p>
                                    <div className="flex items-center text-sm font-semibold opacity-0 group-hover:opacity-100 transition-all duration-300 transform translate-y-2 group-hover:translate-y-0" style={{ color: colors.primary }}>
                                        {t('Learn more')}
                                        <ArrowRight className="ml-2 h-4 w-4" />
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}