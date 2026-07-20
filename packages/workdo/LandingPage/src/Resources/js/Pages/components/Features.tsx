import { useState, useEffect } from 'react';
import { Building2, Calculator, Users, CreditCard, UserCheck, FolderOpen, ChevronLeft, ChevronRight, Coffee, Store, Wheat, Receipt, Brain, Boxes, Home } from 'lucide-react';

interface FeaturesProps {
    settings?: any;
}

const FEATURES_VARIANTS = {
    features1: {
        section: 'bg-white dark:bg-gray-950 py-24 border-b border-gray-100 dark:border-gray-900',
        container: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',
        title: 'text-3xl md:text-4xl lg:text-5xl font-black text-gray-900 dark:text-white mb-6 text-center tracking-tight',
        subtitle: 'text-lg text-gray-600 dark:text-gray-400 mb-16 text-center max-w-3xl mx-auto leading-relaxed',
        grid: 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8',
        layout: 'grid'
    },
    features2: {
        section: 'bg-gray-50 dark:bg-gray-900/30 py-24',
        container: 'max-w-5xl mx-auto px-4 sm:px-6 lg:px-8',
        title: 'text-3xl md:text-4xl font-extrabold text-gray-900 dark:text-white mb-6 text-center',
        subtitle: 'text-lg text-gray-600 dark:text-gray-400 mb-16 text-center max-w-2xl mx-auto',
        grid: 'space-y-8',
        layout: 'list'
    },
    features3: {
        section: 'bg-white dark:bg-gray-950 py-24',
        container: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',
        title: 'text-3xl md:text-4xl lg:text-5xl font-black text-gray-900 dark:text-white mb-6 text-center tracking-tight',
        subtitle: 'text-lg text-gray-600 dark:text-gray-400 mb-20 text-center max-w-3xl mx-auto leading-relaxed',
        grid: 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8',
        layout: 'cards'
    },
    features4: {
        section: 'bg-gray-950 py-24 border-y border-gray-900',
        container: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',
        title: 'text-3xl md:text-4xl lg:text-5xl font-black text-white mb-6 tracking-tight',
        subtitle: 'text-lg text-gray-300 mb-16 max-w-2xl leading-relaxed',
        grid: 'grid grid-cols-1 lg:grid-cols-2 gap-12 items-center',
        layout: 'split'
    },
    features5: {
        section: 'bg-gray-50/50 dark:bg-gray-900/20 py-24',
        container: 'max-w-full px-4 sm:px-6 lg:px-8',
        title: 'text-3xl md:text-4xl lg:text-5xl font-black text-gray-900 dark:text-white mb-6 text-center tracking-tight',
        subtitle: 'text-lg text-gray-600 dark:text-gray-400 mb-16 text-center max-w-3xl mx-auto leading-relaxed',
        grid: 'relative overflow-hidden',
        layout: 'carousel'
    }
};

export default function Features({ settings }: FeaturesProps) {
    const sectionData = settings?.config_sections?.sections?.features || {};
    const variant = sectionData.variant || 'features3'; // Standard modern cards by default
    const config = FEATURES_VARIANTS[variant as keyof typeof FEATURES_VARIANTS] || FEATURES_VARIANTS.features3;
    
    const title = sectionData.title || 'Enterprise Features Powered by AI';
    const subtitle = sectionData.subtitle || 'All the modules you need to scale your operations, tailored specifically for the Ethiopian business ecosystem.';
    const colors = settings?.config_sections?.colors || { primary: '#078930', secondary: '#054a2b', accent: '#fcdd09' };
    const [currentSlide, setCurrentSlide] = useState(0);

    const defaultFeatures = [
        { title: 'AI Copilot & Cash Flow', description: 'Automatically read supplier invoices & receipts, auto-categorize transactions, and predict cash flow using state-of-the-art models.', icon: 'Brain' },
        { title: 'Birr Accounting (VAT/TIN)', description: 'Ensure full compliance with the Ethiopian tax authority. Run VAT calculations, record TIN records, and print audit-ready reports.', icon: 'Calculator' },
        { title: 'Real Estate & Property', description: 'Track properties, lease contracts, tenant profiles, and automate monthly rent invoicing in Ethiopian Birr.', icon: 'Home' },
        { title: 'Multi-Warehouse Inventory', description: 'Real-time stock level monitoring across different regional depots, detailed valuations, and automatic reorder alerts.', icon: 'Boxes' },
        { title: 'Agribusiness & Coffee Export', description: 'Specialized workflow to trace lots from coffee farms (Yirgacheffe/Sidama) through ECX grading all the way to port delivery.', icon: 'Wheat' },
        { title: 'Retail POS (Offline Sync)', description: 'Fast cashier terminal for shops and cafes in Addis Ababa. Print receipts, support Telebirr/CBE payments, and sync offline transactions.', icon: 'Store' },
        { title: 'HRM & Birr Payroll', description: 'Easily calculate income tax, pension, and employee allowances in Birr, fully aligned with current Ethiopian labor laws.', icon: 'UserCheck' },
        { title: 'Projects & Team Collaboration', description: 'Manage client deliverables, schedule sprints, and track billable hours using integrated Kanban boards and Gantt charts.', icon: 'FolderOpen' }
    ];
    
    const features = sectionData.features?.length > 0 ? sectionData.features : defaultFeatures;
    const duplicatedFeatures = [...features, ...features]; // Duplicate for infinite scroll
    
    // Auto slide for carousel
    useEffect(() => {
        if (config.layout === 'carousel' && features.length > 1) {
            const interval = setInterval(() => {
                setCurrentSlide(prev => {
                    const next = prev + 1;
                    if (next >= features.length) {
                        setTimeout(() => setCurrentSlide(0), 500);
                        return next;
                    }
                    return next;
                });
            }, 4000);
            return () => clearInterval(interval);
        }
    }, [config.layout, features.length]);

    const getIcon = (iconName: string) => {
        const icons = { Building2, Calculator, Users, CreditCard, UserCheck, FolderOpen, Coffee, Store, Wheat, Receipt, Brain, Boxes, Home };
        return icons[iconName as keyof typeof icons] || Building2;
    };

    const renderFeature = (feature: any, index: number) => {
        const IconComponent = getIcon(feature.icon);

        if (config.layout === 'grid') {
            return (
                <div key={index} className="text-center p-8 hover:bg-gray-50/50 dark:hover:bg-gray-900/20 rounded-3xl transition-all duration-300 group border border-transparent hover:border-gray-100 dark:hover:border-gray-800">
                    <div className="w-16 h-16 mx-auto mb-6 rounded-2xl flex items-center justify-center shadow-lg group-hover:scale-110 group-hover:rotate-3 transition-all duration-500" style={{ backgroundColor: colors.primary }}>
                        <IconComponent className="h-7 w-7 text-white" />
                    </div>
                    <h3 className="text-xl font-bold text-gray-900 dark:text-white mb-3">{feature.title}</h3>
                    <p className="text-gray-600 dark:text-gray-400 text-sm leading-relaxed">{feature.description}</p>
                </div>
            );
        }

        if (config.layout === 'list') {
            return (
                <div key={index} className="flex items-start space-x-6 p-8 bg-white dark:bg-gray-900 rounded-3xl border border-gray-100/80 dark:border-gray-800/80 shadow-md hover:shadow-lg transition-all duration-300">
                    <div className="flex-shrink-0 w-14 h-14 rounded-2xl flex items-center justify-center shadow-md" style={{ backgroundColor: colors.primary }}>
                        <IconComponent className="h-6 w-6 text-white" />
                    </div>
                    <div>
                        <h3 className="text-xl font-bold text-gray-900 dark:text-white mb-2">{feature.title}</h3>
                        <p className="text-gray-600 dark:text-gray-400 text-sm leading-relaxed">{feature.description}</p>
                    </div>
                </div>
            );
        }

        if (config.layout === 'cards') {
            return (
                <div key={index} className="group relative bg-white dark:bg-gray-900 p-8 rounded-3xl border border-gray-150/60 dark:border-gray-800/60 shadow-xl hover:shadow-2xl transition-all duration-500 transform hover:-translate-y-2 overflow-hidden flex flex-col justify-between">
                    <div 
                        className="absolute -top-12 -right-12 w-28 h-28 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500" 
                        style={{ backgroundColor: `${colors.primary}08` }}
                    />
                    <div>
                        <div className="w-14 h-14 mb-6 rounded-2xl flex items-center justify-center shadow-md transition-all duration-350 group-hover:scale-110 group-hover:rotate-6" style={{ backgroundColor: colors.primary }}>
                            <IconComponent className="h-6 w-6 text-white" />
                        </div>
                        <h3 className="text-xl font-extrabold text-gray-900 dark:text-white mb-4 tracking-tight">{feature.title}</h3>
                        <p className="text-gray-600 dark:text-gray-400 text-sm leading-relaxed mb-6">{feature.description}</p>
                    </div>
                    <div className="w-8 h-1 rounded-full transition-all duration-300 group-hover:w-16" style={{ backgroundColor: colors.primary }}></div>
                </div>
            );
        }

        if (config.layout === 'split') {
            return (
                <div key={index} className="flex items-center space-x-4 p-5 bg-gray-900 dark:bg-gray-950 rounded-2xl border border-gray-800/50 hover:border-gray-700/50 transition-all duration-300 group">
                    <div className="flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center transition-transform group-hover:scale-105" style={{ backgroundColor: colors.primary }}>
                        <IconComponent className="h-5.5 w-5.5 text-white" />
                    </div>
                    <div>
                        <h3 className="text-base font-bold text-white mb-1">{feature.title}</h3>
                        <p className="text-gray-400 text-xs leading-relaxed">{feature.description}</p>
                    </div>
                </div>
            );
        }

        if (config.layout === 'carousel') {
            return (
                <div key={index} className="flex-shrink-0 w-80 mr-6 group">
                    <div className="bg-white dark:bg-gray-900 p-8 rounded-3xl shadow-xl hover:shadow-2xl transition-all duration-500 border border-gray-100/80 dark:border-gray-800/80 transform hover:-translate-y-2 h-full flex flex-col justify-between">
                        <div>
                            <div className="w-16 h-16 mb-6 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300" style={{ backgroundColor: `${colors.primary}15` }}>
                                <IconComponent className="h-8 w-8" style={{ color: colors.primary }} />
                            </div>
                            <h3 className="text-xl font-bold text-gray-900 dark:text-white mb-4 group-hover:text-gray-700 dark:group-hover:text-gray-300 transition-colors">{feature.title}</h3>
                            <p className="text-gray-600 dark:text-gray-400 text-sm leading-relaxed mb-6">{feature.description}</p>
                        </div>
                        <div className="w-12 h-1 rounded-full transition-all duration-300" style={{ backgroundColor: colors.primary }}></div>
                    </div>
                </div>
            );
        }

        return null;
    };

    if (config.layout === 'split') {
        return (
            <section className={config.section}>
                <div className={config.container}>
                    <div className={config.grid}>
                        <div>
                            <h2 className={config.title}>{title}</h2>
                            <p className={config.subtitle}>{subtitle}</p>
                        </div>
                        <div className="space-y-4">
                            {features.map((feature: any, index: number) => renderFeature(feature, index))}
                        </div>
                    </div>
                </div>
            </section>
        );
    }

    if (config.layout === 'carousel') {
        const nextSlide = () => {
            setCurrentSlide(prev => {
                const next = prev + 1;
                if (next >= features.length) {
                    setTimeout(() => setCurrentSlide(0), 500);
                    return next;
                }
                return next;
            });
        };
        
        const prevSlide = () => {
            setCurrentSlide(prev => {
                if (prev === 0) {
                    setCurrentSlide(features.length);
                    setTimeout(() => setCurrentSlide(features.length - 1), 50);
                    return features.length - 1;
                }
                return prev - 1;
            });
        };
        
        return (
            <section className={config.section}>
                <div className={config.container}>
                    <h2 className={config.title}>{title}</h2>
                    <p className={config.subtitle}>{subtitle}</p>
                    <div className="relative">
                        <div className={config.grid}>
                            <div 
                                className={`flex transition-transform duration-500 ease-in-out ${currentSlide >= features.length ? 'transition-none' : ''}`}
                                style={{ transform: `translateX(-${currentSlide * 344}px)` }}
                            >
                                {duplicatedFeatures.map((feature: any, index: number) => renderFeature(feature, index))}
                            </div>
                        </div>
                        <button
                            onClick={prevSlide}
                            className="absolute left-4 top-1/2 transform -translate-y-1/2 bg-white/90 hover:bg-white text-gray-800 p-3 rounded-full shadow-xl transition-all hover:scale-110 z-10"
                        >
                            <ChevronLeft className="w-6 h-6" />
                        </button>
                        <button
                            onClick={nextSlide}
                            className="absolute right-4 top-1/2 transform -translate-y-1/2 bg-white/90 hover:bg-white text-gray-800 p-3 rounded-full shadow-xl transition-all hover:scale-110 z-10"
                        >
                            <ChevronRight className="w-6 h-6" />
                        </button>
                        <div className="flex justify-center mt-8 space-x-2">
                            {features.map((_, index) => (
                                <button
                                    key={index}
                                    onClick={() => setCurrentSlide(index)}
                                    className={`w-3 h-3 rounded-full transition-all duration-300 ${
                                        currentSlide === index ? 'scale-125' : 'hover:scale-110'
                                    }`}
                                    style={{ backgroundColor: currentSlide === index ? colors.primary : '#d1d5db' }}
                                />
                            ))}
                        </div>
                    </div>
                </div>
            </section>
        );
    }
    
    return (
        <section className={config.section}>
            <div className={config.container}>
                <h2 className={config.title}>{title}</h2>
                <p className={config.subtitle}>{subtitle}</p>
                <div className={config.grid}>
                    {features.map((feature: any, index: number) => renderFeature(feature, index))}
                </div>
            </div>
        </section>
    );
}