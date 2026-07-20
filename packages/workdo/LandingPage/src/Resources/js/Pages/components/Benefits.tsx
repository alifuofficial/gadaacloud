import { useState } from 'react';
import { ChevronDown, CheckCircle, ArrowRight } from 'lucide-react';

interface BenefitsProps {
    settings?: any;
}

const BENEFITS_VARIANTS = {
    benefits1: {
        section: 'bg-white dark:bg-gray-950 py-24',
        container: 'max-w-4xl mx-auto px-4 sm:px-6 lg:px-8',
        title: 'text-3xl md:text-4xl lg:text-5xl font-black text-gray-900 dark:text-white mb-16 text-center tracking-tight',
        layout: 'accordion'
    },
    benefits2: {
        section: 'bg-gray-50/50 dark:bg-gray-900/30 py-24 border-y border-gray-100 dark:border-gray-800',
        container: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',
        title: 'text-3xl md:text-4xl lg:text-5xl font-black text-gray-900 dark:text-white mb-16 text-center tracking-tight',
        layout: 'cards'
    },
    benefits3: {
        section: 'bg-white dark:bg-gray-950 py-24',
        container: 'max-w-5xl mx-auto px-4 sm:px-6 lg:px-8',
        title: 'text-3xl md:text-4xl lg:text-5xl font-black text-gray-900 dark:text-white mb-16 text-center tracking-tight',
        layout: 'list'
    },
    benefits4: {
        section: 'bg-gray-950 py-24 border-y border-gray-900',
        container: 'max-w-6xl mx-auto px-4 sm:px-6 lg:px-8',
        title: 'text-3xl md:text-4xl lg:text-5xl font-black text-white mb-16 text-center tracking-tight',
        layout: 'timeline'
    },
    benefits5: {
        section: 'bg-white dark:bg-gray-950 py-24',
        container: 'max-w-6xl mx-auto px-4 sm:px-6 lg:px-8',
        title: 'text-3xl md:text-4xl lg:text-5xl font-black text-gray-900 dark:text-white mb-16 text-center tracking-tight',
        layout: 'tabs'
    }
};

export default function Benefits({ settings }: BenefitsProps) {
    const sectionData = settings?.config_sections?.sections?.benefits || {};
    const variant = sectionData.variant || 'benefits2'; // cards by default
    const config = BENEFITS_VARIANTS[variant as keyof typeof BENEFITS_VARIANTS] || BENEFITS_VARIANTS.benefits2;
    
    const title = sectionData.title || 'Built for Ethiopian Business — Why Choose Us?';
    const colors = settings?.config_sections?.colors || { primary: '#078930', secondary: '#054a2b', accent: '#fcdd09' };
    const [openAccordion, setOpenAccordion] = useState(0);
    const [activeTab, setActiveTab] = useState(0);

    const defaultBenefits = [
        { title: 'Local Compliance Built-In', description: 'VAT, TIN and Ethiopian labour-law requirements are pre-configured. Issue ETB invoices that satisfy the Ethiopian tax authority, generate audit-ready financial reports, and process payroll aligned with local regulations.' },
        { title: 'Amharic & Multilingual Interface', description: 'Work in Amharic, English or Afaan Oromo — your team switches languages instantly. Reports, dashboards and customer-facing receipts support Amharic text and Ge\'ez date format alongside the Gregorian calendar.' },
        { title: 'Accounting in Ethiopian Birr', description: 'Run your books entirely in ETB with multi-currency support for exporters. Reconcile CBE, Dashen, Awash and Telebirr transactions, manage cash flow and produce board-ready financial statements in minutes.' },
        { title: 'Coffee, Retail & Agribusiness Ready', description: 'From specialty coffee exporters in Yirgacheffe to retail chains in Addis Ababa and agribusinesses in the regions — purpose-built workflows handle lots, inventory, supply contracts and storefront sales.' },
        { title: 'Reliable Cloud Across All Regions', description: 'Access your ERP from Addis Ababa, Dire Dawa, Mekelle, Bahir Dar, Hawassa, Jimma, Gondar, Dessie, Adama and beyond. 99.9% uptime, local caching and offline POS keep business running even on patchy connectivity.' },
        { title: 'Affordable for Ethiopian SMEs', description: 'Flexible subscription pricing in Birr scales with your team size. No heavy upfront investment — small businesses, cooperatives and growing enterprises get enterprise-grade tools at SME-friendly rates.' }
    ];
    
    const benefits = sectionData.benefits?.length > 0 ? sectionData.benefits : defaultBenefits;

    const renderAccordion = () => (
        <div className="space-y-4">
            {benefits.map((benefit: any, index: number) => (
                <div key={index} className="border border-gray-100 dark:border-gray-800/80 rounded-2xl overflow-hidden bg-white dark:bg-gray-900 shadow-sm transition-all">
                    <button
                        onClick={() => setOpenAccordion(openAccordion === index ? -1 : index)}
                        className="w-full px-6 py-5 text-left flex items-center justify-between hover:bg-gray-50/50 dark:hover:bg-gray-950/30 transition-all duration-200"
                    >
                        <span className="font-bold text-gray-900 dark:text-white">{benefit.title}</span>
                        <ChevronDown className={`h-5 w-5 text-gray-500 transition-transform duration-300 ${openAccordion === index ? 'rotate-180' : ''}`} />
                    </button>
                    {openAccordion === index && (
                        <div className="px-6 pb-5 text-gray-600 dark:text-gray-400 text-sm leading-relaxed border-t border-gray-50 dark:border-gray-900 pt-4">
                            {benefit.description}
                        </div>
                    )}
                </div>
            ))}
        </div>
    );

    const renderCards = () => (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {benefits.map((benefit: any, index: number) => (
                <div key={index} className="group relative bg-white dark:bg-gray-900 p-8 rounded-3xl border border-gray-150/40 dark:border-gray-800/40 shadow-xl hover:shadow-2xl transition-all duration-500 overflow-hidden transform hover:-translate-y-1">
                    <div 
                        className="absolute -top-12 -right-12 w-28 h-28 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500" 
                        style={{ backgroundColor: `${colors.primary}05` }}
                    />
                    <div className="w-12 h-12 rounded-xl mb-6 flex items-center justify-center shadow-md transition-all duration-300 group-hover:scale-105" style={{ backgroundColor: colors.primary }}>
                        <CheckCircle className="h-6 w-6 text-white" />
                    </div>
                    <h3 className="text-xl font-bold text-gray-900 dark:text-white mb-3 tracking-tight">{benefit.title}</h3>
                    <p className="text-gray-600 dark:text-gray-400 text-sm leading-relaxed">{benefit.description}</p>
                </div>
            ))}
        </div>
    );

    const renderList = () => (
        <div className="space-y-6">
            {benefits.map((benefit: any, index: number) => (
                <div key={index} className="flex items-start space-x-6 p-8 bg-white dark:bg-gray-900 rounded-3xl border border-gray-100/50 dark:border-gray-800/50 shadow-sm hover:shadow-md transition-all duration-300">
                    <div className="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center shadow-sm text-white font-bold text-sm" style={{ backgroundColor: colors.primary }}>
                        {index + 1}
                    </div>
                    <div>
                        <h3 className="text-xl font-bold text-gray-900 dark:text-white mb-2">{benefit.title}</h3>
                        <p className="text-gray-600 dark:text-gray-400 text-sm leading-relaxed">{benefit.description}</p>
                    </div>
                </div>
            ))}
        </div>
    );

    const renderTimeline = () => (
        <div className="relative">
            <div className="absolute left-8 top-0 bottom-0 w-0.5 bg-gray-800"></div>
            <div className="space-y-8">
                {benefits.map((benefit: any, index: number) => (
                    <div key={index} className="relative flex items-start space-x-6">
                        <div className="flex-shrink-0 w-16 h-16 rounded-full flex items-center justify-center shadow-lg relative z-10" style={{ backgroundColor: colors.primary }}>
                            <CheckCircle className="h-8 w-8 text-white" />
                        </div>
                        <div className="bg-gray-900 dark:bg-gray-950 p-8 rounded-3xl border border-gray-800/80 flex-1 shadow-xl">
                            <h3 className="text-xl font-bold text-white mb-3 tracking-tight">{benefit.title}</h3>
                            <p className="text-gray-400 text-sm leading-relaxed">{benefit.description}</p>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );

    const renderTabs = () => (
        <div>
            <div className="flex flex-wrap justify-center mb-12 gap-2 border-b border-gray-100 dark:border-gray-800/60 pb-2">
                {benefits.map((benefit: any, index: number) => (
                    <button
                        key={index}
                        onClick={() => setActiveTab(index)}
                        className={`px-6 py-3 font-semibold text-sm rounded-xl transition-all ${
                            activeTab === index
                                ? 'text-white shadow-md'
                                : 'text-gray-500 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-900'
                        }`}
                        style={activeTab === index ? { backgroundColor: colors.primary } : {}}
                    >
                        {benefit.title}
                    </button>
                ))}
            </div>
            <div className="text-left max-w-4xl mx-auto">
                <div className="bg-gray-50 dark:bg-gray-900/40 p-10 rounded-3xl border border-gray-100 dark:border-gray-800 shadow-md">
                    <h3 className="text-2xl font-black text-gray-900 dark:text-white mb-4 tracking-tight">{benefits[activeTab]?.title}</h3>
                    <p className="text-gray-600 dark:text-gray-400 text-sm md:text-base leading-relaxed">{benefits[activeTab]?.description}</p>
                </div>
            </div>
        </div>
    );

    const renderContent = () => {
        switch (config.layout) {
            case 'cards':
                return renderCards();
            case 'list':
                return renderList();
            case 'timeline':
                return renderTimeline();
            case 'tabs':
                return renderTabs();
            default:
                return renderAccordion();
        }
    };

    return (
        <section className={config.section}>
            <div className={config.container}>
                <h2 className={config.title}>{title}</h2>
                {renderContent()}
            </div>
        </section>
    );
}