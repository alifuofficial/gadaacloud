import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    ShieldCheck, 
    Receipt, 
    CloudOff, 
    AlertTriangle, 
    CheckCircle2, 
    ArrowRight, 
    Settings, 
    RefreshCw, 
    Building2, 
    Clock, 
    FileText, 
    ExternalLink,
    Lock
} from 'lucide-react';

interface EimsDashboardProps {
    metrics: {
        totalClearedCount: number;
        totalClearedAmount: number;
        totalVatAmount: number;
        pendingOfflineCount: number;
        failedCount: number;
        cancelledCount: number;
        urgentOfflineCount: number;
    };
    setting: {
        tin: string | null;
        vat_number: string | null;
        legal_name: string | null;
        branch_code: string | null;
        device_id: string | null;
        environment: 'sandbox' | 'production';
        isConfigured: boolean;
    } | null;
    recentInvoices: any[];
}

export default function EimsDashboard({ metrics, setting, recentInvoices = [] }: EimsDashboardProps) {
    const { t } = useTranslation();

    const breadcrumbs = [
        { label: t('E-Invoicing (EIMS)'), url: route('eims.dashboard') },
        { label: t('Dashboard'), url: route('eims.dashboard') }
    ];

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'ETB',
            minimumFractionDigits: 2
        }).format(amount);
    };

    const handleFlushQueue = () => {
        if (confirm(t('Execute immediate synchronization of all offline queued invoices with the Ministry of Revenues?'))) {
            router.post(route('eims.offline-queue.flush'));
        }
    };

    return (
        <AuthenticatedLayout breadcrumbs={breadcrumbs} pageTitle={t('Ethiopian E-Invoicing (EIMS Directive 1142)')}>
            <Head title={t('Ethiopian E-Invoicing (EIMS) Hub')} />

            <div className="space-y-6 p-4 md:p-6">
                {/* Header Section */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                {t('Ethiopian E-Invoicing Management (EIMS)')}
                            </h1>
                            <Badge variant={setting?.environment === 'production' ? 'default' : 'secondary'} className="font-semibold">
                                {setting?.environment === 'production' ? t('Live MoR Clearance') : t('Directive 1142 Sandbox')}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground mt-1">
                            {t('Ministry of Revenues Continuous Transaction Control (CTC) clearance, IRN hash generation & 72-hour offline resiliency.')}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {metrics.pendingOfflineCount > 0 && (
                            <Button 
                                variant="outline" 
                                className="border-amber-500/50 text-amber-600 hover:bg-amber-50 dark:text-amber-400"
                                onClick={handleFlushQueue}
                            >
                                <RefreshCw className="mr-2 h-4 w-4" />
                                {t('Sync Offline Queue ({{count}})', { count: metrics.pendingOfflineCount })}
                            </Button>
                        )}
                        <Link href={route('eims.settings.index')}>
                            <Button variant="outline">
                                <Settings className="mr-2 h-4 w-4" />
                                {t('MoR Credentials')}
                            </Button>
                        </Link>
                        <Link href={route('eims.invoices.index')}>
                            <Button>
                                <Receipt className="mr-2 h-4 w-4" />
                                {t('View All Cleared')}
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Configuration Warning */}
                {!setting?.isConfigured && (
                    <div className="rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/40">
                        <div className="flex items-start gap-3">
                            <AlertTriangle className="h-5 w-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                            <div className="flex-1">
                                <h3 className="text-sm font-semibold text-amber-900 dark:text-amber-200">
                                    {t('MoR Taxpayer Profile Not Configured')}
                                </h3>
                                <p className="text-xs text-amber-700 dark:text-amber-300 mt-1">
                                    {t('Your company has not yet configured its 10-digit Taxpayer Identification Number (TIN) or fiscal branch. Invoices cannot be cleared in production without regulatory credentials.')}
                                </p>
                            </div>
                            <Link href={route('eims.settings.index')}>
                                <Button size="sm" variant="outline" className="border-amber-400 text-amber-800 hover:bg-amber-100 dark:text-amber-300">
                                    {t('Configure Now')}
                                </Button>
                            </Link>
                        </div>
                    </div>
                )}

                {/* 72h Urgent Warning */}
                {metrics.urgentOfflineCount > 0 && (
                    <div className="rounded-xl border border-red-300 bg-red-50 p-4 dark:border-red-900/60 dark:bg-red-950/40">
                        <div className="flex items-start gap-3">
                            <Clock className="h-5 w-5 text-red-600 dark:text-red-400 shrink-0 mt-0.5 animate-pulse" />
                            <div className="flex-1">
                                <h3 className="text-sm font-semibold text-red-900 dark:text-red-200">
                                    {t('Critical: {{count}} Invoices Approaching 72-Hour Legal Deadline', { count: metrics.urgentOfflineCount })}
                                </h3>
                                <p className="text-xs text-red-700 dark:text-red-300 mt-1">
                                    {t('Directive 1142/2026 mandates that offline generated receipts must be transmitted to the Ministry within 72 hours. Synchronize immediately to avoid regulatory penalties.')}
                                </p>
                            </div>
                            <Button size="sm" variant="destructive" onClick={handleFlushQueue}>
                                <RefreshCw className="mr-2 h-4 w-4" />
                                {t('Sync Urgent Now')}
                            </Button>
                        </div>
                    </div>
                )}

                {/* KPI Metrics Grid */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('Cleared Invoices')}
                            </CardTitle>
                            <div className="rounded-md bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400">
                                <ShieldCheck className="h-5 w-5" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-foreground">
                                {metrics.totalClearedCount.toLocaleString()}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {t('Total volume: {{val}}', { val: formatCurrency(metrics.totalClearedAmount) })}
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('VAT Remitted (15%)')}
                            </CardTitle>
                            <div className="rounded-md bg-blue-500/10 p-2 text-blue-600 dark:text-blue-400">
                                <Building2 className="h-5 w-5" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-foreground">
                                {formatCurrency(metrics.totalVatAmount)}
                            </div>
                            <p className="text-xs text-emerald-600 dark:text-emerald-400 mt-1 flex items-center gap-1 font-medium">
                                <CheckCircle2 className="h-3.5 w-3.5" />
                                {t('MoR Reconciliation Verified')}
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('72h Offline Queue')}
                            </CardTitle>
                            <div className="rounded-md bg-amber-500/10 p-2 text-amber-600 dark:text-amber-400">
                                <CloudOff className="h-5 w-5" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-foreground">
                                {metrics.pendingOfflineCount}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {metrics.pendingOfflineCount > 0 
                                    ? t('Buffered safely in encrypted queue') 
                                    : t('All offline invoices cleared')}
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('Exceptions / Failed')}
                            </CardTitle>
                            <div className="rounded-md bg-rose-500/10 p-2 text-rose-600 dark:text-rose-400">
                                <AlertTriangle className="h-5 w-5" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-foreground">
                                {metrics.failedCount}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {t('{{count}} cancelled credit notes', { count: metrics.cancelledCount })}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Directive 1142 Features & Legal Assurance */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card className="border-border/60 bg-card/60 backdrop-blur">
                        <CardHeader className="pb-2">
                            <div className="flex items-center gap-2 text-primary">
                                <ShieldCheck className="h-5 w-5" />
                                <CardTitle className="text-base">{t('Directive No. 1142/2026')}</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="text-xs text-muted-foreground space-y-2">
                            <p>
                                {t('Fully conforms to the new Ministry of Revenues e-invoicing regulation repealing Directive 149/2018. Continuous Transaction Control (CTC) ensures all B2B, B2C, and B2G supplies are cleared in real-time.')}
                            </p>
                            <div className="flex items-center gap-1.5 font-medium text-foreground">
                                <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" />
                                {t('Cryptographic 64-character IRN Hash')}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/60 bg-card/60 backdrop-blur">
                        <CardHeader className="pb-2">
                            <div className="flex items-center gap-2 text-amber-600 dark:text-amber-400">
                                <Clock className="h-5 w-5" />
                                <CardTitle className="text-base">{t('72-Hour Offline Resiliency')}</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="text-xs text-muted-foreground space-y-2">
                            <p>
                                {t('Under Article 18 / Annex 2, cashiers and sales reps can continue issuing valid receipts during internet outages. Payloads are encrypted with AES-256 and synchronized within 72 hours.')}
                            </p>
                            <div className="flex items-center gap-1.5 font-medium text-foreground">
                                <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" />
                                {t('Automatic Background FIFO Re-sync')}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/60 bg-card/60 backdrop-blur">
                        <CardHeader className="pb-2">
                            <div className="flex items-center gap-2 text-indigo-600 dark:text-indigo-400">
                                <Lock className="h-5 w-5" />
                                <CardTitle className="text-base">{t('INSA Tamper-Evident Logs')}</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="text-xs text-muted-foreground space-y-2">
                            <p>
                                {t('Every clearance, offline buffer, and cancellation is cryptographically chained using HMAC-SHA256 signatures, fulfilling Information Network Security Administration (INSA) requirements.')}
                            </p>
                            <div className="flex items-center gap-1.5 font-medium text-foreground">
                                <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" />
                                {t('Blockchain-Style Signature Chain')}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Recent Invoices Table */}
                <Card className="shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="text-base font-semibold">{t('Recent Cleared Invoices')}</CardTitle>
                            <CardDescription className="text-xs">
                                {t('Latest transactions registered and validated with the Ministry of Revenues')}
                            </CardDescription>
                        </div>
                        <Link href={route('eims.invoices.index')}>
                            <Button variant="ghost" size="sm" className="gap-1 text-xs">
                                {t('View All')}
                                <ArrowRight className="h-3.5 w-3.5" />
                            </Button>
                        </Link>
                    </CardHeader>
                    <CardContent>
                        {recentInvoices.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-10 text-center">
                                <Receipt className="h-10 w-10 text-muted-foreground/40 mb-3" />
                                <p className="text-sm font-medium text-muted-foreground">{t('No EIMS cleared invoices yet')}</p>
                                <p className="text-xs text-muted-foreground/80 mt-1 max-w-sm">
                                    {t('When you post a Sales Invoice in GadaaCloud, it will automatically register with MoR and display here.')}
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b text-xs font-medium text-muted-foreground">
                                            <th className="pb-3">{t('Invoice #')}</th>
                                            <th className="pb-3">{t('Type')}</th>
                                            <th className="pb-3">{t('Buyer')}</th>
                                            <th className="pb-3">{t('IRN Hash')}</th>
                                            <th className="pb-3 text-right">{t('Total Amount')}</th>
                                            <th className="pb-3 text-right">{t('VAT (15%)')}</th>
                                            <th className="pb-3 text-center">{t('Status')}</th>
                                            <th className="pb-3 text-right">{t('Action')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border/50 text-xs">
                                        {recentInvoices.map((inv) => (
                                            <tr key={inv.id} className="hover:bg-muted/40 transition-colors">
                                                <td className="py-3 font-semibold text-foreground">
                                                    {inv.invoice_number}
                                                </td>
                                                <td className="py-3">
                                                    <Badge variant="outline" className="text-[10px] uppercase font-mono">
                                                        {inv.buyer_type}
                                                    </Badge>
                                                </td>
                                                <td className="py-3 max-w-[150px] truncate">
                                                    {inv.buyer_name || 'Consumer'}
                                                </td>
                                                <td className="py-3 font-mono text-[11px] text-muted-foreground max-w-[160px] truncate">
                                                    {inv.irn ? `${inv.irn.slice(0, 14)}...${inv.irn.slice(-6)}` : '—'}
                                                </td>
                                                <td className="py-3 text-right font-medium">
                                                    {formatCurrency(Number(inv.total_amount))}
                                                </td>
                                                <td className="py-3 text-right text-emerald-600 dark:text-emerald-400 font-medium">
                                                    {formatCurrency(Number(inv.tax_amount))}
                                                </td>
                                                <td className="py-3 text-center">
                                                    <Badge 
                                                        variant={
                                                            inv.status === 'CLEARED' ? 'default' :
                                                            inv.status === 'PENDING_OFFLINE' ? 'secondary' :
                                                            inv.status === 'CANCELLED' ? 'destructive' : 'outline'
                                                        }
                                                        className="text-[10px]"
                                                    >
                                                        {inv.status}
                                                    </Badge>
                                                </td>
                                                <td className="py-3 text-right">
                                                    <Link href={route('eims.invoices.show', inv.id)}>
                                                        <Button variant="ghost" size="sm" className="h-7 px-2 text-xs">
                                                            {t('Details')}
                                                        </Button>
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
