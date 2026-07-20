interface StatsProps {
    settings?: any;
}

const STATS_VARIANTS = {
    stats1: {
        section: 'bg-white dark:bg-gray-950 py-20 border-y border-gray-150/40 dark:border-gray-800/40',
        container: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',
        grid: 'grid grid-cols-2 md:grid-cols-4 gap-8 text-center',
        statValue: 'text-4xl md:text-5xl lg:text-6xl font-black mb-2 tracking-tight bg-clip-text text-transparent',
        statLabel: 'text-gray-600 dark:text-gray-400 text-xs md:text-sm font-semibold uppercase tracking-wider',
        layout: 'minimal'
    },
    stats2: {
        section: 'bg-gradient-to-b from-gray-50/50 to-white dark:from-gray-900/50 dark:to-gray-950 py-24',
        container: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',
        grid: 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8',
        statValue: 'text-4xl md:text-5xl font-black mb-3 tracking-tight',
        statLabel: 'text-gray-600 dark:text-gray-400 text-sm font-semibold uppercase tracking-widest',
        layout: 'cards'
    },
    stats3: {
        section: 'bg-white dark:bg-gray-950 py-20',
        container: 'max-w-6xl mx-auto px-4 sm:px-6 lg:px-8',
        grid: 'grid grid-cols-2 md:grid-cols-4 gap-8 md:gap-12',
        statValue: 'text-3xl md:text-4xl font-extrabold text-gray-900 dark:text-white mb-2 tracking-tight',
        statLabel: 'text-gray-500 dark:text-gray-450 text-sm font-medium',
        layout: 'minimal'
    },
    stats4: {
        section: 'bg-gray-950 py-24 border-y border-gray-900',
        container: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',
        grid: 'grid grid-cols-2 md:grid-cols-4 gap-8',
        statValue: 'text-2xl md:text-3xl font-black text-white tracking-tight',
        statLabel: 'text-gray-400 text-xs md:text-sm font-semibold uppercase tracking-wider',
        layout: 'circular'
    },
    stats5: {
        section: 'py-24 relative overflow-hidden',
        container: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10',
        grid: 'grid grid-cols-2 md:grid-cols-4 gap-8 text-center',
        statValue: 'text-5xl md:text-6xl font-black text-white mb-2 tracking-tight',
        statLabel: 'text-white text-xs md:text-sm font-bold uppercase tracking-widest opacity-90',
        layout: 'gradient'
    }
};

export default function Stats({ settings }: StatsProps) {
    const sectionData = settings?.config_sections?.sections?.stats || {};
    const variant = sectionData.variant || 'stats2'; // Use card layout as default for high-premium look
    const config = STATS_VARIANTS[variant as keyof typeof STATS_VARIANTS] || STATS_VARIANTS.stats2;
    
    const colors = settings?.config_sections?.colors || { primary: '#078930', secondary: '#054a2b', accent: '#fcdd09' };

    const defaultStats = [
        { label: 'Ethiopian Businesses Empowered', value: '5,000+' },
        { label: 'Uptime Guarantee', value: '99.9%' },
        { label: 'Regions Covered in Ethiopia', value: '11' },
        { label: 'Local Amharic Support', value: '24/7' }
    ];
    
    const stats = sectionData.stats?.length > 0 ? sectionData.stats : defaultStats;

    const getBackgroundStyle = () => {
        if (config.layout === 'colored') {
            return { backgroundColor: colors.primary };
        }
        if (config.layout === 'gradient') {
            return { background: `linear-gradient(135deg, ${colors.primary}, ${colors.secondary})` };
        }
        return {};
    };

    const renderStat = (stat: any, index: number) => {
        if (config.layout === 'cards') {
            return (
                <div key={index} className="group relative bg-white dark:bg-gray-900 p-8 rounded-3xl shadow-xl hover:shadow-2xl transition-all duration-500 text-center border border-gray-100/80 dark:border-gray-800/80 transform hover:-translate-y-2 overflow-hidden">
                    <div 
                        className="absolute -top-12 -right-12 w-24 h-24 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500" 
                        style={{ backgroundColor: `${colors.primary}05` }}
                    />
                    <div className={config.statValue} style={{ color: colors.primary }}>
                        {stat.value}
                    </div>
                    <div className={config.statLabel}>{stat.label}</div>
                    <div className="mt-4 w-12 h-1 mx-auto rounded-full transition-all duration-300 transform group-hover:w-20" style={{ backgroundColor: colors.primary, opacity: 0.3 }}></div>
                </div>
            );
        }

        if (config.layout === 'minimal') {
            return (
                <div key={index} className="text-center group">
                    <div 
                        className={`${config.statValue} transition-all duration-300 group-hover:scale-105`} 
                        style={
                            variant === 'stats1' 
                                ? { backgroundImage: `linear-gradient(135deg, ${colors.primary}, ${colors.secondary})` }
                                : { color: colors.primary }
                        }
                    >
                        {stat.value}
                    </div>
                    <div className={config.statLabel}>{stat.label}</div>
                    <div className="mt-3 w-8 h-0.5 mx-auto rounded-full transition-all duration-300 opacity-0 group-hover:opacity-100" style={{ backgroundColor: colors.primary }}></div>
                </div>
            );
        }

        if (config.layout === 'circular') {
            return (
                <div key={index} className="text-center group">
                    <div className="relative w-32 h-32 mx-auto mb-6">
                        <div className="absolute inset-0 rounded-full border-4 border-gray-800 transition-all duration-300 group-hover:border-emerald-500/20"></div>
                        <div className="absolute inset-2 rounded-full flex items-center justify-center transition-all duration-300 group-hover:scale-105 bg-gray-900 border border-gray-800">
                            <div className={config.statValue} style={{ color: colors.primary }}>{stat.value}</div>
                        </div>
                    </div>
                    <div className={config.statLabel}>{stat.label}</div>
                </div>
            );
        }

        // Default layout (colored/gradient)
        return (
            <div key={index} className="group">
                <div className={`${config.statValue} transition-all duration-300 group-hover:scale-110`}>{stat.value}</div>
                <div className={config.statLabel}>{stat.label}</div>
            </div>
        );
    };

    return (
        <section className={config.section} style={getBackgroundStyle()}>
            {variant === 'stats5' && (
                <div className="absolute inset-0 opacity-10 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-white via-transparent to-transparent pointer-events-none"></div>
            )}
            <div className={config.container}>
                <div className={config.grid}>
                    {stats.map((stat, index) => renderStat(stat, index))}
                </div>
            </div>
        </section>
    );
}