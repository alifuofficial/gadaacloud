import { Menu, X } from 'lucide-react';
import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { getAdminSetting, getImagePath } from '@/utils/helpers';
import { useTranslation } from 'react-i18next';

interface HeaderProps {
    settings?: any;
}

const HEADER_VARIANTS = {
    header1: {
        nav: 'bg-white/85 dark:bg-gray-950/85 backdrop-blur-md border-b border-gray-100/50 dark:border-gray-800/50 sticky top-0 z-50 shadow-sm transition-all duration-300',
        container: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',
        wrapper: 'flex justify-between items-center h-20',
        logo: 'text-2xl font-bold tracking-tight transition-all duration-300 hover:scale-105 flex items-center',
        desktop: 'hidden md:flex items-center space-x-1 lg:space-x-2',
        mobile: 'md:hidden text-gray-600 dark:text-gray-300 p-2 hover:bg-gray-50 dark:hover:bg-gray-900 rounded-xl transition-all',
        mobileMenu: 'md:hidden bg-white/95 dark:bg-gray-950/95 backdrop-blur-lg border-t border-gray-100 dark:border-gray-800 shadow-xl rounded-b-2xl animate-in slide-in-from-top duration-300'
    },
    header2: {
        nav: 'bg-white/90 dark:bg-gray-950/90 backdrop-blur-md border-b border-gray-100 sticky top-0 z-50 shadow-md',
        container: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',
        wrapper: 'flex justify-between items-center h-20',
        logo: 'text-2xl font-black tracking-tight',
        desktop: 'hidden md:flex items-center space-x-1 bg-gray-50/50 dark:bg-gray-900/50 px-4 py-2 rounded-full border border-gray-100 dark:border-gray-800',
        mobile: 'md:hidden text-gray-600 dark:text-gray-300 p-2 hover:bg-gray-50 dark:hover:bg-gray-900 rounded-xl transition-all',
        mobileMenu: 'md:hidden bg-white dark:bg-gray-950 border-t w-full shadow-2xl rounded-b-2xl'
    },
    header3: {
        nav: 'bg-white/80 dark:bg-gray-950/80 backdrop-blur-xl border-b border-gray-100/30 sticky top-0 z-50',
        container: 'max-w-6xl mx-auto px-6 sm:px-8 lg:px-10',
        wrapper: 'flex justify-between items-center h-16',
        logo: 'text-xl font-bold tracking-tight',
        desktop: 'hidden md:flex items-center space-x-2',
        mobile: 'md:hidden text-gray-600 dark:text-gray-300 p-2 hover:bg-gray-50 dark:hover:bg-gray-900 rounded-lg transition-all',
        mobileMenu: 'md:hidden bg-white/90 dark:bg-gray-950/90 backdrop-blur-lg border-t'
    },
    header4: {
        nav: 'bg-gray-950/40 backdrop-blur-md absolute top-0 left-0 right-0 z-50 border-b border-white/5',
        container: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',
        wrapper: 'flex justify-between items-center h-22 py-5',
        logo: 'text-2xl font-bold text-white drop-shadow-md',
        desktop: 'hidden md:flex items-center space-x-2',
        mobile: 'md:hidden text-white p-2 hover:bg-white/10 rounded-xl transition-all',
        mobileMenu: 'md:hidden bg-gray-950/95 backdrop-blur-lg border-t border-white/10'
    },
    header5: {
        nav: 'sticky top-0 z-50 shadow-xl border-b border-white/10',
        container: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',
        wrapper: 'flex justify-between items-center h-20',
        logo: 'text-2xl font-bold text-white drop-shadow-md',
        desktop: 'hidden md:flex items-center space-x-2',
        mobile: 'md:hidden text-white p-2 hover:bg-white/10 rounded-xl transition-all',
        mobileMenu: 'md:hidden border-t border-white/15'
    }
};

export default function Header({ settings }: HeaderProps) {
    const sectionData = settings?.config_sections?.sections?.header || {};
    const { t } = useTranslation();
    const variant = sectionData.variant || 'header1';
    const config = HEADER_VARIANTS[variant as keyof typeof HEADER_VARIANTS] || HEADER_VARIANTS.header1;
    
    const companyName = sectionData.company_name || settings?.company_name || 'ERPGo SaaS';
    const isAuthenticated = settings?.is_authenticated;
    const ctaText = isAuthenticated ? 'Dashboard' : (sectionData.cta_text || 'Get Started');
    const colors = settings?.config_sections?.colors || { primary: '#078930', secondary: '#054a2b', accent: '#fcdd09' };
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    
    const themeMode = getAdminSetting('theme_mode') || 'light';
    const logoKey = themeMode === 'dark' ? 'logo_light' : 'logo_dark';
    const logoPath = getAdminSetting(logoKey);
    const logoUrl = logoPath ? getImagePath(logoPath) : null;
    
    // Use dynamic navigation items from settings or empty array
    const navigationItems = sectionData.navigation_items || [];

    // Add custom pages to navigation if they exist
    const customPages = settings?.custom_pages || [];
    const customPageItems = customPages.map(page => ({
        text: page.title,
        href: `/page/${page.slug}`,
        target: '_self'
    }));
    
    // Combine navigation items with custom pages
    const allNavigationItems = [...navigationItems, ...customPageItems];

    const hasPricingInNav = allNavigationItems.some(item => 
        item.text?.toLowerCase() === 'pricing' || 
        item.href?.includes('pricing')
    );

    const renderNavItems = (isMobile = false) => {
        const isTransparentOrGradient = variant === 'header4' || variant === 'header5';
        const textColor = isTransparentOrGradient ? 'text-white' : 'text-gray-600';
        const hoverBg = variant === 'header2' ? 'hover:bg-white hover:shadow-sm' : variant === 'header3' ? 'hover:bg-gray-50' : isTransparentOrGradient ? 'hover:bg-white/10' : 'hover:bg-gray-50';
        
        return allNavigationItems.map((item) => {
            const href = item.href?.startsWith('/page/') ? route('custom-page.show', item.href.replace('/page/', '')) : item.href;
            return item.target === '_blank' ? (
                <a 
                    key={item.text} 
                    href={href}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={isMobile 
                        ? `block px-4 py-3 text-base font-medium ${textColor} ${hoverBg} rounded-lg transition-all` 
                        : `${textColor} px-4 py-2 text-sm font-medium ${hoverBg} rounded-lg transition-all duration-200`
                    }
                    style={!isMobile ? { '--hover-color': isTransparentOrGradient ? 'white' : colors.primary } as React.CSSProperties : {}}
                    onMouseEnter={!isMobile ? (e) => {
                        if (!isTransparentOrGradient) {
                            e.currentTarget.style.color = colors.primary;
                        }
                    } : undefined}
                    onMouseLeave={!isMobile ? (e) => e.currentTarget.style.color = '' : undefined}
                >
                    {item.text}
                </a>
            ) : (
                <Link 
                    key={item.text} 
                    href={href} 
                    className={isMobile 
                        ? `block px-4 py-3 text-base font-medium ${textColor} ${hoverBg} rounded-lg transition-all` 
                        : `${textColor} px-4 py-2 text-sm font-medium ${hoverBg} rounded-lg transition-all duration-200`
                    }
                    style={!isMobile ? { '--hover-color': isTransparentOrGradient ? 'white' : colors.primary } as React.CSSProperties : {}}
                    onMouseEnter={!isMobile ? (e) => {
                        if (!isTransparentOrGradient) {
                            e.currentTarget.style.color = colors.primary;
                        }
                    } : undefined}
                    onMouseLeave={!isMobile ? (e) => e.currentTarget.style.color = '' : undefined}
                >
                    {item.text}
                </Link>
            );
        });
    };

    const renderCTAButtons = (isMobile = false) => {
        const enableRegistration = settings?.enable_registration !== false;
        if (isAuthenticated) {
            return (
                <Link 
                    href={route('dashboard')}
                    className={`text-white rounded-md font-medium transition-colors text-center inline-flex items-center justify-center ${
                        isMobile ? 'px-4 py-2 text-sm w-full' : 
                        variant === 'header3' ? 'px-3 py-1 text-xs' : 'px-4 py-2 text-sm'
                    }`}
                    style={{ backgroundColor: colors.primary }} 
                    onMouseEnter={(e) => e.currentTarget.style.backgroundColor = colors.secondary} 
                    onMouseLeave={(e) => e.currentTarget.style.backgroundColor = colors.primary}
                >
                    {t('Dashboard')}
                </Link>
            );
        }
        
        if (enableRegistration) {
            return (
                <div className={`flex ${isMobile ? 'flex-col space-y-2' : 'space-x-2'}`}>
                    <Link 
                        href={route('login')}
                        className={`border rounded-md font-medium transition-colors text-center inline-flex items-center justify-center ${
                            isMobile ? 'px-4 py-2 text-sm w-full' : 
                            variant === 'header3' ? 'px-3 py-1 text-xs' : 'px-4 py-2 text-sm'
                        }`}
                        style={{ borderColor: colors.primary, color: colors.primary }}
                        onMouseEnter={(e) => {
                            e.currentTarget.style.backgroundColor = colors.primary;
                            e.currentTarget.style.color = 'white';
                        }}
                        onMouseLeave={(e) => {
                            e.currentTarget.style.backgroundColor = 'transparent';
                            e.currentTarget.style.color = colors.primary;
                        }}
                    >
                        {t('Sign In')}
                    </Link>
                    <Link 
                        href={route('register')}
                        className={`text-white rounded-md font-medium transition-colors text-center inline-flex items-center justify-center ${
                            isMobile ? 'px-4 py-2 text-sm w-full' : 
                            variant === 'header3' ? 'px-3 py-1 text-xs' : 'px-4 py-2 text-sm'
                        }`}
                        style={{ backgroundColor: colors.primary }} 
                        onMouseEnter={(e) => e.currentTarget.style.backgroundColor = colors.secondary} 
                        onMouseLeave={(e) => e.currentTarget.style.backgroundColor = colors.primary}
                    >
                        {t('Get Started')}
                    </Link>
                </div>
            );
        }
        
        return (
            <Link 
                href={route('login')}
                className={`text-white rounded-md font-medium transition-colors text-center inline-flex items-center justify-center ${
                    isMobile ? 'px-4 py-2 text-sm w-full' : 
                    variant === 'header3' ? 'px-3 py-1 text-xs' : 'px-4 py-2 text-sm'
                }`}
                style={{ backgroundColor: colors.primary }} 
                onMouseEnter={(e) => e.currentTarget.style.backgroundColor = colors.secondary} 
                onMouseLeave={(e) => e.currentTarget.style.backgroundColor = colors.primary}
            >
                {t('Sign In')}
            </Link>
        );
    };

    const getGradientStyle = () => {
        if (variant === 'header5') {
            return {
                background: `linear-gradient(to right, ${colors.primary}, ${colors.secondary}, ${colors.accent})`
            };
        }
        return {};
    };

    const getMobileMenuStyle = () => {
        if (variant === 'header5') {
            return {
                background: `linear-gradient(to right, ${colors.primary}, ${colors.secondary})`
            };
        }
        return {};
    };

    return (
        <nav className={config.nav} style={getGradientStyle()}>
            <div className={config.container}>
                <div className={config.wrapper}>
                    <Link href={route('landing.page')} className={config.logo} style={{ color: colors.primary }}>
                        {logoUrl ? (
                            <img src={logoUrl} alt={companyName} className="w-auto" />
                        ) : (
                            companyName
                        )}
                    </Link>
                    
                    <div className={config.desktop}>
                        {renderNavItems()}
                        {sectionData?.enable_pricing_link !== false && !hasPricingInNav && (
                            <Link 
                                href={route("pricing.page")}
                                className={`px-4 py-2 text-sm font-medium transition-all duration-200 rounded-lg ${
                                    variant === 'header4' || variant === 'header5' 
                                        ? 'text-white hover:bg-white/10' 
                                        : variant === 'header2' 
                                            ? 'text-gray-600 hover:bg-white hover:shadow-sm'
                                            : 'text-gray-600 hover:bg-gray-50'
                                }`}
                                onMouseEnter={(e) => {
                                    if (variant !== 'header4' && variant !== 'header5') {
                                        e.currentTarget.style.color = colors.primary;
                                    }
                                }}
                                onMouseLeave={(e) => e.currentTarget.style.color = ''}
                            >
                                {t('Pricing')}
                            </Link>
                        )}
                        {renderCTAButtons()}
                    </div>
                    
                    <button 
                        className={config.mobile}
                        onMouseEnter={(e) => e.currentTarget.style.color = colors.primary} 
                        onMouseLeave={(e) => e.currentTarget.style.color = ''}
                        onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                    >
                        {mobileMenuOpen ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
                    </button>
                </div>
            </div>
            
            {mobileMenuOpen && (
                <div className={config.mobileMenu} style={getMobileMenuStyle()}>
                    <div className="px-2 pt-2 pb-3 space-y-1">
                        {renderNavItems(true)}
                        <div className="px-3 py-2">
                            {sectionData?.enable_pricing_link !== false && !hasPricingInNav && (
                                <Link 
                                    href={route("pricing.page")}
                                    className="block px-3 py-2 text-base font-medium text-gray-600"
                                >
                                    {t('Pricing')}
                                </Link>
                            )}
                            {renderCTAButtons(true)}
                        </div>
                    </div>
                </div>
            )}
        </nav>
    );
}