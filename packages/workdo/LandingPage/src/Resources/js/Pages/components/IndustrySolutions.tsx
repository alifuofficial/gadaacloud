import React, { useState } from 'react';
import { 
    Coffee, Building2, Boxes, ShoppingBag, 
    Globe, Users, ArrowRight, Check 
} from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface IndustrySolutionsProps {
    colors: {
        primary: string;
        secondary: string;
        accent: string;
    };
    grad: (a?: string, b?: string) => string;
}

export default function IndustrySolutions({ colors, grad }: IndustrySolutionsProps) {
    const { t } = useTranslation();
    const [selectedIndustry, setSelectedIndustry] = useState(0);

    const industries = [
        {
            id: 'coffee',
            title: 'Coffee & Agribusiness',
            icon: Coffee,
            tagline: 'Farm-to-port traceability aligned with ECX grading & export standards.',
            description: 'From Yirgacheffe and Sidama farm cooperatives to Addis Ababa grading labs and Djibouti port shipments. Trace moisture levels, screen sizes, bag weights, and multi-currency foreign exchange contracts.',
            metrics: ['ECX Grade 1 to 5', 'Washed & Sun-Dried Lots', 'Djibouti Transit Demurrage Clock'],
            badge: 'Export Powerhouse'
        },
        {
            id: 'realestate',
            title: 'Real Estate & Property',
            icon: Building2,
            tagline: 'Automated tenant leases, Birr rent invoicing, and building maintenance.',
            description: 'Manage residential and commercial portfolios across Addis Ababa and regional cities. Auto-issue monthly rental VAT invoices, track tenant security deposits, and streamline repair work orders.',
            metrics: ['Automated Monthly Rent Bills', 'Deposit Ledger Scoping', 'Tenant Maintenance Portal'],
            badge: 'Asset Management'
        },
        {
            id: 'manufacturing',
            title: 'Manufacturing & Goods',
            icon: Boxes,
            tagline: 'Multi-stage production, Bill of Materials (BOM), and depot tracking.',
            description: 'Engineered for industrial park manufacturers in Hawassa, Bole Lemi, and Kilinto. Monitor raw material consumption, loaded machine hours, stage-gate inspections, and finished goods transfers.',
            metrics: ['Multi-Level BOM', 'Weighted Average Valuation', 'Batch Barcoding'],
            badge: 'Industrial Grade'
        },
        {
            id: 'retail',
            title: 'Retail, Cafes & Supermarkets',
            icon: ShoppingBag,
            tagline: 'Offline-resilient point of sale with instant Telebirr & CBE payments.',
            description: 'Fast, touch-optimized POS for high-volume stores in Merkato, Bole, and regional centers. Keeps operating with offline receipt printing when internet fluctuates, syncing seamlessly upon reconnection.',
            metrics: ['Offline Cashier Sync', 'Telebirr QR Payment', 'Amharic Receipt Printing'],
            badge: 'High Velocity'
        },
        {
            id: 'importexport',
            title: 'Import, Export & Logistics',
            icon: Globe,
            tagline: 'Letter of Credit (LC) lifecycle, NBE clearance, and shipping management.',
            description: 'Navigate the complexities of cross-border trade. Track commercial bank FX queues, customs declaration approvals, shipping bills of lading, and free-time demurrage countdowns.',
            metrics: ['LC Queue Management', 'Customs Declaration', 'Port Container Tracking'],
            badge: 'Cross-Border Trade'
        },
        {
            id: 'ngo',
            title: 'NGOs & Cooperatives',
            icon: Users,
            tagline: 'Donor grant accounting, project timesheets, and strict compliance.',
            description: 'Purpose-built for development organizations and agricultural cooperative unions. Allocate donor funding transparently, log billable project timesheets, and generate audit-ready financial reports.',
            metrics: ['Donor Fund Allocation', 'Labor Timesheet Loading', 'Audit Compliance Trails'],
            badge: 'Transparency First'
        }
    ];

    const current = industries[selectedIndustry] || industries[0];
    const CurrentIcon = current.icon;

    return (
        <section id="industries" className="py-20 sm:py-28 bg-slate-50/60 border-t border-slate-200/60 relative overflow-hidden">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="max-w-3xl mx-auto text-center mb-16 space-y-4">
                    <span className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-50 text-[#00A76F] border border-emerald-100">
                        <Building2 className="h-3.5 w-3.5" /> Sector Solutions
                    </span>
                    <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 tracking-tight">
                        Purpose-built for Ethiopia's economic pillars.
                    </h2>
                    <p className="text-base sm:text-lg text-slate-600 leading-relaxed">
                        Pre-configured workflows tailored to the exact regulatory and operational demands of key Ethiopian industries.
                    </p>
                </div>

                {/* Industry Tab Navigation Chips */}
                <div className="flex flex-wrap justify-center gap-2 mb-10 max-w-4xl mx-auto">
                    {industries.map((ind, idx) => {
                        const Icon = ind.icon;
                        const isSelected = selectedIndustry === idx;
                        return (
                            <button
                                key={ind.id}
                                onClick={() => setSelectedIndustry(idx)}
                                className={`flex items-center gap-2 px-4 py-2.5 rounded-2xl text-xs font-bold transition-all ${
                                    isSelected
                                        ? 'bg-[#00A76F] text-white shadow-md shadow-emerald-500/20 scale-[1.02]'
                                        : 'bg-white text-slate-700 border border-slate-200/80 hover:bg-slate-100/70'
                                }`}
                            >
                                <Icon className="h-4 w-4" />
                                <span>{ind.title}</span>
                            </button>
                        );
                    })}
                </div>

                {/* Active Industry Showcase Card */}
                <div className="max-w-5xl mx-auto bg-white rounded-3xl p-8 sm:p-12 border border-emerald-100 shadow-xl grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    <div className="lg:col-span-7 space-y-5 text-left">
                        <div className="flex items-center gap-2.5">
                            <div className="w-12 h-12 rounded-2xl bg-emerald-50 flex items-center justify-center text-[#00A76F]">
                                <CurrentIcon className="h-6 w-6" />
                            </div>
                            <div>
                                <span className="text-[10px] font-bold uppercase tracking-wider text-[#00A76F] block">
                                    {current.badge}
                                </span>
                                <h3 className="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                                    {current.title}
                                </h3>
                            </div>
                        </div>

                        <p className="text-sm sm:text-base text-slate-600 leading-relaxed font-medium">
                            {current.tagline}
                        </p>

                        <p className="text-xs sm:text-sm text-slate-500 leading-relaxed">
                            {current.description}
                        </p>

                        <div className="space-y-2.5 pt-2">
                            {current.metrics.map((m, i) => (
                                <div key={i} className="flex items-center gap-2.5 text-xs font-semibold text-slate-700">
                                    <div className="w-4 h-4 rounded-full bg-emerald-100 text-[#00A76F] flex items-center justify-center flex-shrink-0">
                                        <Check className="h-3 w-3 stroke-[3]" />
                                    </div>
                                    <span>{m}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="lg:col-span-5 bg-gradient-to-br from-[#031B13] to-[#042A1D] text-white rounded-2xl p-6 sm:p-8 space-y-5 text-left shadow-lg border border-emerald-500/20">
                        <div className="flex items-center justify-between border-b border-emerald-800/40 pb-3">
                            <span className="text-xs font-bold text-white">Pre-Configured Template</span>
                            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/20 text-[#34D399]">Ready in 5 Mins</span>
                        </div>
                        <div className="space-y-3 text-xs">
                            <div className="p-3 rounded-xl bg-emerald-950/60 border border-emerald-500/20 space-y-1">
                                <span className="text-slate-400 text-[10px] block font-bold">Chart of Accounts</span>
                                <span className="font-bold text-white block">Pre-Mapped to Ethiopian Tax Schedule</span>
                            </div>
                            <div className="p-3 rounded-xl bg-emerald-950/60 border border-emerald-500/20 space-y-1">
                                <span className="text-slate-400 text-[10px] block font-bold">Fiscal Invoicing</span>
                                <span className="font-bold text-[#34D399] block">MoR Directive 1142 Ready</span>
                            </div>
                            <div className="p-3 rounded-xl bg-emerald-950/60 border border-emerald-500/20 space-y-1">
                                <span className="text-slate-400 text-[10px] block font-bold">Local Currency</span>
                                <span className="font-bold text-white block">ETB Base with Dual USD/EUR Sheet</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
