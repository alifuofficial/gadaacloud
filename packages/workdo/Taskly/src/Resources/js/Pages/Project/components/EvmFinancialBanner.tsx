import React, { useState } from 'react';
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { DollarSign, TrendingUp, TrendingDown, AlertTriangle, Copy, Check, ExternalLink, ShieldCheck, Clock } from 'lucide-react';
import { formatCurrency } from '@/utils/helpers';

interface EvmMetrics {
    budget: number;
    currency: string;
    labor_actual: number;
    material_actual: number;
    actual_cost: number;
    planned_value: number;
    earned_value: number;
    progress_percent: number;
    cost_variance: number;
    schedule_variance: number;
    cpi: number;
    spi: number;
    is_over_budget: boolean;
    is_behind_schedule: boolean;
}

interface EvmBannerProps {
    metrics: EvmMetrics;
    shareUrl?: string;
    projectType?: string;
    projectScope?: string;
    internalCategory?: string;
}

export function EvmFinancialBanner({ 
    metrics, 
    shareUrl = '', 
    projectType = 'general',
    projectScope = 'client',
    internalCategory
}: EvmBannerProps) {
    const [copied, setCopied] = useState(false);

    const handleCopy = () => {
        navigator.clipboard.writeText(shareUrl);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="mb-6 space-y-3">
            {/* Top Bar with Client Magic Link & Workspace Type */}
            <div className="flex flex-wrap items-center justify-between gap-3 p-3 bg-white dark:bg-slate-900 border rounded-2xl shadow-sm">
                <div className="flex items-center gap-2">
                    <Badge variant="outline" className="capitalize text-xs font-semibold bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                        {projectType} Workspace
                    </Badge>
                    {projectScope === 'internal' ? (
                        <Badge className="bg-purple-100 text-purple-800 border-purple-300 dark:bg-purple-950 dark:text-purple-300 text-xs font-semibold uppercase tracking-wider">
                            In-House / Internal ({internalCategory || 'OpEx'})
                        </Badge>
                    ) : (
                        <Badge className="bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-950 dark:text-blue-300 text-xs font-semibold">
                            Client Delivery
                        </Badge>
                    )}
                    <div className="h-4 w-px bg-slate-200 dark:bg-slate-700 mx-1" />
                    <span className="text-xs text-slate-500 font-mono">
                        Base Currency: <strong>{metrics.currency}</strong>
                    </span>
                    {metrics.is_over_budget && (
                        <Badge variant="destructive" className="text-[10px] gap-1 animate-pulse">
                            <AlertTriangle className="w-3 h-3" /> Budget Overrun Alert
                        </Badge>
                    )}
                    {metrics.is_behind_schedule && (
                        <Badge className="bg-amber-100 text-amber-800 border-amber-300 text-[10px] gap-1">
                            <Clock className="w-3 h-3" /> Behind Schedule
                        </Badge>
                    )}
                </div>

                {/* Scope Action Pill */}
                <div className="flex items-center gap-2">
                    {projectScope === 'internal' ? (
                        <div className="flex items-center gap-2 px-3 py-1 bg-purple-50 dark:bg-purple-950/40 border border-purple-200 dark:border-purple-800/60 rounded-full text-xs">
                            <ShieldCheck className="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" />
                            <span className="text-purple-900 dark:text-purple-200 font-medium">Internal Cost Center Project</span>
                        </div>
                    ) : (
                        shareUrl && (
                            <div className="flex items-center gap-2 px-3 py-1 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-full text-xs">
                                <ShieldCheck className="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                                <span className="text-emerald-800 dark:text-emerald-300 font-medium hidden sm:inline">Client Live Portal:</span>
                                <code className="text-[11px] text-emerald-900 dark:text-emerald-200 font-mono truncate max-w-[160px]">
                                    {shareUrl}
                                </code>
                                <TooltipProvider>
                                    <Tooltip delayDuration={0}>
                                        <TooltipTrigger asChild>
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                onClick={handleCopy}
                                                className="h-6 w-6 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100"
                                            >
                                                {copied ? <Check className="w-3.5 h-3.5 text-emerald-600" /> : <Copy className="w-3.5 h-3.5" />}
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{copied ? 'Link Copied!' : 'Copy Client Tracking Link'}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                                <a
                                    href={shareUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-emerald-700 hover:text-emerald-900 p-1"
                                >
                                    <ExternalLink className="w-3.5 h-3.5" />
                                </a>
                            </div>
                        )
                    )}
                </div>
            </div>

            {/* EVM Metric Cards */}
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                {/* 1. Budget at Completion (BAC) */}
                <Card className="border shadow-none bg-slate-50/50 dark:bg-slate-900/50">
                    <CardContent className="p-3.5 space-y-1">
                        <span className="text-[11px] font-medium text-slate-500 uppercase tracking-wider block">
                            Budget (BAC)
                        </span>
                        <div className="text-base font-bold text-slate-900 dark:text-white font-mono">
                            {formatCurrency(metrics.budget)}
                        </div>
                        <span className="text-[10px] text-slate-400 block font-mono">Planned Cap</span>
                    </CardContent>
                </Card>

                {/* 2. Actual Cost (AC) */}
                <Card className="border shadow-none bg-slate-50/50 dark:bg-slate-900/50">
                    <CardContent className="p-3.5 space-y-1">
                        <span className="text-[11px] font-medium text-slate-500 uppercase tracking-wider block">
                            Actual Cost (AC)
                        </span>
                        <div className={`text-base font-bold font-mono ${metrics.is_over_budget ? 'text-red-600' : 'text-slate-900 dark:text-white'}`}>
                            {formatCurrency(metrics.actual_cost)}
                        </div>
                        <span className="text-[10px] text-slate-400 block font-mono">
                            Labor: {formatCurrency(metrics.labor_actual)}
                        </span>
                    </CardContent>
                </Card>

                {/* 3. Earned Value (EV) */}
                <Card className="border shadow-none bg-slate-50/50 dark:bg-slate-900/50">
                    <CardContent className="p-3.5 space-y-1">
                        <span className="text-[11px] font-medium text-slate-500 uppercase tracking-wider block">
                            Earned Value (EV)
                        </span>
                        <div className="text-base font-bold text-emerald-600 font-mono">
                            {formatCurrency(metrics.earned_value)}
                        </div>
                        <span className="text-[10px] text-slate-400 block font-mono">
                            {metrics.progress_percent}% Delivered
                        </span>
                    </CardContent>
                </Card>

                {/* 4. Cost Variance (CV) */}
                <Card className="border shadow-none bg-slate-50/50 dark:bg-slate-900/50">
                    <CardContent className="p-3.5 space-y-1">
                        <span className="text-[11px] font-medium text-slate-500 uppercase tracking-wider block">
                            Cost Variance (CV)
                        </span>
                        <div className={`text-base font-bold font-mono flex items-center gap-1 ${metrics.cost_variance >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>
                            {metrics.cost_variance >= 0 ? <TrendingUp className="w-3.5 h-3.5" /> : <TrendingDown className="w-3.5 h-3.5" />}
                            {formatCurrency(metrics.cost_variance)}
                        </div>
                        <span className="text-[10px] text-slate-400 block font-mono">EV - AC</span>
                    </CardContent>
                </Card>

                {/* 5. Cost Performance Index (CPI) */}
                <Card className="border shadow-none bg-slate-50/50 dark:bg-slate-900/50">
                    <CardContent className="p-3.5 space-y-1">
                        <span className="text-[11px] font-medium text-slate-500 uppercase tracking-wider block">
                            Cost Index (CPI)
                        </span>
                        <div className={`text-base font-bold font-mono ${metrics.cpi >= 1.0 ? 'text-emerald-600' : 'text-red-600'}`}>
                            {metrics.cpi.toFixed(2)}
                        </div>
                        <span className="text-[10px] text-slate-400 block font-mono">
                            {metrics.cpi >= 1.0 ? 'Under/On Budget' : 'Cost Inefficiency'}
                        </span>
                    </CardContent>
                </Card>

                {/* 6. Schedule Performance Index (SPI) */}
                <Card className="border shadow-none bg-slate-50/50 dark:bg-slate-900/50">
                    <CardContent className="p-3.5 space-y-1">
                        <span className="text-[11px] font-medium text-slate-500 uppercase tracking-wider block">
                            Schedule Index (SPI)
                        </span>
                        <div className={`text-base font-bold font-mono ${metrics.spi >= 1.0 ? 'text-emerald-600' : 'text-amber-600'}`}>
                            {metrics.spi.toFixed(2)}
                        </div>
                        <span className="text-[10px] text-slate-400 block font-mono">
                            {metrics.spi >= 1.0 ? 'On / Ahead Schedule' : 'Schedule Delay'}
                        </span>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

export default EvmFinancialBanner;
