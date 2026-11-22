import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    History, 
    ArrowLeft, 
    Plus, 
    RotateCcw, 
    Layers, 
    Search,
    ExternalLink
} from 'lucide-react';

interface HistoryProps {
    batches: {
        data: any[];
        links: any[];
        current_page: number;
        last_page: number;
        total: number;
    };
}

export default function MigrationHistory({ batches }: HistoryProps) {
    const { t } = useTranslation();
    const [search, setSearch] = useState('');

    const handleRollback = (id: number, code: string) => {
        if (confirm(t(`Rollback batch ${code}? All created records will be permanently deleted.`))) {
            router.post(route('erp-migrate.batches.rollback', id));
        }
    };

    const filtered = batches.data.filter((b) => {
        const q = search.toLowerCase();
        return !q || b.batch_code.toLowerCase().includes(q) || b.entity_type.toLowerCase().includes(q) || (b.original_filename && b.original_filename.toLowerCase().includes(q));
    });

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('ERP Data Migration'), url: route('erp-migrate.index') },
                { label: t('Audit Logs & History') }
            ]}
            pageTitle={t('Migration Audit History & Rollback')}
        >
            <Head title={t('Migration Audit History & Rollback')} />

            <div className="space-y-6 max-w-6xl mx-auto">
                <div className="p-5 rounded-2xl bg-white border border-slate-200/80 shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2">
                            <Link
                                href={route('erp-migrate.index')}
                                className="w-8 h-8 rounded-lg border flex items-center justify-center text-slate-500 hover:bg-slate-50"
                            >
                                <ArrowLeft className="w-4 h-4" />
                            </Link>
                            <h2 className="text-lg font-bold text-slate-900 flex items-center gap-2">
                                <History className="w-5 h-5 text-emerald-600" />
                                {t('Migration History & Audit Trail')}
                            </h2>
                        </div>
                        <p className="text-xs text-slate-500">
                            {t('Complete log of all data migrations from Odoo, ERPNext, and spreadsheets.')}
                        </p>
                    </div>

                    <Link
                        href={route('erp-migrate.wizard')}
                        className="inline-flex items-center gap-1.5 h-9 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-xs"
                    >
                        <Plus className="w-4 h-4" />
                        <span>{t('New Migration')}</span>
                    </Link>
                </div>

                <Card className="border border-slate-200/80 shadow-xs">
                    <CardHeader className="p-4 border-b bg-slate-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <CardTitle className="text-sm font-bold text-slate-900">
                            {t('Executed Batches')} ({batches.total})
                        </CardTitle>
                        <div className="w-64">
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder={t('Search batch code or entity...')}
                                className="h-8 text-xs border rounded-lg px-2.5 w-full bg-white"
                            />
                        </div>
                    </CardHeader>
                    <CardContent className="p-0 overflow-x-auto">
                        <table className="w-full text-xs text-left">
                            <thead className="bg-slate-50 border-b text-slate-500 uppercase font-semibold text-[11px]">
                                <tr>
                                    <th className="p-3.5 pl-5">{t('Batch Code')}</th>
                                    <th className="p-3.5">{t('Entity')}</th>
                                    <th className="p-3.5">{t('Source')}</th>
                                    <th className="p-3.5 text-center">{t('Imported')}</th>
                                    <th className="p-3.5 text-center">{t('Status')}</th>
                                    <th className="p-3.5">{t('Date')}</th>
                                    <th className="p-3.5 pr-5 text-right">{t('Actions')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 font-mono">
                                {filtered.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="p-8 text-center text-slate-400 font-sans">
                                            {t('No migration batches found.')}
                                        </td>
                                    </tr>
                                ) : (
                                    filtered.map((b) => (
                                        <tr key={b.id} className="hover:bg-slate-50/60 transition-colors">
                                            <td className="p-3.5 pl-5 font-bold text-slate-900">
                                                <Link
                                                    href={route('erp-migrate.batches.show', b.id)}
                                                    className="hover:text-emerald-600 hover:underline"
                                                >
                                                    {b.batch_code}
                                                </Link>
                                            </td>
                                            <td className="p-3.5 font-sans font-semibold capitalize text-slate-700">
                                                {b.entity_type.replace('_', ' ')}
                                            </td>
                                            <td className="p-3.5 font-sans text-slate-600">
                                                {b.original_filename || b.source_type}
                                            </td>
                                            <td className="p-3.5 text-center font-bold text-emerald-700">
                                                {b.imported_count} / {b.total_records}
                                            </td>
                                            <td className="p-3.5 text-center font-sans">
                                                {b.status === 'completed' && (
                                                    <Badge className="bg-emerald-100 text-emerald-800 border-emerald-200">
                                                        ✓ {t('Completed')}
                                                    </Badge>
                                                )}
                                                {b.status === 'rolled_back' && (
                                                    <Badge className="bg-slate-100 text-slate-600 border-slate-200">
                                                        ↺ {t('Rolled Back')}
                                                    </Badge>
                                                )}
                                                {b.status === 'failed' && (
                                                    <Badge className="bg-rose-100 text-rose-800 border-rose-200">
                                                        ✕ {t('Failed')}
                                                    </Badge>
                                                )}
                                                {b.status === 'draft' && (
                                                    <Badge className="bg-amber-100 text-amber-800 border-amber-200">
                                                        {t('Draft')}
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="p-3.5 text-slate-500 font-sans text-[11px]">
                                                {new Date(b.created_at).toLocaleDateString()}
                                            </td>
                                            <td className="p-3.5 pr-5 text-right font-sans">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <Link
                                                        href={route('erp-migrate.batches.show', b.id)}
                                                        className="inline-flex items-center gap-1 h-7 px-2 rounded-md border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-[11px] font-medium"
                                                    >
                                                        {t('Audit Logs')}
                                                    </Link>
                                                    {b.status === 'completed' && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => handleRollback(b.id, b.batch_code)}
                                                            className="h-7 px-2 text-[11px] text-rose-600 border-rose-200 hover:bg-rose-50 gap-1"
                                                        >
                                                            <RotateCcw className="w-3 h-3" />
                                                            {t('Rollback')}
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
