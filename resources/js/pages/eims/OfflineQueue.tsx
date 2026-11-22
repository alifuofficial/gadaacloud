import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    CloudOff, 
    RefreshCw, 
    Clock, 
    AlertTriangle, 
    CheckCircle2, 
    ShieldCheck, 
    ArrowLeft, 
    Trash2, 
    Receipt,
    Eye
} from 'lucide-react';

interface OfflineQueueProps {
    items: {
        data: any[];
        links: any[];
        current_page: number;
        total: number;
    };
    filters: {
        status?: string;
    };
    metrics: {
        activeCount: number;
        expiredCount: number;
        completedCount: number;
    };
}

export default function OfflineQueue({ items, filters = {}, metrics }: OfflineQueueProps) {
    const { t } = useTranslation();
    const [isFlushing, setIsFlushing] = useState(false);

    const breadcrumbs = [
        { label: t('E-Invoicing (EIMS)'), url: route('eims.dashboard') },
        { label: t('72h Offline Queue'), url: route('eims.offline-queue.index') },
    ];

    const handleFlush = () => {
        setIsFlushing(true);
        router.post(route('eims.offline-queue.flush'), {}, {
            onFinish: () => setIsFlushing(false)
        });
    };

    const handleDelete = (id: number) => {
        if (confirm(t('Are you sure you want to dismiss this offline queue item?'))) {
            router.delete(route('eims.offline-queue.destroy', id));
        }
    };

    return (
        <AuthenticatedLayout breadcrumbs={breadcrumbs} pageTitle={t('EIMS 72-Hour Offline Resiliency Buffer')}>
            <Head title={t('Offline Resiliency Queue - Directive 1142/2026')} />

            <div className="space-y-6 p-4 md:p-6">
                {/* Header Section */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                {t('72-Hour Offline Resiliency Queue')}
                            </h1>
                            <Badge variant="outline" className="text-xs font-semibold">
                                {t('Article 18 / Annex 2')}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground mt-1">
                            {t('Encrypted temporary buffer preserving transactions during internet outages. Transmission legally mandated within 72 hours.')}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button 
                            onClick={handleFlush} 
                            disabled={isFlushing || metrics.activeCount === 0}
                            className="gap-2"
                        >
                            <RefreshCw className={`h-4 w-4 ${isFlushing ? 'animate-spin' : ''}`} />
                            {isFlushing ? t('Synchronizing with MoR...') : t('Flush Queue Now')}
                        </Button>
                    </div>
                </div>

                {/* KPI Metrics */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('Pending Transmission')}
                            </CardTitle>
                            <div className="rounded-md bg-amber-500/10 p-2 text-amber-600 dark:text-amber-400">
                                <Clock className="h-5 w-5" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-foreground">
                                {metrics.activeCount}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {t('Invoices waiting for MoR clearance')}
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('Synchronized & Cleared')}
                            </CardTitle>
                            <div className="rounded-md bg-emerald-500/10 p-2 text-emerald-600 dark:text-emerald-400">
                                <CheckCircle2 className="h-5 w-5" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-foreground">
                                {metrics.completedCount}
                            </div>
                            <p className="text-xs text-emerald-600 dark:text-emerald-400 mt-1 font-medium">
                                {t('Successfully uploaded to MoR')}
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {t('Expired (> 72 Hours)')}
                            </CardTitle>
                            <div className="rounded-md bg-rose-500/10 p-2 text-rose-600 dark:text-rose-400">
                                <AlertTriangle className="h-5 w-5" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-foreground">
                                {metrics.expiredCount}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {t('Non-compliant regulatory breach')}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Legal Advisory Banner */}
                <div className="rounded-xl border border-blue-200 bg-blue-50/70 p-4 dark:border-blue-900/60 dark:bg-blue-950/30">
                    <div className="flex items-start gap-3">
                        <ShieldCheck className="h-5 w-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                        <div className="space-y-1 text-xs text-blue-900 dark:text-blue-200">
                            <div className="font-semibold text-sm">
                                {t('Directive No. 1142/2026 Resiliency Protocol')}
                            </div>
                            <p className="leading-relaxed">
                                {t('When internet connectivity is restored, GadaaCloud background cron automatically transmits offline items in FIFO order. Receipts issued during outages carry temporary offline sentinels, which are substituted with official government IRNs upon clearance.')}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Queue Table */}
                <Card className="shadow-sm">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="text-base font-semibold">{t('Queued Invoices Buffer')}</CardTitle>
                            <CardDescription className="text-xs">
                                {t('Current offline queue backlog with countdown timers')}
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        {items.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <CheckCircle2 className="h-12 w-12 text-emerald-500/60 mb-3" />
                                <p className="text-base font-medium text-foreground">{t('Offline queue is clear!')}</p>
                                <p className="text-xs text-muted-foreground mt-1 max-w-sm">
                                    {t('All issued sales invoices are actively synchronized and cleared with the Ministry of Revenues.')}
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b bg-muted/30 text-xs font-semibold text-muted-foreground">
                                            <th className="p-3 pl-4">{t('Invoice #')}</th>
                                            <th className="p-3">{t('Buyer')}</th>
                                            <th className="p-3">{t('Enqueued Time')}</th>
                                            <th className="p-3">{t('Legal Deadline (72h)')}</th>
                                            <th className="p-3 text-center">{t('Attempts')}</th>
                                            <th className="p-3">{t('Last Gateway Diagnostic')}</th>
                                            <th className="p-3 text-center">{t('Status')}</th>
                                            <th className="p-3 pr-4 text-right">{t('Action')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y text-xs">
                                        {items.data.map((item) => {
                                            const inv = item.eims_invoice || {};
                                            const isUrgent = item.hours_remaining < 24 && item.status === 'QUEUED';

                                            return (
                                                <tr key={item.id} className="hover:bg-muted/40 transition-colors">
                                                    <td className="p-3 pl-4 font-semibold text-foreground">
                                                        {inv.invoice_number || '—'}
                                                    </td>
                                                    <td className="p-3">
                                                        {inv.buyer_name || 'Consumer'}
                                                    </td>
                                                    <td className="p-3 text-muted-foreground font-mono text-[11px]">
                                                        {new Date(item.created_at).toLocaleString()}
                                                    </td>
                                                    <td className="p-3">
                                                        <Badge 
                                                            variant={item.is_expired ? 'destructive' : isUrgent ? 'destructive' : 'secondary'}
                                                            className="text-[10px] font-mono gap-1"
                                                        >
                                                            <Clock className="h-3 w-3" />
                                                            {item.is_expired 
                                                                ? t('EXPIRED') 
                                                                : t('{{hours}}h left', { hours: item.hours_remaining })}
                                                        </Badge>
                                                    </td>
                                                    <td className="p-3 text-center font-mono">
                                                        {item.attempt_count} / {item.max_attempts}
                                                    </td>
                                                    <td className="p-3 max-w-[200px] truncate text-muted-foreground text-[11px]">
                                                        {item.last_error || t('Awaiting next network flush window')}
                                                    </td>
                                                    <td className="p-3 text-center">
                                                        <Badge 
                                                            variant={
                                                                item.status === 'COMPLETED' ? 'default' :
                                                                item.status === 'EXPIRED' ? 'destructive' : 'outline'
                                                            }
                                                            className="text-[10px]"
                                                        >
                                                            {item.status}
                                                        </Badge>
                                                    </td>
                                                    <td className="p-3 pr-4 text-right">
                                                        <div className="flex items-center justify-end gap-1">
                                                            {inv.id && (
                                                                <Link href={route('eims.invoices.show', inv.id)}>
                                                                    <Button variant="ghost" size="sm" className="h-7 w-7 p-0" title={t('View Invoice Details')}>
                                                                        <Eye className="h-3.5 w-3.5" />
                                                                    </Button>
                                                                </Link>
                                                            )}
                                                            <Button 
                                                                variant="ghost" 
                                                                size="sm" 
                                                                className="h-7 w-7 p-0 text-red-600 hover:text-red-700 hover:bg-red-50"
                                                                title={t('Delete item')}
                                                                onClick={() => handleDelete(item.id)}
                                                            >
                                                                <Trash2 className="h-3.5 w-3.5" />
                                                            </Button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {/* Pagination */}
                        {items.links && items.links.length > 3 && (
                            <div className="flex items-center justify-between border-t p-3 text-xs text-muted-foreground">
                                <div>
                                    {t('Total {{total}} queue items', { total: items.total })}
                                </div>
                                <div className="flex gap-1">
                                    {items.links.map((link: any, idx: number) => (
                                        <button
                                            key={idx}
                                            disabled={!link.url}
                                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                            className={`h-7 px-2.5 rounded border text-xs font-medium transition-colors ${
                                                link.active
                                                    ? 'bg-primary text-primary-foreground border-primary'
                                                    : 'bg-background hover:bg-muted text-foreground'
                                            } ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
