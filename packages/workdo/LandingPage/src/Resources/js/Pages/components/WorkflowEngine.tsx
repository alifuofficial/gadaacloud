import React, { useState } from 'react';
import { 
    GitMerge, ArrowRight, CheckCircle2, ShieldCheck, 
    FileText, ShoppingBag, Truck, DollarSign, Boxes, 
    Coffee, Building2, Globe, Clock, ChevronRight
} from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface WorkflowEngineProps {
    colors: {
        primary: string;
        secondary: string;
        accent: string;
    };
    grad: (a?: string, b?: string) => string;
}

type WorkflowType = 'orderToCash' | 'procureToPay';

export default function WorkflowEngine({ colors, grad }: WorkflowEngineProps) {
    const { t } = useTranslation();
    const [activeFlow, setActiveFlow] = useState<WorkflowType>('orderToCash');
    const [selectedStepIndex, setSelectedStepIndex] = useState(2); // default EIMS Invoicing step

    const orderToCashSteps = [
        {
            id: 'quote',
            stepNumber: '01',
            title: 'Quotation & Contract',
            module: 'CRM & Sales',
            summary: 'Client approves terms; inventory auto-reserves in depot.',
            detail: 'Quotes convert seamlessly to contracts with milestone stage-gates. Inventory reservations prevent accidental overselling across depots in Addis Ababa and Hawassa.',
            impact: 'Zero overselling across branches',
            icon: FileText
        },
        {
            id: 'contract',
            stepNumber: '02',
            title: 'Contract Milestone Gate',
            module: 'Contracting & Legal',
            summary: 'Multi-party approval, labor rate loading, and SLA terms.',
            detail: 'Client contracts track loaded labor cost, EVM (Earned Value Management), and deliverable sign-offs before triggering billing.',
            impact: '100% margin visibility',
            icon: Building2
        },
        {
            id: 'eims',
            stepNumber: '03',
            title: 'MoR EIMS Fiscal Invoice',
            module: 'EIMS Directive 1142',
            summary: 'Instant IRN cryptographic QR stamp with direct MoR sync.',
            detail: 'Generates the official Ministry of Revenues tax invoice with SHA-256 signature, IRN validation, and dual Ge\'ez/Gregorian date stamps.',
            impact: '100% tax compliance guarantee',
            icon: ShieldCheck
        },
        {
            id: 'inventory',
            stepNumber: '04',
            title: 'Depot Stock Deduction',
            module: 'Inventory Management',
            summary: 'Barcoded picking slip, weighted-average COGS update.',
            detail: 'Stock deduction is synchronized at warehouse level. Barcoded dispatch slips update general ledger Cost of Goods Sold automatically.',
            impact: 'Real-time FIFO/Average valuation',
            icon: Boxes
        },
        {
            id: 'reconcile',
            stepNumber: '05',
            title: 'CBE / Telebirr Settlement',
            module: 'Banking & AR',
            summary: 'Webhook confirms payment, clears AR, and updates books.',
            detail: 'Direct payment integration matches customer CBE reference or Telebirr transaction ID with the outstanding invoice, reconciling books in seconds.',
            impact: 'Zero unallocated cash receipts',
            icon: DollarSign
        }
    ];

    const procureToPaySteps = [
        {
            id: 'requisition',
            stepNumber: '01',
            title: 'Requisition & PO',
            module: 'Procurement',
            summary: 'Department purchase requests with tiered budget controls.',
            detail: 'Managers approve spending against live monthly budget caps. Purchase orders auto-generate in ETB with supplier tax TIN validation.',
            impact: 'Zero unapproved company spending',
            icon: ShoppingBag
        },
        {
            id: 'lc',
            stepNumber: '02',
            title: 'Letter of Credit (LC)',
            module: 'Import/Export & FX',
            summary: 'NBE foreign exchange approval & customs declaration tracking.',
            detail: 'Manages commercial bank FX queue, NBE approval numbers, proforma invoices, and shipping container documentation.',
            impact: 'Complete FX compliance',
            icon: Globe
        },
        {
            id: 'demurrage',
            stepNumber: '03',
            title: 'Demurrage Clock & Customs',
            module: 'Port Logistics',
            summary: 'Mojo Dry Port & Djibouti free-time countdown clock.',
            detail: 'Automatic countdown alerts alert logistics teams 72 hours before container demurrage fees trigger at Mojo dry port or Djibouti corridor.',
            impact: 'Eliminates demurrage penalties',
            icon: Clock
        },
        {
            id: 'ecx_receipt',
            stepNumber: '04',
            title: 'Warehouse & Lot Receipt',
            module: 'Agribusiness & Stock',
            summary: 'ECX grade verification, moisture testing, and barcode tag.',
            detail: 'Inspects goods against PO specifications. For coffee exporters, logs lot moisture, Q-score, and bag weights before moving to central export stores.',
            impact: '100% batch traceability',
            icon: Coffee
        },
        {
            id: 'ap',
            stepNumber: '05',
            title: 'Accounts Payable & 2% WHT',
            module: 'Accounts Payable',
            summary: 'Three-way match (PO + Receipt + Bill) and withholding receipt.',
            detail: 'Automated 3-way match verifies invoice amounts against goods received. Auto-withholds 2% Ethiopian WHT and generates vendor tax withholding certificates.',
            impact: 'Audit-ready tax deductions',
            icon: CheckCircle2
        }
    ];

    const currentSteps = activeFlow === 'orderToCash' ? orderToCashSteps : procureToPaySteps;
    const selectedStep = currentSteps[selectedStepIndex] || currentSteps[0];

    return (
        <section id="workflows" className="py-20 sm:py-28 bg-white relative overflow-hidden">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="max-w-3xl mx-auto text-center mb-14 space-y-4">
                    <span className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-50 text-[#00A76F] border border-emerald-100">
                        <GitMerge className="h-3.5 w-3.5" /> End-to-End Enterprise Flow
                    </span>
                    <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-slate-900 tracking-tight">
                        No module is an island. Data flows seamlessly.
                    </h2>
                    <p className="text-base sm:text-lg text-slate-600 leading-relaxed">
                        Watch how sales, inventory, MoR e-invoicing, and bank reconciliation operate as one continuous, automated heartbeat.
                    </p>

                    {/* Flow Switcher Toggle */}
                    <div className="inline-flex items-center p-1 rounded-2xl bg-slate-100 border border-slate-200 mt-4 text-xs font-bold">
                        <button
                            onClick={() => { setActiveFlow('orderToCash'); setSelectedStepIndex(2); }}
                            className={`px-5 py-2.5 rounded-xl transition-all ${
                                activeFlow === 'orderToCash'
                                    ? 'bg-white text-[#00A76F] shadow-md shadow-emerald-500/10'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            Order-to-Cash (National Standard)
                        </button>
                        <button
                            onClick={() => { setActiveFlow('procureToPay'); setSelectedStepIndex(2); }}
                            className={`px-5 py-2.5 rounded-xl transition-all ${
                                activeFlow === 'procureToPay'
                                    ? 'bg-white text-[#00A76F] shadow-md shadow-emerald-500/10'
                                    : 'text-slate-600 hover:text-slate-900'
                            }`}
                        >
                            Procure-to-Pay & Export (Corridor Standard)
                        </button>
                    </div>
                </div>

                {/* Pipeline Steps Tracker */}
                <div className="grid grid-cols-2 md:grid-cols-5 gap-3 lg:gap-4 mb-8">
                    {currentSteps.map((step, idx) => {
                        const Icon = step.icon;
                        const isSelected = selectedStepIndex === idx;
                        return (
                            <button
                                key={step.id}
                                onClick={() => setSelectedStepIndex(idx)}
                                className={`text-left p-4 rounded-2xl border transition-all duration-200 relative group flex flex-col justify-between ${
                                    isSelected
                                        ? 'bg-emerald-50/70 border-[#00A76F] shadow-lg shadow-emerald-500/10 -translate-y-1'
                                        : 'bg-white border-slate-200/80 hover:border-emerald-200 hover:bg-slate-50/50'
                                }`}
                            >
                                <div className="flex items-center justify-between mb-3">
                                    <div className={`w-9 h-9 rounded-xl flex items-center justify-center transition-colors ${
                                        isSelected ? 'bg-[#00A76F] text-white' : 'bg-slate-100 text-slate-600 group-hover:text-[#00A76F]'
                                    }`}>
                                        <Icon className="h-4 w-4" />
                                    </div>
                                    <span className="text-[11px] font-black text-slate-400">{step.stepNumber}</span>
                                </div>
                                <div>
                                    <span className="text-[10px] font-bold text-[#00A76F] uppercase tracking-wider block truncate">
                                        {step.module}
                                    </span>
                                    <h4 className="text-xs sm:text-sm font-extrabold text-slate-900 mt-0.5 line-clamp-1">
                                        {step.title}
                                    </h4>
                                </div>
                            </button>
                        );
                    })}
                </div>

                {/* Selected Stage Detail Showcase Card */}
                <div className="bg-gradient-to-br from-[#031B13] via-[#042A1D] to-[#022C22] text-white rounded-3xl p-6 sm:p-10 shadow-2xl border border-emerald-500/20 relative overflow-hidden">
                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center relative z-10">
                        {/* Left description */}
                        <div className="lg:col-span-7 space-y-4">
                            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/20 text-[#34D399] border border-emerald-500/30">
                                <span>Stage {selectedStep.stepNumber} of 05</span>
                                <span>·</span>
                                <span>{selectedStep.module}</span>
                            </div>

                            <h3 className="text-2xl sm:text-3xl font-black text-white tracking-tight">
                                {selectedStep.title}
                            </h3>

                            <p className="text-emerald-100/90 text-sm sm:text-base leading-relaxed">
                                {selectedStep.detail}
                            </p>

                            <div className="flex items-center gap-3 pt-2">
                                <div className="w-8 h-8 rounded-lg bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center text-[#34D399]">
                                    <CheckCircle2 className="h-4 w-4" />
                                </div>
                                <div>
                                    <span className="text-[10px] uppercase font-bold text-emerald-400 block">Verified Business Impact</span>
                                    <span className="text-xs sm:text-sm font-bold text-white">{selectedStep.impact}</span>
                                </div>
                            </div>
                        </div>

                        {/* Right interactive telemetry badge */}
                        <div className="lg:col-span-5 bg-emerald-950/80 rounded-2xl p-6 border border-emerald-500/30 space-y-4">
                            <div className="flex items-center justify-between border-b border-emerald-800/60 pb-3">
                                <span className="text-xs font-bold text-white flex items-center gap-2">
                                    <span className="w-2 h-2 rounded-full bg-[#34D399] animate-pulse" />
                                    Data Integrity Gate
                                </span>
                                <span className="text-[10px] font-mono text-emerald-400">STATUS: PASS</span>
                            </div>

                            <div className="space-y-2.5 text-xs">
                                <div className="flex items-center justify-between p-2 rounded-lg bg-emerald-900/40 border border-emerald-500/20">
                                    <span className="text-slate-300">Upstream Origin:</span>
                                    <span className="font-bold text-white">Verified</span>
                                </div>
                                <div className="flex items-center justify-between p-2 rounded-lg bg-emerald-900/40 border border-emerald-500/20">
                                    <span className="text-slate-300">Multi-Tenant Scoping:</span>
                                    <span className="font-bold text-[#34D399]">created_by Enforced</span>
                                </div>
                                <div className="flex items-center justify-between p-2 rounded-lg bg-emerald-900/40 border border-emerald-500/20">
                                    <span className="text-slate-300">Audit Trail:</span>
                                    <span className="font-mono text-[10px] text-emerald-300">Logged to DB</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
