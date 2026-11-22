import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    ArrowRightLeft, 
    Sparkles, 
    Database, 
    History, 
    CheckCircle2, 
    AlertTriangle, 
    FileSpreadsheet, 
    RotateCcw, 
    Plus, 
    Layers, 
    ShieldCheck, 
    ArrowRight,
    Users,
    Package,
    Landmark,
    Briefcase,
    ExternalLink
} from 'lucide-react';

interface MigrationIndexProps {
    batches: any[];
    connections: any[];
    stats: {
        totalBatches: number;
        completedBatches: number;
        totalRecordsImported: number;
        totalConnections: number;
        activeConnections: number;
    };
}

export default function MigrationIndex({ batches = [], connections = [], stats }: MigrationIndexProps) {
    const { t } = useTranslation();

    const handleRollback = (batchId: number, batchCode: string) => {
        if (confirm(t(`Are you sure you want to rollback batch ${batchCode}? All records created during this migration will be safely removed.`))) {
            router.post(route('erp-migrate.batches.rollback', batchId));
        }
    };

    const supportedErps = [
        {
            name: 'Odoo ERP',
            version: 'v13 - v18 (Community & Enterprise)',
            desc: 'Direct JSON-RPC / XML-RPC connection or standard res.partner / product.template CSV exports.',
            icon: '🟣',
            color: 'border-purple-200 bg-purple-50/50 text-purple-900',
        },
        {
            name: 'ERPNext / Frappe',
            version: 'v13, v14, v15 (Cloud & On-Prem)',
            desc: 'REST API integration via Frappe API Key & Secret or Frappe Data Import CSV/XLSX.',
            icon: '🔵',
            color: 'border-blue-200 bg-blue-50/50 text-blue-900',
        },
        {
            name: 'Universal Excel & CSV',
            version: 'XLSX, XLS, CSV, TSV',
            desc: 'Smart column detection and auto-mapping for any spreadsheet or QuickBooks/Zoho export.',
            icon: '🟢',
            color: 'border-emerald-200 bg-emerald-50/50 text-emerald-900',
        },
    ];

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('ERP Data Migration') }, { label: t('Studio Dashboard') }]}
            pageTitle={t('ERP Data Migration Studio')}
        >
            <Head title={t('ERP Data Migration Studio - Odoo & ERPNext Bridge')} />

            <div className="space-y-6 max-w-7xl mx-auto">
                {/* Header Banner */}
                <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-emerald-950 to-slate-900 text-white p-6 md:p-8 shadow-md border border-emerald-500/20">
                    <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <div className="space-y-2 max-w-2xl">
                            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 text-xs font-semibold uppercase tracking-wider">
                                <Sparkles className="w-3.5 h-3.5 text-emerald-400" />
                                {t('Automated ERP Data Bridge')}
                            </div>
                            <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight text-white flex items-center gap-2.5">
                                <ArrowRightLeft className="w-7 h-7 text-emerald-400" />
                                {t('ERP Data Migration Studio')}
                            </h1>
                            <p className="text-slate-300 text-xs md:text-sm leading-relaxed">
                                {t('Seamlessly migrate your Chart of Accounts, Customers, Vendors, Product Inventory, and Employees from Odoo, ERPNext, or custom spreadsheets into GadaaCloud with automated column mapping, dry-run validations, and 1-click rollback.')}
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2.5">
                            <Link
                                href={route('erp-migrate.wizard')}
                                className="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs shadow-sm transition-all"
                            >
                                <Plus className="w-4 h-4" />
                                {t('Start New Migration')}
                            </Link>
                            <Link
                                href={route('erp-migrate.connections')}
                                className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-slate-800/80 hover:bg-slate-800 border border-slate-700 text-slate-200 font-medium text-xs transition-all"
                            >
                                <Database className="w-4 h-4 text-emerald-400" />
                                {t('API Connections')}
                            </Link>
                        </div>
                    </div>
                </div>

                {/* KPI Stat Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <Card className="border border-slate-200/80 shadow-2xs">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <span className="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">
                                    {t('Total Records Migrated')}
                                </span>
                                <h3 className="text-xl font-extrabold text-slate-900 mt-0.5">
                                    {stats.totalRecordsImported.toLocaleString()}
                                </h3>
                                <span className="text-[10px] text-emerald-600 font-medium">✓ Capitalized & Live</span>
                            </div>
                            <div className="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <CheckCircle2 className="w-5 h-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border border-slate-200/80 shadow-2xs">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <span className="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">
                                    {t('Completed Batches')}
                                </span>
                                <h3 className="text-xl font-extrabold text-slate-900 mt-0.5">
                                    {stats.completedBatches} / {stats.totalBatches}
                                </h3>
                                <span className="text-[10px] text-slate-500 font-medium">{t('100% Rollback Protected')}</span>
                            </div>
                            <div className="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                <Layers className="w-5 h-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border border-slate-200/80 shadow-2xs">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <span className="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">
                                    {t('Active ERP Connections')}
                                </span>
                                <h3 className="text-xl font-extrabold text-slate-900 mt-0.5">
                                    {stats.activeConnections}
                                </h3>
                                <span className="text-[10px] text-purple-600 font-medium">{t('Odoo & ERPNext Gateway')}</span>
                            </div>
                            <div className="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                                <Database className="w-5 h-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border border-slate-200/80 shadow-2xs">
                        <CardContent className="p-4 flex items-center justify-between">
                            <div>
                                <span className="text-[11px] font-semibold text-slate-500 uppercase tracking-wider block">
                                    {t('Safety & Compliance')}
                                </span>
                                <h3 className="text-xl font-extrabold text-slate-900 mt-0.5">
                                    {t('10-Digit TIN')}
                                </h3>
                                <span className="text-[10px] text-emerald-600 font-medium">Ethiopia MoR Compliant</span>
                            </div>
                            <div className="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                                <ShieldCheck className="w-5 h-5" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Supported ERP Gateways */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {supportedErps.map((erp, idx) => (
                        <div key={idx} className={`p-4 rounded-xl border ${erp.color} space-y-1.5 transition-all`}>
                            <div className="flex items-center justify-between">
                                <h4 className="text-xs font-bold flex items-center gap-1.5">
                                    <span>{erp.icon}</span>
                                    {erp.name}
                                </h4>
                                <Badge variant="outline" className="text-[10px] bg-white/70">
                                    {erp.version}
                                </Badge>
                            </div>
                            <p className="text-[11px] opacity-80 leading-relaxed">{erp.desc}</p>
                        </div>
                    ))}
                </div>

                {/* Recent Migration Batches Table */}
                <Card className="border border-slate-200/80 shadow-xs">
                    <CardHeader className="p-4 sm:p-5 border-b flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <CardTitle className="text-base font-bold text-slate-900 flex items-center gap-2">
                                <History className="w-4 h-4 text-emerald-600" />
                                {t('Recent Migration Runs')}
                            </CardTitle>
                            <CardDescription className="text-xs">
                                {t('Audit trail of imported batches, record counts, and transactional rollback options.')}
                            </CardDescription>
                        </div>
                        <Link
                            href={route('erp-migrate.history')}
                            className="inline-flex items-center gap-1 text-xs text-emerald-600 hover:text-emerald-700 font-semibold"
                        >
                            <span>{t('View All History')}</span>
                            <ArrowRight className="w-3.5 h-3.5" />
                        </Link>
                    </CardHeader>
                    <CardContent className="p-0 overflow-x-auto">
                        <table className="w-full text-xs text-left">
                            <thead className="bg-slate-50/80 border-b border-slate-200 text-slate-500 uppercase font-semibold text-[11px]">
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
                                {batches.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="p-8 text-center text-slate-400 font-sans">
                                            <div className="max-w-sm mx-auto space-y-2">
                                                <Layers className="w-8 h-8 text-slate-300 mx-auto" />
                                                <p className="text-xs font-semibold text-slate-600">{t('No migration batches executed yet.')}</p>
                                                <p className="text-[11px] text-slate-400">{t('Click "Start New Migration" to import your data from Odoo, ERPNext, or Excel.')}</p>
                                                <Link
                                                    href={route('erp-migrate.wizard')}
                                                    className="inline-flex items-center gap-1.5 text-xs text-emerald-600 font-bold hover:underline pt-1"
                                                >
                                                    <Plus className="w-3.5 h-3.5" />
                                                    {t('Start First Migration')}
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    batches.map((b) => (
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
                                                <span className="inline-flex items-center gap-1">
                                                    {b.source_type.includes('odoo') && '🟣 Odoo'}
                                                    {b.source_type.includes('erpnext') && '🔵 ERPNext'}
                                                    {b.source_type.includes('universal') && '🟢 Excel/CSV'}
                                                </span>
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
