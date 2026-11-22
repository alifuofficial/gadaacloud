import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    Lock, 
    ShieldCheck, 
    ArrowLeft, 
    Copy, 
    Check, 
    Filter, 
    FileText, 
    Eye,
    Globe
} from 'lucide-react';

interface AuditLogsProps {
    logs: {
        data: any[];
        links: any[];
        current_page: number;
        total: number;
    };
    filters: {
        action?: string;
    };
}

export default function AuditLogs({ logs, filters = {} }: AuditLogsProps) {
    const { t } = useTranslation();
    const [actionFilter, setActionFilter] = useState(filters.action || '');
    const [copiedHash, setCopiedHash] = useState<string | null>(null);
    const [expandedLogId, setExpandedLogId] = useState<number | null>(null);

    const breadcrumbs = [
        { label: t('E-Invoicing (EIMS)'), url: route('eims.dashboard') },
        { label: t('INSA Audit Logs'), url: route('eims.audit-logs.index') },
    ];

    const copyText = (text: string, id: string) => {
        navigator.clipboard.writeText(text);
        setCopiedHash(id);
        setTimeout(() => setCopiedHash(null), 2000);
    };

    const handleFilterChange = (action: string) => {
        setActionFilter(action);
        router.get(route('eims.audit-logs.index'), {
            action: action || undefined,
        }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout breadcrumbs={breadcrumbs} pageTitle={t('INSA Tamper-Evident Audit Trail')}>
            <Head title={t('INSA Audit Trail - Directive 1142/2026')} />

            <div className="space-y-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                {t('INSA Cryptographic Audit Trail')}
                            </h1>
                            <Badge variant="outline" className="font-semibold text-xs">
                                {t('HMAC-SHA256 Chained')}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground mt-1">
                            {t('Information Network Security Administration compliance log proving non-repudiation and invoice ledger integrity.')}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <select
                            aria-label="Filter by Audit Action"
                            className="h-9 rounded-md border border-input bg-background px-3 py-1 text-xs shadow-sm"
                            value={actionFilter}
                            onChange={(e) => handleFilterChange(e.target.value)}
                        >
                            <option value="">{t('All Actions')}</option>
                            <option value="REGISTER">{t('REGISTER')}</option>
                            <option value="OFFLINE_QUEUE">{t('OFFLINE_QUEUE')}</option>
                            <option value="SYNC_OFFLINE">{t('SYNC_OFFLINE')}</option>
                            <option value="CANCEL">{t('CANCEL')}</option>
                            <option value="SETTINGS_UPDATE">{t('SETTINGS_UPDATE')}</option>
                        </select>
                    </div>
                </div>

                {/* Explanation Card */}
                <div className="rounded-xl border border-indigo-200 bg-indigo-50/70 p-4 dark:border-indigo-900/60 dark:bg-indigo-950/30">
                    <div className="flex items-start gap-3">
                        <Lock className="h-5 w-5 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5" />
                        <div className="space-y-1 text-xs text-indigo-950 dark:text-indigo-200">
                            <div className="font-semibold text-sm">
                                {t('Blockchain-Style Cryptographic Immutability')}
                            </div>
                            <p className="leading-relaxed">
                                {t('Each log entry incorporates the cryptographic signature of the preceding log (Previous Hash) plus a SHA256 digest of the canonical transmission payload. Altering any cleared invoice or log entry invalidates the entire chain.')}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Logs Table */}
                <Card className="shadow-sm">
                    <CardContent className="p-0">
                        {logs.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <FileText className="h-12 w-12 text-muted-foreground/40 mb-3" />
                                <p className="text-base font-medium text-foreground">{t('No audit records yet')}</p>
                                <p className="text-xs text-muted-foreground mt-1 max-w-sm">
                                    {t('Audit events will be chained here as soon as invoices are registered or synchronized.')}
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead>
                                        <tr className="border-b bg-muted/30 font-semibold text-muted-foreground">
                                            <th className="p-3 pl-4">ID</th>
                                            <th className="p-3">{t('Timestamp')}</th>
                                            <th className="p-3">{t('Action')}</th>
                                            <th className="p-3">{t('Operator')}</th>
                                            <th className="p-3">{t('Previous Hash (Chain)')}</th>
                                            <th className="p-3">{t('Payload Digest (SHA256)')}</th>
                                            <th className="p-3">{t('HMAC Signature')}</th>
                                            <th className="p-3 pr-4 text-right">{t('Details')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y font-mono text-[11px]">
                                        {logs.data.map((log) => {
                                            const isExpanded = expandedLogId === log.id;

                                            return (
                                                <React.Fragment key={log.id}>
                                                    <tr className="hover:bg-muted/40 transition-colors">
                                                        <td className="p-3 pl-4 text-muted-foreground font-sans">
                                                            #{log.id}
                                                        </td>
                                                        <td className="p-3 font-sans text-foreground">
                                                            {new Date(log.created_at).toLocaleString()}
                                                        </td>
                                                        <td className="p-3">
                                                            <Badge 
                                                                variant={
                                                                    log.action === 'REGISTER' ? 'default' :
                                                                    log.action === 'SYNC_OFFLINE' ? 'default' :
                                                                    log.action === 'CANCEL' ? 'destructive' : 'outline'
                                                                }
                                                                className="font-sans text-[10px]"
                                                            >
                                                                {log.action}
                                                            </Badge>
                                                        </td>
                                                        <td className="p-3 font-sans">
                                                            {log.user?.name || t('System Core')}
                                                        </td>
                                                        <td className="p-3 text-muted-foreground">
                                                            <div className="flex items-center gap-1">
                                                                <span>{log.previous_hash ? `${log.previous_hash.slice(0, 10)}...` : 'GENESIS'}</span>
                                                                {log.previous_hash && (
                                                                    <button 
                                                                        onClick={() => copyText(log.previous_hash, `prev_${log.id}`)}
                                                                        className="hover:text-foreground p-0.5"
                                                                    >
                                                                        {copiedHash === `prev_${log.id}` ? <Check className="h-3 w-3 text-emerald-500" /> : <Copy className="h-3 w-3" />}
                                                                    </button>
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="p-3 text-muted-foreground">
                                                            <div className="flex items-center gap-1">
                                                                <span>{log.payload_hash.slice(0, 12)}...</span>
                                                                <button 
                                                                    onClick={() => copyText(log.payload_hash, `pay_${log.id}`)}
                                                                    className="hover:text-foreground p-0.5"
                                                                >
                                                                    {copiedHash === `pay_${log.id}` ? <Check className="h-3 w-3 text-emerald-500" /> : <Copy className="h-3 w-3" />}
                                                                </button>
                                                            </div>
                                                        </td>
                                                        <td className="p-3 text-emerald-600 dark:text-emerald-400 font-semibold">
                                                            <div className="flex items-center gap-1">
                                                                <span>{log.signature.slice(0, 14)}...</span>
                                                                <button 
                                                                    onClick={() => copyText(log.signature, `sig_${log.id}`)}
                                                                    className="hover:text-foreground p-0.5"
                                                                >
                                                                    {copiedHash === `sig_${log.id}` ? <Check className="h-3 w-3 text-emerald-500" /> : <Copy className="h-3 w-3" />}
                                                                </button>
                                                            </div>
                                                        </td>
                                                        <td className="p-3 pr-4 text-right font-sans">
                                                            <Button 
                                                                variant="ghost" 
                                                                size="sm" 
                                                                className="h-6 px-2 text-[11px]"
                                                                onClick={() => setExpandedLogId(isExpanded ? null : log.id)}
                                                            >
                                                                {isExpanded ? t('Hide') : t('Inspect')}
                                                            </Button>
                                                        </td>
                                                    </tr>

                                                    {isExpanded && (
                                                        <tr className="bg-muted/30">
                                                            <td colSpan={8} className="p-4">
                                                                <div className="space-y-2 text-xs font-sans">
                                                                    <div className="flex flex-wrap gap-4 text-muted-foreground text-[11px]">
                                                                        <div><span className="font-semibold text-foreground">IP Address:</span> {log.ip_address || '127.0.0.1'}</div>
                                                                        <div><span className="font-semibold text-foreground">User Agent:</span> {log.user_agent || 'GadaaCloud-EIMS'}</div>
                                                                        {log.eims_invoice_id && (
                                                                            <div>
                                                                                <span className="font-semibold text-foreground">Invoice Link:</span>{' '}
                                                                                <Link href={route('eims.invoices.show', log.eims_invoice_id)} className="text-primary hover:underline">
                                                                                    {t('View Invoice #{{id}}', { id: log.eims_invoice_id })}
                                                                                </Link>
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                    <pre className="p-3 rounded bg-slate-900 text-slate-100 font-mono text-[10px] overflow-x-auto">
                                                                        {JSON.stringify(log.details || {}, null, 2)}
                                                                    </pre>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    )}
                                                </React.Fragment>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {/* Pagination */}
                        {logs.links && logs.links.length > 3 && (
                            <div className="flex items-center justify-between border-t p-3 text-xs text-muted-foreground">
                                <div>
                                    {t('Total {{total}} INSA cryptographic audit logs', { total: logs.total })}
                                </div>
                                <div className="flex gap-1">
                                    {logs.links.map((link: any, idx: number) => (
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
