import React, { useState, useMemo } from 'react';
import { Calculator, ArrowRight, TrendingUp, Clock, ShieldCheck, DatabaseBackup, Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CloudTopologyPattern, SecurityHexPattern } from './CloudSecurityPatterns';

interface RoiCalculatorProps {
    colors: {
        primary: string;
        secondary: string;
        accent: string;
        dark?: string;
    };
    grad: (a?: string, b?: string) => string;
    onStartTrial: () => void;
}

export default function RoiCalculator({ colors, grad, onStartTrial }: RoiCalculatorProps) {
    const { t } = useTranslation();
    const [employees, setEmployees] = useState(35);
    const [invoices, setInvoices] = useState(450);
    const [branches, setBranches] = useState(3);

    const calculations = useMemo(() => {
        // Bookkeeping hours saved per month
        const hoursSaved = Math.round(employees * 1.8 + invoices * 0.12 + branches * 8);
        
        // Annual savings in ETB (average loaded hourly rate ETB 350 + penalty avoidance)
        const annualSavingsEtb = Math.round(hoursSaved * 12 * 350 + (invoices * 55 * 12));
        
        // Month end closing days reduction (from ~12 days down to 2 days)
        const closingDaysBefore = Math.min(15, Math.round(7 + branches * 1.5 + (invoices / 200)));
        const closingDaysAfter = Math.max(1, Math.round(closingDaysBefore * 0.2));

        return {
            hoursSaved,
            annualSavingsEtb,
            closingDaysBefore,
            closingDaysAfter,
            roiMultiplier: Math.max(4, Math.round((annualSavingsEtb / 140000) * 10) / 10)
        };
    }, [employees, invoices, branches]);

    const formatCurrency = (val: number) => {
        return new Intl.NumberFormat('en-ET', { maximumFractionDigits: 0 }).format(val);
    };

    return (
        <section id="roi-calculator" className="py-10 sm:py-14 bg-slate-50/70 border-y border-slate-200/60 relative overflow-hidden">
            <CloudTopologyPattern className="absolute inset-0 opacity-60" />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div className="max-w-3xl mx-auto text-center mb-7 space-y-2.5">
                    <span className="inline-flex items-center gap-2 px-3.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-50 text-[#00A76F] border border-emerald-100">
                        <Calculator className="h-3.5 w-3.5" /> Interactive ROI Simulator
                    </span>
                    <h2 className="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 tracking-tight">
                        Calculate your Birr savings with GadaaCloud.
                    </h2>
                    <p className="text-sm sm:text-base text-slate-600 leading-relaxed">
                        Adjust the sliders to match your business size and see the immediate cloud efficiency and compliance savings you unlock.
                    </p>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center max-w-6xl mx-auto">
                    {/* Left Column: Interactive Input Sliders (7 Cols) */}
                    <div className="lg:col-span-7 bg-white rounded-3xl p-6 sm:p-7 border border-slate-200/80 shadow-xl space-y-6">
                        {/* Slider 1: Employees */}
                        <div className="space-y-3">
                            <div className="flex items-center justify-between text-sm">
                                <label className="font-bold text-slate-800 flex items-center gap-2">
                                    <span>Number of Employees / ሰራተኞች</span>
                                </label>
                                <span className="font-black text-lg text-[#00A76F] px-3 py-1 rounded-lg bg-emerald-50 border border-emerald-100">
                                    {employees} Staff
                                </span>
                            </div>
                            <input
                                type="range"
                                min={5}
                                max={500}
                                step={5}
                                value={employees}
                                onChange={(e) => setEmployees(Number(e.target.value))}
                                className="w-full h-2.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-[#00A76F]"
                            />
                            <div className="flex justify-between text-[11px] font-semibold text-slate-400">
                                <span>5 (Small Business)</span>
                                <span>250</span>
                                <span>500+ (Large Enterprise)</span>
                            </div>
                        </div>

                        {/* Slider 2: Monthly Invoices */}
                        <div className="space-y-3">
                            <div className="flex items-center justify-between text-sm">
                                <label className="font-bold text-slate-800 flex items-center gap-2">
                                    <span>Monthly Invoices & Bills / ደረሰኞች</span>
                                </label>
                                <span className="font-black text-lg text-[#00A76F] px-3 py-1 rounded-lg bg-emerald-50 border border-emerald-100">
                                    {invoices} Invoices
                                </span>
                            </div>
                            <input
                                type="range"
                                min={50}
                                max={5000}
                                step={50}
                                value={invoices}
                                onChange={(e) => setInvoices(Number(e.target.value))}
                                className="w-full h-2.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-[#00A76F]"
                            />
                            <div className="flex justify-between text-[11px] font-semibold text-slate-400">
                                <span>50 / Month</span>
                                <span>2,500</span>
                                <span>5,000+ / Month</span>
                            </div>
                        </div>

                        {/* Slider 3: Branches / Warehouses */}
                        <div className="space-y-3">
                            <div className="flex items-center justify-between text-sm">
                                <label className="font-bold text-slate-800 flex items-center gap-2">
                                    <span>Operating Branches & Warehouses / ቅርንጫፎች</span>
                                </label>
                                <span className="font-black text-lg text-[#00A76F] px-3 py-1 rounded-lg bg-emerald-50 border border-emerald-100">
                                    {branches} Locations
                                </span>
                            </div>
                            <input
                                type="range"
                                min={1}
                                max={20}
                                step={1}
                                value={branches}
                                onChange={(e) => setBranches(Number(e.target.value))}
                                className="w-full h-2.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-[#00A76F]"
                            />
                            <div className="flex justify-between text-[11px] font-semibold text-slate-400">
                                <span>1 Location</span>
                                <span>10</span>
                                <span>20+ Nationwide</span>
                            </div>
                        </div>

                        {/* Live Value Guarantees */}
                        <div className="grid grid-cols-2 gap-3 pt-2 text-xs font-semibold text-slate-600">
                            <div className="flex items-center gap-2">
                                <Check className="h-4 w-4 text-[#00A76F] stroke-[3]" />
                                <span>MoR Directive 1142 Automated EIMS</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <Check className="h-4 w-4 text-[#00A76F] stroke-[3]" />
                                <span>Zero Discrepancy CBE Reconciliation</span>
                            </div>
                        </div>
                    </div>

                    {/* Right Column: Dynamic Result Card (5 Cols) */}
                    <div className="lg:col-span-5 bg-gradient-to-br from-[#031B13] via-[#042A1D] to-[#022C22] text-white rounded-3xl p-6 sm:p-7 shadow-2xl border border-emerald-500/20 space-y-5 flex flex-col justify-between relative overflow-hidden">
                        <SecurityHexPattern className="absolute inset-0 opacity-70" />
                        <div className="space-y-4 relative z-10">
                            <div className="flex items-center justify-between border-b border-emerald-800/40 pb-3">
                                <span className="text-xs font-bold uppercase tracking-wider text-emerald-400 flex items-center gap-1.5">
                                    <DatabaseBackup className="h-3.5 w-3.5 text-[#34D399]" /> Projected Annual Value
                                </span>
                                <span className="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/20 text-[#34D399] border border-emerald-500/30">
                                    {calculations.roiMultiplier}x ROI
                                </span>
                            </div>

                            {/* Headline Big Value */}
                            <div className="space-y-1">
                                <div className="text-xs text-emerald-200/80 font-medium">Estimated Annual Financial Savings</div>
                                <div className="text-3xl sm:text-4xl font-black text-[#34D399] tracking-tight">
                                    ETB {formatCurrency(calculations.annualSavingsEtb)}
                                </div>
                                <p className="text-[11px] text-emerald-300/70">
                                    Derived from manual labor reduction, automated tax validation, and accelerated closing.
                                </p>
                            </div>

                            {/* Metric Breakdown Grid */}
                            <div className="grid grid-cols-2 gap-3 pt-2">
                                <div className="p-3.5 rounded-xl bg-emerald-950/70 border border-emerald-500/20 space-y-1">
                                    <div className="flex items-center gap-1.5 text-[10px] font-bold text-emerald-400/90 uppercase">
                                        <Clock className="h-3.5 w-3.5" /> Monthly Time Saved
                                    </div>
                                    <div className="text-xl font-black text-white">{calculations.hoursSaved} Hours</div>
                                    <span className="text-[10px] text-emerald-300/60 block">Saved in admin overhead</span>
                                </div>

                                <div className="p-3.5 rounded-xl bg-emerald-950/70 border border-emerald-500/20 space-y-1">
                                    <div className="flex items-center gap-1.5 text-[10px] font-bold text-emerald-400/90 uppercase">
                                        <TrendingUp className="h-3.5 w-3.5" /> Month Close Time
                                    </div>
                                    <div className="text-xl font-black text-[#34D399]">
                                        {calculations.closingDaysAfter} Days <span className="text-xs text-slate-400 line-through font-normal">{calculations.closingDaysBefore}d</span>
                                    </div>
                                    <span className="text-[10px] text-emerald-300/60 block">85% faster close cycle</span>
                                </div>
                            </div>
                        </div>

                        {/* CTA button inside card */}
                        <div className="pt-4 border-t border-emerald-800/40">
                            <button
                                onClick={onStartTrial}
                                className="w-full py-3.5 px-6 rounded-xl font-bold text-sm bg-[#00A76F] hover:bg-[#059669] text-white shadow-xl shadow-emerald-950/80 transition-all flex items-center justify-center gap-2 hover:scale-[1.02]"
                            >
                                Start Realizing These Savings <ArrowRight className="h-4 w-4" />
                            </button>
                            <span className="text-[10px] text-center text-emerald-300/60 block mt-2">
                                Free 14-day trial · No credit card required · Instant setup in Birr
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
