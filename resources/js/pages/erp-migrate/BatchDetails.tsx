import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from '@/components/ui/dialog';
import { toast } from 'sonner';
import { 
    Layers, 
    RotateCcw, 
    ArrowLeft, 
    CheckCircle2, 
    AlertTriangle, 
    History, 
    FileText, 
    ExternalLink, 
    Database, 
    ShieldCheck, 
    Search,
    ChevronDown,
    ChevronUp
} from 'lucide-react';

interface BatchDetailsProps {
    batch: any;
    logs: {
        data: any[];
        links: any[];
        current_page: number;
        last_page: number;
        total: number;
    };
}

export default function BatchDetails({ batch, logs }: BatchDetailsProps) {
    const { t } = useTranslation();
    const [rollbackModalOpen, setRollbackModalOpen] = useState(false);
    const [expandedRows, setExpandedRows] = useState<Record<number, boolean>>({});
    const [filterStatus, setFilterStatus] = useState<'all' | 'success' | 'warning' | 'skipped' | 'error'>('all');
    const [searchQuery, setSearchQuery] = useState('');

    const toggleRow = (id: number) => {
        setExpandedRows((prev) => ({ ...prev, [id]: !prev[id] }));
    };

    const handleConfirmRollback = () => {
        router.post(route('erp-migrate.batches.rollback', batch.id), {}, {
            onSuccess: () => {
                setRollbackModalOpen(false);
                toast.success(t('Batch rolled back and records cleaned up successfully.'));
            },
        });
    };

    const filteredLogs = logs.data.filter((l) => {
        const matchesStatus = filterStatus === 'all' || l.status === filterStatus;
        const q = searchQuery.toLowerCase();
        const matchesSearch = !q || (l.record_identifier && l.record_identifier.toLowerCase().includes(q)) || (l.message && l.message.toLowerCase().includes(q));
        return matchesStatus && matchesSearch;
    });

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('ERP Data Migration'), url: route('erp-migrate.index') },
                { label: t('Batch Audit Details') }
            ]}
            pageTitle={`${t('Batch Details')}: ${batch.batch_code}`}
        >
            <Head title={`${batch.batch_code} - ${t('Migration Audit Details & Rollback')}`} />

            <div className="space-y-6 max-w-6xl mx-auto">
                {/* Header Banner */}
                <div className="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <Link
                                href={route('erp-migrate.index')}
                                className="w-8 h-8 rounded-lg border flex items-center justify-center text-slate-500 hover:bg-slate-50"
                            >
                                <ArrowLeft className="w-4 h-4" />
                            </Link>
                            <h2 className="text-lg font-bold text-slate-900 font-mono">
                                {batch.batch_code}
                            </h2>
                            <Badge className={`text-xs ${
                                batch.status === 'completed'
                                    ? 'bg-emerald-100 text-emerald-800 border-emerald-300'
                                    : batch.status === 'rolled_back'
                                    ? 'bg-slate-100 text-slate-600 border-slate-300'
                                    : 'bg-rose-100 text-rose-800'
                            }`}>
                                {batch.status.toUpperCase()}
                            </Badge>
                        </div>
                        <p className="text-xs text-slate-500">
                            {t('Entity')}: <strong className="text-slate-800 capitalize">{batch.entity_type.replace('_', ' ')}</strong> • {t('Source')}: <span className="font-mono">{batch.original_filename || batch.source_type}</span>
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        {batch.status === 'completed' && (
                            <Button
                                onClick={() => setRollbackModalOpen(true)}
                                variant="outline"
                                className="border-rose-300 text-rose-600 hover:bg-rose-50 text-xs h-9 gap-1.5 font-semibold"
                            >
                                <RotateCcw className="w-3.5 h-3.5" />
                                {t('1-Click Rollback')}
                            </Button>
                        )}
                        <Link
                            href={route('erp-migrate.wizard')}
                            className="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 shadow-xs"
                        >
                            <span>{t('New Migration')}</span>
                        </Link>
                    </div>
                </div>

                {/* KPI Metrics */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <Card className="border border-slate-200/80 shadow-2xs">
                        <CardContent className="p-4">
                            <span className="text-[11px] font-semibold text-slate-500 uppercase">{t('Total Scanned')}</span>
                            <h3 className="text-2xl font-extrabold text-slate-900 font-mono mt-0.5">{batch.total_records}</h3>
                        </CardContent>
                    </Card>

                    <Card className="border border-slate-200/80 shadow-2xs">
                        <CardContent className="p-4">
                            <span className="text-[11px] font-semibold text-emerald-700 uppercase">{t('Successfully Imported')}</span>
                            <h3 className="text-2xl font-extrabold text-emerald-700 font-mono mt-0.5">{batch.imported_count}</h3>
                        </CardContent>
                    </Card>

                    <Card className="border border-slate-200/80 shadow-2xs">
                        <CardContent className="p-4">
                            <span className="text-[11px] font-semibold text-slate-500 uppercase">{t('Skipped Records')}</span>
                            <h3 className="text-2xl font-extrabold text-slate-700 font-mono mt-0.5">{batch.skipped_count}</h3>
                        </CardContent>
                    </Card>

                    <Card className="border border-slate-200/80 shadow-2xs">
                        <CardContent className="p-4">
                            <span className="text-[11px] font-semibold text-rose-700 uppercase">{t('Failed Records')}</span>
                            <h3 className="text-2xl font-extrabold text-rose-700 font-mono mt-0.5">{batch.failed_count}</h3>
                        </CardContent>
                    </Card>
                </div>

                {/* Row-by-Row Audit Logs */}
                <Card className="border border-slate-200/80 shadow-xs">
                    <CardHeader className="p-4 border-b bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <FileText className="w-4 h-4 text-emerald-600" />
                            <CardTitle className="text-sm font-bold text-slate-900">{t('Granular Row-by-Row Audit Log')}</CardTitle>
                        </div>

                        {/* Search & Status Filters */}
                        <div className="flex items-center gap-2 flex-wrap">
                            <div className="flex items-center border rounded-lg bg-white overflow-hidden h-8">
                                {(['all', 'success', 'warning', 'skipped', 'error'] as const).map((s) => (
                                    <button
                                        key={s}
                                        type="button"
                                        onClick={() => setFilterStatus(s)}
                                        className={`px-2.5 text-[11px] font-semibold h-full transition-colors capitalize ${
                                            filterStatus === s
                                                ? 'bg-emerald-600 text-white'
                                                : 'text-slate-600 hover:bg-slate-50'
                                        }`}
                                    >
                                        {s}
                                    </button>
                                ))}
                            </div>
                            <div className="w-48">
                                <input
                                    type="text"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    placeholder={t('Search log...')}
                                    className="h-8 text-xs border rounded-lg px-2.5 w-full bg-white"
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0 overflow-x-auto">
                        <table className="w-full text-xs text-left">
                            <thead className="bg-slate-50 border-b text-slate-500 uppercase font-semibold text-[11px]">
                                <tr>
                                    <th className="p-3 pl-5">#</th>
                                    <th className="p-3">{t('Identifier')}</th>
                                    <th className="p-3">{t('Status')}</th>
                                    <th className="p-3">{t('Target Record')}</th>
                                    <th className="p-3">{t('Message')}</th>
                                    <th className="p-3 pr-5 text-right">{t('Payload')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 font-mono">
                                {filteredLogs.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="p-8 text-center text-slate-400 font-sans">
                                            {t('No logs found matching filter.')}
                                        </td>
                                    </tr>
                                ) : (
                                    filteredLogs.map((log) => {
                                        const isExpanded = !!expandedRows[log.id];
                                        return (
                                            <React.Fragment key={log.id}>
                                                <tr className="hover:bg-slate-50/60 transition-colors">
                                                    <td className="p-3 pl-5 text-slate-400 font-bold">{log.row_index}</td>
                                                    <td className="p-3 font-semibold text-slate-800">{log.record_identifier || '—'}</td>
                                                    <td className="p-3 font-sans">
                                                        {log.status === 'success' && (
                                                            <Badge className="bg-emerald-100 text-emerald-800 border-emerald-200 text-[10px]">
                                                                ✓ {t('Success')}
                                                            </Badge>
                                                        )}
                                                        {log.status === 'warning' && (
                                                            <Badge className="bg-amber-100 text-amber-800 border-amber-200 text-[10px]">
                                                                ⚠ {t('Warning')}
                                                            </Badge>
                                                        )}
                                                        {log.status === 'skipped' && (
                                                            <Badge className="bg-slate-100 text-slate-600 border-slate-200 text-[10px]">
                                                                {t('Skipped')}
                                                            </Badge>
                                                        )}
                                                        {log.status === 'error' && (
                                                            <Badge className="bg-rose-100 text-rose-800 border-rose-200 text-[10px]">
                                                                ✕ {t('Error')}
                                                            </Badge>
                                                        )}
                                                        {log.status === 'rolled_back' && (
                                                            <Badge className="bg-slate-100 text-slate-500 border-slate-200 text-[10px]">
                                                                ↺ {t('Rolled Back')}
                                                            </Badge>
                                                        )}
                                                    </td>
                                                    <td className="p-3 font-sans text-slate-600">
                                                        {log.target_model ? `${log.target_model} #${log.target_id}` : '—'}
                                                    </td>
                                                    <td className="p-3 font-sans text-slate-600 max-w-xs truncate">{log.message || '—'}</td>
                                                    <td className="p-3 pr-5 text-right font-sans">
                                                        {log.raw_data && (
                                                            <button
                                                                type="button"
                                                                onClick={() => toggleRow(log.id)}
                                                                className="inline-flex items-center gap-1 text-[11px] text-emerald-600 hover:text-emerald-700 font-medium"
                                                            >
                                                                <span>{isExpanded ? t('Hide') : t('Inspect')}</span>
                                                                {isExpanded ? <ChevronUp className="w-3 h-3" /> : <ChevronDown className="w-3 h-3" />}
                                                            </button>
                                                        )}
                                                    </td>
                                                </tr>
                                                {isExpanded && log.raw_data && (
                                                    <tr className="bg-slate-900 text-emerald-400">
                                                        <td colSpan={6} className="p-4 pl-8">
                                                            <div className="text-[10px] text-slate-400 uppercase font-bold mb-1 font-sans">{t('Raw Payload Received:')}</div>
                                                            <pre className="text-xs font-mono overflow-x-auto whitespace-pre-wrap max-h-48 text-slate-200">
                                                                {JSON.stringify(log.raw_data, null, 2)}
                                                            </pre>
                                                        </td>
                                                    </tr>
                                                )}
                                            </React.Fragment>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                {/* Rollback Confirmation Modal */}
                <Dialog open={rollbackModalOpen} onOpenChange={setRollbackModalOpen}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2 text-rose-600 font-bold text-base">
                                <AlertTriangle className="w-5 h-5 text-rose-600" />
                                {t('Confirm 1-Click Rollback')}
                            </DialogTitle>
                            <DialogDescription className="text-xs text-slate-600 pt-2 space-y-2">
                                <p>
                                    {t('Are you sure you want to rollback batch')} <strong className="font-mono text-slate-900">{batch.batch_code}</strong>?
                                </p>
                                <div className="p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-xs">
                                    <strong>{t('Warning')}:</strong> {t('This will permanently delete all')} <strong>{batch.imported_count}</strong> {t('records created during this migration run, and mark the batch as rolled back.')}
                                </div>
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter className="flex items-center justify-end gap-2 pt-2 border-t">
                            <Button
                                variant="outline"
                                onClick={() => setRollbackModalOpen(false)}
                                className="text-xs h-9"
                            >
                                {t('Cancel')}
                            </Button>
                            <Button
                                onClick={handleConfirmRollback}
                                className="bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs h-9 px-4 gap-1.5"
                            >
                                <RotateCcw className="w-3.5 h-3.5" />
                                {t('Confirm & Rollback Records')}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AuthenticatedLayout>
    );
}
