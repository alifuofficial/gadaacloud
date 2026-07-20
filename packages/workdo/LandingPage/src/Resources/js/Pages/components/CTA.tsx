import { ArrowRight } from 'lucide-react';
import { getImagePath } from '@/utils/helpers';
import { useTranslation } from 'react-i18next';

interface CTAProps {
    settings?: any;
}

const CTA_VARIANTS = {
    cta1: {
        section: 'bg-white dark:bg-gray-950 py-24',
        container: 'max-w-6xl mx-auto px-4 sm:px-6 lg:px-8',
        title: 'text-3xl md:text-4xl lg:text-5xl font-black text-white mb-6 leading-tight',
        subtitle: 'text-lg md:text-xl text-white/90 mb-10 max-w-2xl mx-auto leading-relaxed',
        buttons: 'flex flex-col sm:flex-row gap-4 justify-center items-center',
        layout: 'centered'
    },
    cta2: {
        section: 'bg-white dark:bg-gray-950 py-24',
        container: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',
        title: 'text-3xl md:text-4xl font-extrabold text-gray-900 dark:text-white mb-6 leading-tight',
        subtitle: 'text-lg text-gray-600 dark:text-gray-400 mb-8 leading-relaxed',
        buttons: 'flex flex-col sm:flex-row gap-4',
        layout: 'split'
    },
    cta3: {
        section: 'bg-gray-50/50 dark:bg-gray-900/30 py-24',
        container: 'max-w-5xl mx-auto px-4 sm:px-6 lg:px-8',
        title: 'text-3xl md:text-4xl font-black text-gray-900 dark:text-white mb-6 leading-tight',
        subtitle: 'text-base md:text-lg text-gray-600 dark:text-gray-400 mb-8 max-w-xl mx-auto leading-relaxed',
        buttons: 'flex flex-col sm:flex-row gap-4 justify-center items-center',
        layout: 'card'
    },
    cta4: {
        section: 'py-24 relative overflow-hidden',
        container: 'max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10',
        title: 'text-4xl md:text-5xl lg:text-6xl font-black text-white mb-6 leading-tight tracking-tight',
        subtitle: 'text-lg md:text-xl text-white/90 mb-12 max-w-3xl mx-auto leading-relaxed',
        buttons: 'flex flex-col sm:flex-row gap-6 justify-center items-center',
        layout: 'gradient'
    },
    cta5: {
        section: 'bg-gradient-to-b from-gray-50/50 to-white dark:from-gray-900/20 dark:to-gray-950 py-24 border-t border-gray-100 dark:border-gray-800/80',
        container: 'max-w-4xl mx-auto px-6 sm:px-8 lg:px-12 text-center',
        title: 'text-2xl md:text-3xl lg:text-4xl font-black text-gray-900 dark:text-white mb-6 tracking-tight',
        subtitle: 'text-sm md:text-base text-gray-600 dark:text-gray-400 mb-8 max-w-2xl mx-auto leading-relaxed',
        buttons: 'flex flex-col sm:flex-row gap-4 justify-center items-center',
        layout: 'minimal'
    }
};

export default function CTA({ settings }: CTAProps) {
    const { t } = useTranslation();
    const sectionData = settings?.config_sections?.sections?.cta || {};
    const variant = sectionData.variant || 'cta4'; // Use gradient by default for high premium saas look
    const config = CTA_VARIANTS[variant as keyof typeof CTA_VARIANTS] || CTA_VARIANTS.cta4;
    
    const title = sectionData.title || 'ቢዝኔስዎን ዛሬ በዘመናዊ AI ያሳድጉ — Ready to Scale Your Enterprise?';
    const subtitle = sectionData.subtitle || 'Join thousands of Ethiopian businesses running accounting in Birr, property tracking, POS systems, and agribusiness modules on GadaaCloud.';
    const primaryButton = sectionData.primary_button || 'Start Free Trial';
    const secondaryButton = sectionData.secondary_button || 'Contact Sales';
    const colors = settings?.config_sections?.colors || { primary: '#078930', secondary: '#054a2b', accent: '#fcdd09' };

    const getBackgroundStyle = () => {
        if (config.layout === 'centered') {
            return { backgroundColor: colors.primary };
        }
        if (config.layout === 'gradient') {
            return { 
                background: `linear-gradient(135deg, ${colors.primary} 0%, ${colors.secondary} 50%, ${colors.accent} 100%)`,
                backgroundAttachment: 'fixed'
            };
        }
        return {};
    };

    const renderButtons = () => {
        const primaryLink = sectionData.primary_button_link || '#';
        const secondaryLink = sectionData.secondary_button_link || '#';
        
        return (
            <div className={config.buttons}>
                <a 
                    href={primaryLink}
                    className={`inline-flex items-center justify-center text-white px-8 py-3 rounded-lg font-semibold transition-all duration-300 hover:scale-105 hover:shadow-lg ${
                        config.layout === 'minimal' 
                            ? 'text-base px-8 py-3.5 rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-0.5' 
                            : config.layout === 'card'
                                ? 'text-base px-10 py-4 rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1'
                                : 'text-lg'
                    } ${config.layout === 'split' ? 'shadow-lg hover:shadow-xl' : 'bg-white/20 hover:bg-white/30 backdrop-blur-sm'}`}
                    style={{ 
                        backgroundColor: config.layout === 'split' || config.layout === 'minimal' || config.layout === 'card' ? colors.primary : undefined,
                        boxShadow: config.layout === 'minimal' || config.layout === 'card' ? `0 4px 14px 0 ${colors.primary}40` : undefined
                    }}
                >
                    {primaryButton}
                    <ArrowRight className={`ml-2 ${config.layout === 'minimal' || config.layout === 'card' ? 'h-5 w-5' : 'h-5 w-5'}`} />
                </a>
                <a 
                    href={secondaryLink}
                    className={`inline-flex items-center justify-center border-2 px-8 py-3 rounded-lg font-semibold transition-all duration-300 hover:scale-105 ${
                        config.layout === 'minimal' 
                            ? 'text-base px-8 py-3.5 border-gray-300 text-gray-700 hover:bg-gray-50 hover:border-gray-400 rounded-lg shadow-sm hover:shadow-md transform hover:-translate-y-0.5' 
                            : config.layout === 'card'
                                ? 'text-base px-10 py-4 border-gray-300 text-gray-700 hover:bg-gray-50 hover:border-gray-400 rounded-xl shadow-sm hover:shadow-lg transform hover:-translate-y-1'
                                : config.layout === 'split'
                                    ? 'border-gray-300 text-gray-700 hover:bg-gray-50 hover:text-gray-900 hover:border-gray-400 shadow-md hover:shadow-lg'
                                    : 'border-white text-white hover:bg-white hover:text-gray-900 backdrop-blur-sm'
                    }`}
                >
                    {secondaryButton}
                </a>
            </div>
        );
    };

    if (config.layout === 'split') {
        return (
            <section className={config.section}>
                <div className={config.container}>
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center bg-gray-50 dark:bg-gray-900 p-10 md:p-16 rounded-3xl border border-gray-100 dark:border-gray-800 shadow-2xl relative overflow-hidden">
                        <div className="absolute top-0 right-0 w-48 h-48 bg-emerald-500/5 rounded-full blur-3xl pointer-events-none"></div>
                        <div className="relative z-10">
                            <h2 className="text-3xl md:text-4xl font-black text-gray-900 dark:text-white mb-6 leading-tight tracking-tight">{title}</h2>
                            <p className="text-base text-gray-600 dark:text-gray-400 mb-8 leading-relaxed">{subtitle}</p>
                            {renderButtons()}
                        </div>
                        <div className="relative overflow-hidden rounded-2xl shadow-xl border border-gray-100 dark:border-gray-850">
                            {sectionData.image ? (
                                <img 
                                    src={sectionData.image.startsWith('http') ? sectionData.image : getImagePath(sectionData.image)}
                                    alt="CTA Image"
                                    className="w-full h-80 object-cover hover:scale-105 transition-transform duration-500"
                                />
                            ) : (
                                <div className="bg-gradient-to-br from-gray-100 to-gray-250 dark:from-gray-850 dark:to-gray-950 h-80 flex items-center justify-center">
                                    <div className="text-center">
                                        <div className="w-16 h-16 bg-gray-300 dark:bg-gray-800 rounded-full mx-auto mb-4 flex items-center justify-center">
                                            <svg className="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <p className="text-gray-500 dark:text-gray-400 font-medium">{t('Upload CTA Image')}</p>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </section>
        );
    }

    if (config.layout === 'card') {
        return (
            <section className={config.section}>
                <div className={config.container}>
                    <div className="bg-white dark:bg-gray-900 p-10 md:p-16 lg:p-20 rounded-3xl shadow-2xl text-center border border-gray-150/60 dark:border-gray-800/60 relative overflow-hidden">
                        <div className="absolute inset-0 bg-gradient-to-br from-gray-50/50 dark:from-gray-950/20 via-white dark:via-gray-900/10 to-gray-50/50 dark:to-gray-950/20 pointer-events-none"></div>
                        <div className="absolute -top-24 -left-24 w-48 h-48 bg-emerald-500/5 dark:bg-emerald-500/2 rounded-full blur-3xl"></div>
                        <div className="absolute -bottom-24 -right-24 w-48 h-48 bg-yellow-500/5 dark:bg-yellow-500/2 rounded-full blur-3xl"></div>
                        <div className="relative z-10 space-y-8">
                            <div className="space-y-6">
                                <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-4 shadow-md bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-100/50 dark:border-emerald-900/30">
                                    <svg className="w-8 h-8 text-emerald-600 dark:text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 className="text-3xl md:text-4xl font-black text-gray-900 dark:text-white mb-4 leading-tight tracking-tight">{title}</h2>
                                    <p className="text-base md:text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto leading-relaxed">{subtitle}</p>
                                </div>
                            </div>
                            <div className="pt-4">
                                {renderButtons()}
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        );
    }

    if (config.layout === 'minimal') {
        return (
            <section className={config.section}>
                <div className={config.container}>
                    <div className="w-16 h-1.5 mx-auto mb-8 rounded-full" style={{ backgroundColor: colors.primary }}></div>
                    <h2 className="text-3xl md:text-4xl font-black text-gray-900 dark:text-white mb-6 leading-tight tracking-tight">{title}</h2>
                    <p className="text-base md:text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto leading-relaxed mb-10">{subtitle}</p>
                    {renderButtons()}
                </div>
            </section>
        );
    }

    // Centered or Gradient layout
    return (
        <section className={`${config.section} relative overflow-hidden bg-white dark:bg-gray-950`}>
            <style dangerouslySetInnerHTML={{__html: `
                .grid-bg {
                    background-image: linear-gradient(to right, rgba(128,128,128,0.04) 1px, transparent 1px), linear-gradient(to bottom, rgba(128,128,128,0.04) 1px, transparent 1px);
                    background-size: 24px 24px;
                }
            `}} />
            {/* Grid background */}
            <div className="absolute inset-0 grid-bg opacity-30 pointer-events-none"></div>
            
            <div className={config.container}>
                <div className="relative overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-emerald-950 via-slate-900 to-emerald-950 border border-emerald-500/20 shadow-[0_0_50px_-12px_rgba(16,185,129,0.3)] p-12 md:p-20 text-center">
                    {/* Glowing effect inside card */}
                    <div className="absolute -top-40 -left-40 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
                    <div className="absolute -bottom-40 -right-40 w-96 h-96 bg-yellow-500/10 rounded-full blur-3xl pointer-events-none"></div>
                    <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[300px] h-[300px] bg-emerald-600/5 rounded-full blur-[80px] pointer-events-none"></div>

                    <div className="relative z-10 space-y-6">
                        <div className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 mx-auto">
                            <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            ⚡ GadaaCloud Enterprise ERP
                        </div>
                        <h2 className="text-3xl md:text-5xl font-black text-white mb-6 leading-tight tracking-tight max-w-4xl mx-auto">{title}</h2>
                        <p className="text-base md:text-lg text-gray-300 mb-10 max-w-2xl mx-auto leading-relaxed">{subtitle}</p>
                        <div className="flex justify-center">
                            {renderButtons()}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}