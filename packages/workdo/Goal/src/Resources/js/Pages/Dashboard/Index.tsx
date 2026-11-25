import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Target,
    TrendingUp,
    AlertTriangle,
    CheckCircle2,
    Calendar,
    ArrowRight,
    Flag,
    DollarSign,
    Layers,
    Clock,
    ShieldAlert
} from "lucide-react";
import { formatCurrency, formatDate } from '@/utils/helpers';

interface DashboardProps {
    metrics: {
        totalTarget: number;
        totalAchieved: number;
        overallProgress: number;
        activeGoalsCount: number;
        completedGoalsCount: number;
        pausedGoalsCount: number;
        atRiskCount: number;
        ethiopianFiscalYear: string;
    };
    typeBreakdown: Array<{
        goal_type: string;
        count: number;
        total_target: number;
        total_current: number;
    }>;
    atRiskGoals: Array<{
        id: number;
        goal_name: string;
        goal_type: string;
        target_amount: number;
        current_amount: number;
        target_date: string;
        category?: { category_name: string };
        tracking?: Array<{ on_track_status: string; progress_percentage: number }>;
    }>;
    upcomingMilestones: Array<{
        id: number;
        milestone_name: string;
        target_amount: number;
        target_date: string;
        status: string;
        goal?: { id: number; goal_name: string };
    }>;
    monthlyTrend: Array<{
        month: string;
        total: number;
    }>;
}

export default function Dashboard({
    metrics = {
        totalTarget: 0,
        totalAchieved: 0,
        overallProgress: 0,
        activeGoalsCount: 0,
        completedGoalsCount: 0,
        pausedGoalsCount: 0,
        atRiskCount: 0,
        ethiopianFiscalYear: '2018 E.C.',
    },
    typeBreakdown = [],
    atRiskGoals = [],
    upcomingMilestones = [],
    monthlyTrend = [],
}: DashboardProps) {
    const { t } = useTranslation();

    const safeMetrics = metrics || {
        totalTarget: 0,
        totalAchieved: 0,
        overallProgress: 0,
        activeGoalsCount: 0,
        completedGoalsCount: 0,
        pausedGoalsCount: 0,
        atRiskCount: 0,
        ethiopianFiscalYear: '2018 E.C.',
    };

    const getPaceBadge = (status?: string) => {
        switch (status) {
            case 'ahead':
                return <Badge className="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20">{t('Ahead')}</Badge>;
            case 'on_track':
                return <Badge className="bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20">{t('On Track')}</Badge>;
            case 'behind':
                return <Badge className="bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20">{t('Behind')}</Badge>;
            case 'critical':
            default:
                return <Badge className="bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20">{t('Critical')}</Badge>;
        }
    };

    const getGoalTypeLabel = (type: string) => {
        switch (type) {
            case 'savings': return t('Savings');
            case 'debt_reduction': return t('Debt Reduction');
            case 'expense_reduction': return t('Expense Reduction');
            case 'revenue_target': return t('Revenue Target');
            default: return type;
        }
    };

    return (
        <AuthenticatedLayout
            title={t('Financial Goal Intelligence Dashboard')}
            breadcrumb={[
                { label: t('Goal'), url: route('goal.goals.index') },
                { label: t('Dashboard') },
            ]}
        >
            <Head title={t('Financial Goal Intelligence Dashboard')} />

            <div className="space-y-6">
                {/* Header & Ethiopian Fiscal Year Badge */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">{t('Goal Performance & Capital Allocation')}</h1>
                        <p className="text-sm text-muted-foreground">{t('Strategic enterprise goal execution, funding run-rates, and risk telemetry')}</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant="outline" className="px-3 py-1.5 text-xs font-semibold gap-1.5 bg-background shadow-xs">
                            <Calendar className="w-3.5 h-3.5 text-primary" />
                            <span>{t('Ethiopian Fiscal Cycle')}: <strong>{safeMetrics.ethiopianFiscalYear}</strong></span>
                        </Badge>
                        <Link href={route('goal.goals.index')}>
                            <Button size="sm" variant="default" className="gap-1.5">
                                <Target className="w-4 h-4" />
                                {t('Manage Goals')}
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* KPI Metrics Strip */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card className="shadow-xs border-border/60">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">{t('Total Target Capital')}</CardTitle>
                            <DollarSign className="w-4 h-4 text-primary" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatCurrency(safeMetrics.totalTarget || 0)}</div>
                            <div className="flex items-center justify-between text-xs text-muted-foreground mt-2">
                                <span>{t('Funded')}: {formatCurrency(safeMetrics.totalAchieved || 0)}</span>
                                <span className="font-semibold text-primary">{safeMetrics.overallProgress || 0}%</span>
                            </div>
                            <div className="w-full bg-secondary rounded-full h-1.5 mt-1.5 overflow-hidden">
                                <div
                                    className="bg-primary h-1.5 rounded-full transition-all duration-500"
                                    style={{ width: `${Math.min(100, safeMetrics.overallProgress || 0)}%` }}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="shadow-xs border-border/60">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">{t('Active Goals')}</CardTitle>
                            <Target className="w-4 h-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{safeMetrics.activeGoalsCount || 0}</div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {t('Completed')}: <strong className="text-emerald-600">{safeMetrics.completedGoalsCount || 0}</strong> • {t('Paused')}: {safeMetrics.pausedGoalsCount || 0}
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="shadow-xs border-border/60">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">{t('Capital Velocity')}</CardTitle>
                            <TrendingUp className="w-4 h-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {formatCurrency((monthlyTrend || []).length > 0 ? Number(monthlyTrend[monthlyTrend.length - 1]?.total || 0) : 0)}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">{t('Contributions logged this month')}</p>
                        </CardContent>
                    </Card>

                    <Card className="shadow-xs border-border/60">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">{t('Pace Risk Watchlist')}</CardTitle>
                            <ShieldAlert className="w-4 h-4 text-rose-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-rose-600">{safeMetrics.atRiskCount || 0}</div>
                            <p className="text-xs text-muted-foreground mt-1">{t('Goals behind schedule or approaching deadline')}</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Middle Grid: Type Breakdown & Monthly Run-Rate */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Goal Types Breakdown */}
                    <Card className="shadow-xs border-border/60 lg:col-span-1">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base font-semibold flex items-center gap-2">
                                <Layers className="w-4 h-4 text-primary" />
                                {t('Strategy & Classification')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {(typeBreakdown || []).length === 0 ? (
                                <p className="text-xs text-muted-foreground py-4 text-center">{t('No goal classifications available.')}</p>
                            ) : (
                                (typeBreakdown || []).map((item, idx) => {
                                    const progress = item.total_target > 0
                                        ? Math.min(100, Math.round((Number(item.total_current || 0) / Number(item.total_target || 1)) * 100))
                                        : 0;
                                    return (
                                        <div key={idx} className="space-y-1.5 p-2.5 rounded-lg bg-muted/30 border border-border/40">
                                            <div className="flex justify-between items-center text-xs">
                                                <span className="font-medium text-foreground">{getGoalTypeLabel(item.goal_type)}</span>
                                                <Badge variant="secondary" className="text-[10px] px-1.5 py-0.5">{item.count} {t('goals')}</Badge>
                                            </div>
                                            <div className="flex justify-between items-center text-xs text-muted-foreground">
                                                <span>{formatCurrency(Number(item.total_current || 0))}</span>
                                                <span>{progress}%</span>
                                            </div>
                                            <div className="w-full bg-secondary rounded-full h-1 overflow-hidden">
                                                <div className="bg-primary h-1 rounded-full" style={{ width: `${progress}%` }} />
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </CardContent>
                    </Card>

                    {/* Monthly Contribution Run-rate */}
                    <Card className="shadow-xs border-border/60 lg:col-span-2">
                        <CardHeader className="pb-3 flex flex-row items-center justify-between">
                            <CardTitle className="text-base font-semibold flex items-center gap-2">
                                <TrendingUp className="w-4 h-4 text-emerald-500" />
                                {t('Contribution Run-Rate (Last 6 Months)')}
                            </CardTitle>
                            <Link href={route('goal.contributions.index')}>
                                <Button variant="ghost" size="sm" className="text-xs gap-1">
                                    {t('View All')} <ArrowRight className="w-3.5 h-3.5" />
                                </Button>
                            </Link>
                        </CardHeader>
                        <CardContent>
                            {(monthlyTrend || []).length === 0 ? (
                                <p className="text-xs text-muted-foreground py-8 text-center">{t('No recent contributions recorded.')}</p>
                            ) : (
                                <div className="space-y-3 pt-2">
                                    {(monthlyTrend || []).map((trend, idx) => {
                                        const maxAmount = Math.max(...(monthlyTrend || []).map(m => Number(m.total || 0)), 1);
                                        const pct = Math.min(100, Math.round((Number(trend.total || 0) / maxAmount) * 100));
                                        return (
                                            <div key={idx} className="space-y-1">
                                                <div className="flex justify-between text-xs">
                                                    <span className="font-mono text-muted-foreground">{trend.month}</span>
                                                    <span className="font-semibold text-foreground">{formatCurrency(Number(trend.total || 0))}</span>
                                                </div>
                                                <div className="w-full bg-secondary rounded-full h-2 overflow-hidden">
                                                    <div className="bg-emerald-500 h-2 rounded-full transition-all duration-500" style={{ width: `${pct}%` }} />
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Bottom Grid: At-Risk Watchlist & Upcoming Milestones */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* At-Risk Watchlist */}
                    <Card className="shadow-xs border-border/60">
                        <CardHeader className="pb-3 flex flex-row items-center justify-between">
                            <CardTitle className="text-base font-semibold flex items-center gap-2 text-rose-600 dark:text-rose-400">
                                <AlertTriangle className="w-4 h-4" />
                                {t('At-Risk Execution Watchlist')}
                            </CardTitle>
                            <Link href={route('goal.goals.index')}>
                                <Button variant="ghost" size="sm" className="text-xs gap-1">
                                    {t('Explore')} <ArrowRight className="w-3.5 h-3.5" />
                                </Button>
                            </Link>
                        </CardHeader>
                        <CardContent>
                            {(atRiskGoals || []).length === 0 ? (
                                <div className="text-center py-6 text-muted-foreground text-xs flex flex-col items-center gap-1.5">
                                    <CheckCircle2 className="w-6 h-6 text-emerald-500" />
                                    <span>{t('All active financial goals are tracking normally on schedule!')}</span>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {(atRiskGoals || []).map((goal) => {
                                        const latestPace = goal.tracking?.[0]?.on_track_status || 'behind';
                                        return (
                                            <div key={goal.id} className="p-3 rounded-lg border border-border/50 bg-card hover:bg-muted/40 transition-colors flex items-center justify-between gap-4">
                                                <div className="space-y-1 min-w-0">
                                                    <div className="flex items-center gap-2">
                                                        <Link href={route('goal.goals.show', goal.id)} className="text-sm font-medium hover:underline truncate">
                                                            {goal.goal_name}
                                                        </Link>
                                                        {getPaceBadge(latestPace)}
                                                    </div>
                                                    <p className="text-xs text-muted-foreground">
                                                        {goal.category?.category_name} • {t('Due')}: {formatDate(goal.target_date)}
                                                    </p>
                                                </div>
                                                <div className="text-right shrink-0">
                                                    <div className="text-xs font-semibold">{formatCurrency(goal.current_amount || 0)}</div>
                                                    <div className="text-[10px] text-muted-foreground">/ {formatCurrency(goal.target_amount || 0)}</div>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Upcoming Milestones */}
                    <Card className="shadow-xs border-border/60">
                        <CardHeader className="pb-3 flex flex-row items-center justify-between">
                            <CardTitle className="text-base font-semibold flex items-center gap-2">
                                <Flag className="w-4 h-4 text-primary" />
                                {t('Upcoming Capital Milestones')}
                            </CardTitle>
                            <Link href={route('goal.milestones.index')}>
                                <Button variant="ghost" size="sm" className="text-xs gap-1">
                                    {t('View All')} <ArrowRight className="w-3.5 h-3.5" />
                                </Button>
                            </Link>
                        </CardHeader>
                        <CardContent>
                            {(upcomingMilestones || []).length === 0 ? (
                                <p className="text-xs text-muted-foreground py-6 text-center">{t('No upcoming pending milestones.')}</p>
                            ) : (
                                <div className="space-y-3">
                                    {(upcomingMilestones || []).map((ms) => (
                                        <div key={ms.id} className="p-3 rounded-lg border border-border/50 bg-card hover:bg-muted/40 transition-colors flex items-center justify-between gap-4">
                                            <div className="space-y-0.5 min-w-0">
                                                <div className="text-sm font-medium truncate">{ms.milestone_name}</div>
                                                <p className="text-xs text-muted-foreground truncate">
                                                    {ms.goal?.goal_name} • {t('Deadline')}: {formatDate(ms.target_date)}
                                                </p>
                                            </div>
                                            <div className="text-right shrink-0">
                                                <span className="text-xs font-bold text-primary">{formatCurrency(ms.target_amount || 0)}</span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
