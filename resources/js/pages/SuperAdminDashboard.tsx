import React from 'react';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Head, Link, router } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { AreaChart, PieChart } from '@/components/charts';
import {
    Building2, ShoppingCart, CreditCard, Crown, Sparkles, Cpu,
    TrendingUp, AlertTriangle, CheckCircle2, Clock, ExternalLink,
    ShieldCheck, HardDrive, Users, Landmark, Layers, Plus,
    ArrowUpRight, Activity, Globe, Database, Server
} from "lucide-react";
import { formatAdminCurrency } from '@/utils/helpers';

interface SuperAdminDashboardProps {
    stats: {
        order_payments: number;
        total_orders: number;
        total_plans: number;
        total_companies: number;
        active_paid: number;
        active_trial: number;
        free_plan: number;
        expired_churn: number;
        mrr_etb: number;
        mrr_usd: number;
        arr_usd: number;
        currency_breakdown: Record<string, string | number>;
        pending_transfers: number;
        new_companies_this_month: number;
        tenant_growth_rate: number;
        ai_total_tokens?: number;
        ai_total_cost?: number;
    };
    chartData: Array<{
        month: string;
        orders: number;
        payments: number;
    }>;
    planChartData: Array<{
        name: string;
        count: number;
        color: string;
    }>;
    topModules: Array<{
        module: string;
        name: string;
        count: number;
    }>;
    expiringCompanies: Array<{
        id: number;
        name: string;
        email: string;
        plan_name: string;
        expire_date: string;
        days_left: number;
    }>;
    recentCompanies: Array<{
        id: number;
        name: string;
        email: string;
        plan_name: string;
        is_free_plan: boolean;
        is_expired: boolean;
        created_at: string;
        total_users: number;
        status: string;
    }>;
    telemetry: {
        storage_used_bytes: number;
        failed_jobs: number;
        php_version: string;
        db_driver: string;
        active_modules_count: number;
    };
}

export default function SuperAdminDashboard({
    stats,
    chartData,
    planChartData,
    topModules,
    expiringCompanies,
    recentCompanies,
    telemetry
}: SuperAdminDashboardProps) {
    const { t } = useTranslation();

    const handleImpersonate = (companyId: number) => {
        router.post(route('users.impersonate', companyId));
    };

    const formatStorageMb = (bytes: number) => {
        if (!bytes || bytes <= 0) return '0 MB';
        const mb = bytes / (1024 * 1024);
        if (mb > 1024) {
            return `${(mb / 1024).toFixed(2)} GB`;
        }
        return `${mb.toFixed(1)} MB`;
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('SaaS Command Center') }]}
            pageTitle={t('SaaS Platform Command Center')}
        >
            <Head title={t('SaaS Command Center - Super Admin')} />

            <div className="space-y-6 pb-8">
                {/* Executive Quick Actions & Platform Status Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-card border rounded-xl p-4 shadow-sm">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <h2 className="text-xl font-bold tracking-tight">{t('Platform Operations & Governance')}</h2>
                            <Badge variant="outline" className="text-xs bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border-emerald-300">
                                <Activity className="w-3 h-3 mr-1 animate-pulse" />
                                {t('Live SaaS Runtime')}
                            </Badge>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {t('Multi-tenant subscription metrics, revenue velocity, tenant retention, and cloud infrastructure telemetry.')}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild size="sm" variant="default" className="shadow-sm">
                            <Link href={route('users.create')}>
                                <Plus className="w-4 h-4 mr-1.5" />
                                {t('New Company')}
                            </Link>
                        </Button>

                        <Button asChild size="sm" variant={stats.pending_transfers > 0 ? "destructive" : "outline"} className="relative">
                            <Link href={route('bank-transfer.index')}>
                                <Landmark className="w-4 h-4 mr-1.5" />
                                {t('Bank Transfers')}
                                {stats.pending_transfers > 0 && (
                                    <span className="ml-1.5 px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-white text-destructive">
                                        {stats.pending_transfers}
                                    </span>
                                )}
                            </Link>
                        </Button>

                        <Button asChild size="sm" variant="outline">
                            <Link href={route('plans.index')}>
                                <Crown className="w-4 h-4 mr-1.5 text-amber-500" />
                                {t('Plans')}
                            </Link>
                        </Button>

                        <Button asChild size="sm" variant="outline">
                            <Link href={route('modules.list')}>
                                <Layers className="w-4 h-4 mr-1.5 text-blue-500" />
                                {t('Add-on Modules')}
                            </Link>
                        </Button>

                        <Button asChild size="sm" variant="outline">
                            <Link href={route('settings.domains.index')}>
                                <Globe className="w-4 h-4 mr-1.5 text-purple-500" />
                                {t('Domains')}
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* SaaS Core Financial & Tenant KPI Cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    {/* Card 1: MRR & ARR */}
                    <Card className="relative overflow-hidden border-emerald-500/30 bg-gradient-to-br from-emerald-50/70 via-emerald-50/30 to-background dark:from-emerald-950/20 dark:via-background dark:to-background">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                {t('Monthly Recurring (MRR)')}
                            </CardTitle>
                            <div className="p-2 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-lg">
                                <TrendingUp className="h-5 w-5" />
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-1">
                            <div className="text-2xl font-extrabold text-foreground tracking-tight">
                                {stats.mrr_etb > 0 && <span>ETB {stats.mrr_etb.toLocaleString()}</span>}
                                {stats.mrr_etb > 0 && stats.mrr_usd > 0 && <span className="text-muted-foreground text-sm mx-1">/</span>}
                                {stats.mrr_usd > 0 && <span>${stats.mrr_usd.toLocaleString()}</span>}
                                {stats.mrr_etb === 0 && stats.mrr_usd === 0 && <span>$0.00</span>}
                            </div>
                            <div className="flex items-center justify-between text-xs text-muted-foreground pt-1 border-t border-border/60">
                                <span>{t('Annual Run Rate (ARR)')}</span>
                                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                    ${stats.arr_usd.toLocaleString()}
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Card 2: Active Tenants & Lifecycle */}
                    <Card className="relative overflow-hidden border-blue-500/30 bg-gradient-to-br from-blue-50/70 via-blue-50/30 to-background dark:from-blue-950/20 dark:via-background dark:to-background">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                {t('Active Paid Tenants')}
                            </CardTitle>
                            <div className="p-2 bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-lg">
                                <Building2 className="h-5 w-5" />
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-1">
                            <div className="text-2xl font-extrabold text-foreground tracking-tight flex items-baseline gap-2">
                                <span>{stats.active_paid}</span>
                                <span className="text-xs font-normal text-muted-foreground">/ {stats.total_companies} {t('Total')}</span>
                            </div>
                            <div className="flex items-center justify-between text-xs text-muted-foreground pt-1 border-t border-border/60">
                                <span>{t('Free / Trial / Expired')}</span>
                                <span className="font-medium">
                                    <span className="text-blue-600">{stats.free_plan}</span> / <span className="text-amber-600">{stats.active_trial}</span> / <span className="text-rose-600">{stats.expired_churn}</span>
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Card 3: Total Orders & Lifetime Payments */}
                    <Card className="relative overflow-hidden border-indigo-500/30 bg-gradient-to-br from-indigo-50/70 via-indigo-50/30 to-background dark:from-indigo-950/20 dark:via-background dark:to-background">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                {t('Total Payments Volume')}
                            </CardTitle>
                            <div className="p-2 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded-lg">
                                <CreditCard className="h-5 w-5" />
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-1">
                            <div className="text-2xl font-extrabold text-foreground tracking-tight">
                                {formatAdminCurrency(stats.order_payments)}
                            </div>
                            <div className="flex items-center justify-between text-xs text-muted-foreground pt-1 border-t border-border/60">
                                <span>{t('Processed Orders')}</span>
                                <span className="font-semibold text-indigo-600 dark:text-indigo-400">
                                    {stats.total_orders} {t('Orders')}
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Card 4: Pending Bank Transfers Action Card */}
                    <Card className={`relative overflow-hidden border-amber-500/30 bg-gradient-to-br ${stats.pending_transfers > 0 ? 'from-amber-100/80 via-amber-50/40 to-background dark:from-amber-950/30' : 'from-slate-50/70 via-slate-50/30 to-background dark:from-slate-900/40'}`}>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                {t('Pending Bank Requests')}
                            </CardTitle>
                            <div className={`p-2 rounded-lg ${stats.pending_transfers > 0 ? 'bg-amber-500/20 text-amber-600' : 'bg-slate-500/10 text-slate-600'}`}>
                                <Landmark className="h-5 w-5" />
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-1">
                            <div className="text-2xl font-extrabold tracking-tight flex items-center gap-2">
                                <span className={stats.pending_transfers > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-foreground'}>
                                    {stats.pending_transfers}
                                </span>
                                {stats.pending_transfers > 0 ? (
                                    <Badge variant="destructive" className="text-[10px] px-1.5 py-0">
                                        {t('Action Required')}
                                    </Badge>
                                ) : (
                                    <span className="text-xs text-emerald-600 flex items-center font-normal">
                                        <CheckCircle2 className="w-3.5 h-3.5 mr-1 inline" />
                                        {t('Settled')}
                                    </span>
                                )}
                            </div>
                            <div className="flex items-center justify-between text-xs text-muted-foreground pt-1 border-t border-border/60">
                                <span>{t('Bank Verification')}</span>
                                <Link href={route('bank-transfer.index')} className="text-primary hover:underline font-medium inline-flex items-center">
                                    {t('Review')}
                                    <ArrowUpRight className="w-3 h-3 ml-0.5" />
                                </Link>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Card 5: AI Copilot Token Consumption */}
                    <Card className="relative overflow-hidden bg-slate-900 text-white border-purple-500/40 shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-xs font-semibold uppercase tracking-wider text-purple-200 flex items-center gap-1.5">
                                <Sparkles className="w-3.5 h-3.5 text-purple-400 animate-pulse" />
                                {t('AI Copilot Usage')}
                            </CardTitle>
                            <Cpu className="h-5 w-5 text-emerald-400 opacity-90" />
                        </CardHeader>
                        <CardContent className="space-y-1">
                            <div className="text-2xl font-extrabold font-mono text-white">
                                {(stats.ai_total_tokens || 0).toLocaleString()}
                            </div>
                            <div className="flex items-center justify-between text-xs text-slate-300 pt-1 border-t border-white/10">
                                <span className="text-slate-400">{t('Token Monetization')}</span>
                                <span className="font-bold text-emerald-400 font-mono">
                                    {formatAdminCurrency(stats.ai_total_cost || 0)}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Visual Analytics Row: Revenue Velocity & Plan Distribution */}
                <div className="grid gap-6 lg:grid-cols-12">
                    {/* Revenue & Order Velocity (8 Cols) */}
                    <Card className="lg:col-span-8 shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <div>
                                <CardTitle className="text-base font-bold">{t('Revenue & Order Velocity')}</CardTitle>
                                <CardDescription>{t('Monthly subscription transactions and payment performance for')} {new Date().getFullYear()}</CardDescription>
                            </div>
                            <Badge variant="outline" className="text-xs">
                                {t('Annual Run')}
                            </Badge>
                        </CardHeader>
                        <CardContent className="pt-4">
                            <AreaChart
                                data={chartData}
                                dataKey="payments"
                                xAxisKey="month"
                                height={290}
                                color="#10b981"
                                gradient={true}
                                showGrid={true}
                                showTooltip={true}
                                areas={[
                                    { dataKey: 'payments', color: '#10b981', name: t('Payments Volume') }
                                ]}
                            />
                        </CardContent>
                    </Card>

                    {/* Plan Distribution Donut Chart (4 Cols) */}
                    <Card className="lg:col-span-4 shadow-sm flex flex-col justify-between">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-base font-bold">{t('Plan Tier Distribution')}</CardTitle>
                            <CardDescription>{t('Proportion of tenant workspaces per subscription tier')}</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col items-center justify-center pt-2">
                            <div className="w-full h-[210px]">
                                <PieChart
                                    data={planChartData}
                                    dataKey="count"
                                    nameKey="name"
                                    donut={true}
                                    innerRadius={55}
                                    outerRadius={80}
                                    height={210}
                                    showTooltip={true}
                                />
                            </div>
                            <div className="w-full mt-3 grid grid-cols-1 gap-1.5 border-t pt-3">
                                {planChartData.map((item, idx) => (
                                    <div key={idx} className="flex items-center justify-between text-xs">
                                        <div className="flex items-center gap-2">
                                            <span className="w-2.5 h-2.5 rounded-full" style={{ backgroundColor: item.color }} />
                                            <span className="font-medium text-foreground">{item.name}</span>
                                        </div>
                                        <span className="font-semibold text-muted-foreground">{item.count} {t('Tenants')}</span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Operations & Watchlist Row */}
                <div className="grid gap-6 lg:grid-cols-12">
                    {/* Top Adopted Modules (6 Cols) */}
                    <Card className="lg:col-span-6 shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-3">
                            <div>
                                <CardTitle className="text-base font-bold flex items-center gap-2">
                                    <Layers className="w-4 h-4 text-primary" />
                                    {t('Top Adopted Add-on Modules')}
                                </CardTitle>
                                <CardDescription>{t('Most activated packages across tenant workspaces')}</CardDescription>
                            </div>
                            <Button asChild variant="ghost" size="sm">
                                <Link href={route('modules.list')} className="text-xs text-primary">
                                    {t('View All')} &rarr;
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-3.5">
                            {topModules.length > 0 ? (
                                topModules.map((mod, index) => {
                                    const percentage = stats.total_companies > 0 
                                        ? Math.min(100, Math.round((mod.count / stats.total_companies) * 100))
                                        : 0;
                                    return (
                                        <div key={index} className="space-y-1">
                                            <div className="flex items-center justify-between text-xs">
                                                <span className="font-medium text-foreground">{mod.name || mod.module}</span>
                                                <span className="text-muted-foreground font-semibold">
                                                    {mod.count} {t('Companies')} ({percentage}%)
                                                </span>
                                            </div>
                                            <div className="w-full bg-secondary h-2 rounded-full overflow-hidden">
                                                <div 
                                                    className="bg-primary h-full rounded-full transition-all duration-500" 
                                                    style={{ width: `${Math.max(5, percentage)}%` }}
                                                />
                                            </div>
                                        </div>
                                    );
                                })
                            ) : (
                                <p className="text-xs text-muted-foreground text-center py-6">{t('No active modules recorded yet.')}</p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Subscription Expiration Watchlist (< 14 Days) (6 Cols) */}
                    <Card className="lg:col-span-6 shadow-sm">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base font-bold flex items-center gap-2">
                                <Clock className="w-4 h-4 text-amber-500" />
                                {t('Retention Watchlist (Expiring < 14 Days)')}
                            </CardTitle>
                            <CardDescription>{t('Tenants whose subscription is nearing expiration')}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {expiringCompanies.length > 0 ? (
                                <div className="border rounded-lg overflow-hidden">
                                    <Table>
                                        <TableHeader className="bg-muted/50">
                                            <TableRow>
                                                <TableHead className="text-xs">{t('Company')}</TableHead>
                                                <TableHead className="text-xs">{t('Plan')}</TableHead>
                                                <TableHead className="text-xs">{t('Expire Date')}</TableHead>
                                                <TableHead className="text-xs text-right">{t('Action')}</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {expiringCompanies.map((c) => (
                                                <TableRow key={c.id}>
                                                    <TableCell className="text-xs font-medium">
                                                        <div>{c.name}</div>
                                                        <div className="text-[11px] text-muted-foreground">{c.email}</div>
                                                    </TableCell>
                                                    <TableCell className="text-xs">
                                                        <Badge variant="outline" className="text-[10px]">{c.plan_name}</Badge>
                                                    </TableCell>
                                                    <TableCell className="text-xs">
                                                        <Badge variant="secondary" className="text-amber-600 bg-amber-50 border-amber-200">
                                                            {c.days_left} {t('days left')}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-xs text-right">
                                                        <Button size="sm" variant="ghost" className="h-7 text-xs" onClick={() => handleImpersonate(c.id)}>
                                                            {t('Inspect')}
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center p-6 text-center border border-dashed rounded-lg bg-emerald-50/20">
                                    <ShieldCheck className="w-10 h-10 text-emerald-500 mb-2" />
                                    <p className="text-sm font-semibold text-emerald-700 dark:text-emerald-400">{t('All Subscriptions Healthy')}</p>
                                    <p className="text-xs text-muted-foreground mt-1 max-w-sm">
                                        {t('Zero tenant workspaces are due for expiration in the next 14 days. Retention is stable.')}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Recent Companies Directory with 1-Click Impersonate */}
                <Card className="shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between pb-3">
                        <div>
                            <CardTitle className="text-base font-bold flex items-center gap-2">
                                <Building2 className="w-4 h-4 text-primary" />
                                {t('Recently Registered Company Workspaces')}
                            </CardTitle>
                            <CardDescription>{t('Latest tenant accounts onboarded with quick impersonation access')}</CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm">
                            <Link href={route('users.index')} className="text-xs">
                                {t('Manage All Companies')} &rarr;
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div className="border rounded-lg overflow-x-auto">
                            <Table>
                                <TableHeader className="bg-muted/50">
                                    <TableRow>
                                        <TableHead className="text-xs">{t('Company & Contact')}</TableHead>
                                        <TableHead className="text-xs">{t('Plan Tier')}</TableHead>
                                        <TableHead className="text-xs">{t('Workspace Users')}</TableHead>
                                        <TableHead className="text-xs">{t('Joined Date')}</TableHead>
                                        <TableHead className="text-xs">{t('Status')}</TableHead>
                                        <TableHead className="text-xs text-right">{t('Action')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recentCompanies.map((comp) => (
                                        <TableRow key={comp.id}>
                                            <TableCell className="text-xs">
                                                <div className="font-semibold text-foreground">{comp.name}</div>
                                                <div className="text-[11px] text-muted-foreground">{comp.email}</div>
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                <Badge 
                                                    variant="secondary" 
                                                    className={comp.is_free_plan ? "bg-slate-100 text-slate-700" : "bg-primary/10 text-primary border-primary/20"}
                                                >
                                                    {comp.plan_name}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-xs font-mono">
                                                <Users className="w-3.5 h-3.5 inline mr-1 text-muted-foreground" />
                                                {comp.total_users} {t('members')}
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground">
                                                {comp.created_at}
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                <Badge 
                                                    variant="outline"
                                                    className={comp.status === 'Active' 
                                                        ? "text-emerald-700 bg-emerald-50 border-emerald-300"
                                                        : "text-rose-700 bg-rose-50 border-rose-300"}
                                                >
                                                    {comp.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-xs text-right">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="h-7 text-xs hover:bg-primary hover:text-primary-foreground transition-all"
                                                    onClick={() => handleImpersonate(comp.id)}
                                                    title={t('Login as company workspace to inspect or configure')}
                                                >
                                                    <ExternalLink className="w-3 h-3 mr-1" />
                                                    {t('Login as Company')}
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                {/* Cloud Infrastructure & System Telemetry Footer */}
                <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-4 text-xs">
                    <div className="flex items-center gap-2 p-3 bg-card border rounded-lg shadow-2xs">
                        <HardDrive className="w-4 h-4 text-blue-500 shrink-0" />
                        <div>
                            <div className="text-muted-foreground font-medium">{t('Tenant Storage Consumed')}</div>
                            <div className="font-bold text-foreground">{formatStorageMb(telemetry.storage_used_bytes)}</div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 p-3 bg-card border rounded-lg shadow-2xs">
                        <Server className="w-4 h-4 text-emerald-500 shrink-0" />
                        <div>
                            <div className="text-muted-foreground font-medium">{t('Queue / Background Workers')}</div>
                            <div className="font-bold text-foreground">
                                {telemetry.failed_jobs === 0 ? (
                                    <span className="text-emerald-600 flex items-center gap-1">
                                        <CheckCircle2 className="w-3 h-3" /> {t('Healthy (0 failed)')}
                                    </span>
                                ) : (
                                    <span className="text-rose-600 font-semibold">{telemetry.failed_jobs} {t('Failed Jobs')}</span>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 p-3 bg-card border rounded-lg shadow-2xs">
                        <Database className="w-4 h-4 text-purple-500 shrink-0" />
                        <div>
                            <div className="text-muted-foreground font-medium">{t('Engine & Database')}</div>
                            <div className="font-bold text-foreground">PHP {telemetry.php_version} ({telemetry.db_driver.toUpperCase()})</div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 p-3 bg-card border rounded-lg shadow-2xs">
                        <Layers className="w-4 h-4 text-amber-500 shrink-0" />
                        <div>
                            <div className="text-muted-foreground font-medium">{t('Active Modules Catalog')}</div>
                            <div className="font-bold text-foreground">{telemetry.active_modules_count} {t('Packages Enabled')}</div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
