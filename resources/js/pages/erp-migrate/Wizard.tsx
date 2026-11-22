import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { toast } from 'sonner';
import { 
    ArrowRightLeft, 
    Sparkles, 
    Database, 
    FileSpreadsheet, 
    UploadCloud, 
    CheckCircle2, 
    AlertTriangle, 
    Check, 
    Layers, 
    RotateCcw, 
    RefreshCw, 
    ArrowRight, 
    ArrowLeft,
    Users,
    Package,
    Landmark,
    Briefcase,
    ShieldAlert,
    ExternalLink,
    Plus
} from 'lucide-react';
import axios from 'axios';

interface WizardProps {
    connections: any[];
    entityFields: Record<string, Record<string, { label: string; required: boolean; synonyms: string[] }>>;
}

export default function MigrationWizard({ connections = [], entityFields }: WizardProps) {
    const { t } = useTranslation();

    // Wizard Step: 1 = Source & Entity, 2 = Upload/Connect, 3 = Map Fields, 4 = Dry-Run, 5 = Complete
    const [step, setStep] = useState(1);

    // Step 1 State
    const [sourceType, setSourceType] = useState<'odoo' | 'erpnext' | 'excel'>('odoo');
    const [entityType, setEntityType] = useState<'customers' | 'vendors' | 'products' | 'chart_of_accounts' | 'employees'>('customers');

    // Step 2 State
    const [selectedConnectionId, setSelectedConnectionId] = useState<string>(connections[0]?.id ? String(connections[0].id) : '');
    const [file, setFile] = useState<File | null>(null);
    const [isLoadingStep2, setIsLoadingStep2] = useState(false);

    // Step 3 State (Mapping & Preview)
    const [batchId, setBatchId] = useState<number | null>(null);
    const [batchCode, setBatchCode] = useState<string>('');
    const [sourceHeaders, setSourceHeaders] = useState<string[]>([]);
    const [sampleRows, setSampleRows] = useState<any[]>([]);
    const [totalRows, setTotalRows] = useState<number>(0);
    const [fieldMappings, setFieldMappings] = useState<Record<string, string>>({});

    // Step 4 State (Dry-Run)
    const [isDryRunning, setIsDryRunning] = useState(false);
    const [dryRunSummary, setDryRunSummary] = useState<any>(null);
    const [skipErrors, setSkipErrors] = useState(true);

    // Step 5 State (Execution)
    const [isExecuting, setIsExecuting] = useState(false);
    const [executionResult, setExecutionResult] = useState<any>(null);

    // STEP 2: Handle Upload or Remote Fetch
    const handleProceedToMapping = async () => {
        setIsLoadingStep2(true);
        try {
            if (sourceType === 'excel') {
                if (!file) {
                    toast.error(t('Please select a spreadsheet file (.xlsx, .csv).'));
                    setIsLoadingStep2(false);
                    return;
                }
                const formData = new FormData();
                formData.append('file', file);
                formData.append('entity_type', entityType);

                const res = await axios.post(route('erp-migrate.upload'), formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });

                if (res.data.success) {
                    setBatchId(res.data.batch_id);
                    setBatchCode(res.data.batch_code);
                    setSourceHeaders(res.data.headers);
                    setSampleRows(res.data.sample_rows);
                    setTotalRows(res.data.total_rows);
                    setFieldMappings(res.data.suggested_mappings);
                    setStep(3);
                    toast.success(t('File parsed and columns auto-mapped!'));
                }
            } else {
                if (!selectedConnectionId) {
                    toast.error(t('Please select an active ERP connection.'));
                    setIsLoadingStep2(false);
                    return;
                }
                const res = await axios.post(route('erp-migrate.fetch-remote'), {
                    connection_id: selectedConnectionId,
                    entity_type: entityType,
                });

                if (res.data.success) {
                    setBatchId(res.data.batch_id);
                    setBatchCode(res.data.batch_code);
                    setSourceHeaders(res.data.headers);
                    setSampleRows(res.data.sample_rows);
                    setTotalRows(res.data.total_rows);
                    setFieldMappings(res.data.suggested_mappings);
                    setStep(3);
                    toast.success(t(`Fetched ${res.data.total_rows} records from remote ERP!`));
                }
            }
        } catch (err: any) {
            toast.error(err.response?.data?.message || t('Failed to process source data.'));
        } finally {
            setIsLoadingStep2(false);
        }
    };

    // STEP 3: Handle Dry Run
    const handleRunDryRun = async () => {
        if (!batchId) return;
        setIsDryRunning(true);
        try {
            const res = await axios.post(route('erp-migrate.dry-run'), {
                batch_id: batchId,
                field_mappings: fieldMappings,
            });

            if (res.data.success) {
                setDryRunSummary(res.data.summary);
                setStep(4);
                toast.success(t('Validation dry-run completed!'));
            }
        } catch (err: any) {
            toast.error(err.response?.data?.message || t('Dry run validation failed.'));
        } finally {
            setIsDryRunning(false);
        }
    };

    // STEP 4: Handle Execute
    const handleExecuteMigration = async () => {
        if (!batchId) return;
        setIsExecuting(true);
        try {
            const res = await axios.post(route('erp-migrate.execute'), {
                batch_id: batchId,
                skip_errors: skipErrors,
            });

            if (res.data.success) {
                setExecutionResult(res.data);
                setStep(5);
                toast.success(t('Migration executed successfully!'));
            }
        } catch (err: any) {
            toast.error(err.response?.data?.message || t('Migration execution failed.'));
        } finally {
            setIsExecuting(false);
        }
    };

    const targetFields = entityFields[entityType] || {};

    const steps = [
        { num: 1, label: t('Source & Entity') },
        { num: 2, label: t('Connect / Upload') },
        { num: 3, label: t('Map Fields') },
        { num: 4, label: t('Validation Dry-Run') },
        { num: 5, label: t('Migrate & Complete') },
    ];

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('ERP Data Migration'), url: route('erp-migrate.index') },
                { label: t('Migration Wizard') }
            ]}
            pageTitle={t('ERP Migration Wizard')}
        >
            <Head title={t('ERP Migration Wizard - Step-by-Step Data Bridge')} />

            <div className="space-y-6 max-w-5xl mx-auto">
                {/* Stepper Header */}
                <div className="p-4 rounded-xl bg-white border border-slate-200/80 shadow-2xs">
                    <div className="flex items-center justify-between">
                        {steps.map((s, idx) => (
                            <React.Fragment key={s.num}>
                                <div className="flex items-center gap-2">
                                    <div className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all ${
                                        step === s.num
                                            ? 'bg-emerald-600 text-white ring-4 ring-emerald-100 shadow-xs'
                                            : step > s.num
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-slate-100 text-slate-400'
                                    }`}>
                                        {step > s.num ? '✓' : s.num}
                                    </div>
                                    <span className={`text-xs font-semibold hidden sm:inline ${
                                        step === s.num ? 'text-slate-900' : 'text-slate-400'
                                    }`}>
                                        {s.label}
                                    </span>
                                </div>
                                {idx < steps.length - 1 && (
                                    <div className={`flex-1 h-0.5 mx-2 ${
                                        step > s.num ? 'bg-emerald-500' : 'bg-slate-200'
                                    }`} />
                                )}
                            </React.Fragment>
                        ))}
                    </div>
                </div>

                {/* STAGE 1: Source & Entity Selection */}
                {step === 1 && (
                    <Card className="border shadow-xs">
                        <CardHeader className="p-5 border-b bg-slate-50/50">
                            <CardTitle className="text-base font-bold text-slate-900 flex items-center gap-2">
                                <span className="w-6 h-6 rounded-full bg-emerald-100 text-emerald-800 text-xs flex items-center justify-center font-bold">1</span>
                                {t('Select ERP Source & Target Entity')}
                            </CardTitle>
                            <CardDescription className="text-xs">
                                {t('Choose which system you are migrating from and what data you want to import into GadaaCloud.')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-6 space-y-6">
                            {/* Source ERP Selection */}
                            <div className="space-y-2">
                                <Label className="text-xs font-semibold text-slate-700 uppercase tracking-wider block">
                                    {t('Source System / Format')}
                                </Label>
                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div
                                        onClick={() => setSourceType('odoo')}
                                        className={`p-4 rounded-xl border cursor-pointer transition-all ${
                                            sourceType === 'odoo'
                                                ? 'border-purple-600 bg-purple-50/50 ring-2 ring-purple-500/20 shadow-xs'
                                                : 'border-slate-200 hover:border-slate-300'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between mb-2">
                                            <span className="text-lg">🟣</span>
                                            {sourceType === 'odoo' && <CheckCircle2 className="w-4 h-4 text-purple-600" />}
                                        </div>
                                        <h4 className="text-sm font-bold text-slate-900">Odoo ERP</h4>
                                        <p className="text-xs text-slate-500 mt-1">Community & Enterprise v13-v18 via JSON-RPC API or exports.</p>
                                    </div>

                                    <div
                                        onClick={() => setSourceType('erpnext')}
                                        className={`p-4 rounded-xl border cursor-pointer transition-all ${
                                            sourceType === 'erpnext'
                                                ? 'border-blue-600 bg-blue-50/50 ring-2 ring-blue-500/20 shadow-xs'
                                                : 'border-slate-200 hover:border-slate-300'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between mb-2">
                                            <span className="text-lg">🔵</span>
                                            {sourceType === 'erpnext' && <CheckCircle2 className="w-4 h-4 text-blue-600" />}
                                        </div>
                                        <h4 className="text-sm font-bold text-slate-900">ERPNext / Frappe</h4>
                                        <p className="text-xs text-slate-500 mt-1">Frappe REST API tokens or standard CSV/Excel packages.</p>
                                    </div>

                                    <div
                                        onClick={() => setSourceType('excel')}
                                        className={`p-4 rounded-xl border cursor-pointer transition-all ${
                                            sourceType === 'excel'
                                                ? 'border-emerald-600 bg-emerald-50/50 ring-2 ring-emerald-500/20 shadow-xs'
                                                : 'border-slate-200 hover:border-slate-300'
                                        }`}
                                    >
                                        <div className="flex items-center justify-between mb-2">
                                            <span className="text-lg">🟢</span>
                                            {sourceType === 'excel' && <CheckCircle2 className="w-4 h-4 text-emerald-600" />}
                                        </div>
                                        <h4 className="text-sm font-bold text-slate-900">Universal Excel / CSV</h4>
                                        <p className="text-xs text-slate-500 mt-1">Upload any spreadsheet or QuickBooks/Zoho export file.</p>
                                    </div>
                                </div>
                            </div>

                            {/* Target Entity Selection */}
                            <div className="space-y-2">
                                <Label className="text-xs font-semibold text-slate-700 uppercase tracking-wider block">
                                    {t('Target GadaaCloud Entity')}
                                </Label>
                                <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
                                    {[
                                        { id: 'customers', label: t('Customers'), icon: Users, desc: t('Contacts & TIN') },
                                        { id: 'vendors', label: t('Vendors'), icon: Users, desc: t('Suppliers & Terms') },
                                        { id: 'products', label: t('Products & Stock'), icon: Package, desc: t('SKU, Prices, Units') },
                                        { id: 'chart_of_accounts', label: t('Chart of Accounts'), icon: Landmark, desc: t('Codes & Normal Balances') },
                                        { id: 'employees', label: t('Employees'), icon: Briefcase, desc: t('Staff & Departments') },
                                    ].map((ent) => {
                                        const isSelected = entityType === ent.id;
                                        return (
                                            <div
                                                key={ent.id}
                                                onClick={() => setEntityType(ent.id as any)}
                                                className={`p-3.5 rounded-xl border text-center cursor-pointer transition-all ${
                                                    isSelected
                                                        ? 'border-emerald-600 bg-emerald-50/50 ring-2 ring-emerald-500/20 shadow-2xs'
                                                        : 'border-slate-200 hover:border-slate-300'
                                                }`}
                                            >
                                                <ent.icon className={`w-5 h-5 mx-auto mb-1.5 ${isSelected ? 'text-emerald-600' : 'text-slate-400'}`} />
                                                <h5 className="text-xs font-bold text-slate-900">{ent.label}</h5>
                                                <span className="text-[10px] text-slate-400 block mt-0.5">{ent.desc}</span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="flex justify-end pt-4 border-t">
                                <Button
                                    onClick={() => setStep(2)}
                                    className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs h-9 px-5 gap-1.5 shadow-xs"
                                >
                                    <span>{t('Continue to Connection / Upload')}</span>
                                    <ArrowRight className="w-3.5 h-3.5" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* STAGE 2: Connect or Upload */}
                {step === 2 && (
                    <Card className="border shadow-xs">
                        <CardHeader className="p-5 border-b bg-slate-50/50">
                            <CardTitle className="text-base font-bold text-slate-900 flex items-center gap-2">
                                <span className="w-6 h-6 rounded-full bg-emerald-100 text-emerald-800 text-xs flex items-center justify-center font-bold">2</span>
                                {sourceType === 'excel' ? t('Upload Spreadsheet File') : t('Select or Configure ERP API Gateway')}
                            </CardTitle>
                            <CardDescription className="text-xs">
                                {sourceType === 'excel'
                                    ? t('Upload your .xlsx, .xls, or .csv file. The engine will inspect columns and auto-match fields.')
                                    : t('Connect directly to your Odoo or ERPNext instance to fetch records in real time.')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-6 space-y-6">
                            {sourceType === 'excel' ? (
                                <div className="space-y-4">
                                    <div className="border-2 border-dashed border-emerald-300 rounded-2xl p-8 text-center bg-emerald-50/20 hover:bg-emerald-50/40 transition-colors">
                                        <UploadCloud className="w-10 h-10 text-emerald-600 mx-auto mb-3" />
                                        <h4 className="text-sm font-bold text-slate-900">{t('Drag and drop your spreadsheet here')}</h4>
                                        <p className="text-xs text-slate-500 mt-1 mb-4">{t('Supports Excel (.xlsx, .xls) and CSV (.csv, .tsv) up to 20MB.')}</p>
                                        <input
                                            type="file"
                                            accept=".xlsx,.xls,.csv,.tsv"
                                            onChange={(e) => setFile(e.target.files?.[0] || null)}
                                            className="hidden"
                                            id="file-upload"
                                        />
                                        <label
                                            htmlFor="file-upload"
                                            className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs cursor-pointer shadow-xs"
                                        >
                                            <FileSpreadsheet className="w-4 h-4" />
                                            {file ? file.name : t('Browse Local File')}
                                        </label>
                                    </div>
                                    {file && (
                                        <div className="p-3 rounded-lg bg-slate-50 border flex items-center justify-between text-xs">
                                            <span className="font-semibold text-slate-800">{file.name}</span>
                                            <Badge variant="outline" className="text-slate-500 font-mono">
                                                {(file.size / 1024).toFixed(1)} KB
                                            </Badge>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {connections.length === 0 ? (
                                        <div className="p-6 text-center border rounded-xl bg-slate-50 space-y-3">
                                            <Database className="w-8 h-8 text-slate-400 mx-auto" />
                                            <h4 className="text-xs font-bold text-slate-800">{t('No saved ERP connections found')}</h4>
                                            <p className="text-xs text-slate-500 max-w-sm mx-auto">
                                                {t('You need to configure an API connection with your Odoo or ERPNext credentials before fetching live data.')}
                                            </p>
                                            <Link
                                                href={route('erp-migrate.connections')}
                                                className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold"
                                            >
                                                <Plus className="w-3.5 h-3.5" />
                                                {t('Add New Connection')}
                                            </Link>
                                        </div>
                                    ) : (
                                        <div className="space-y-2">
                                            <Label className="text-xs font-semibold text-slate-700 block">
                                                {t('Choose Saved Connection')}
                                            </Label>
                                            <select
                                                value={selectedConnectionId}
                                                onChange={(e) => setSelectedConnectionId(e.target.value)}
                                                className="w-full h-10 border border-slate-200 rounded-xl text-xs px-3 bg-white"
                                            >
                                                {connections
                                                    .filter((c) => c.source_type === sourceType)
                                                    .map((c) => (
                                                        <option key={c.id} value={c.id}>
                                                            {c.name} ({c.host}) — {c.connection_status.toUpperCase()}
                                                        </option>
                                                    ))}
                                            </select>
                                            <div className="pt-2">
                                                <Link
                                                    href={route('erp-migrate.connections')}
                                                    className="inline-flex items-center gap-1 text-xs text-emerald-600 hover:underline"
                                                >
                                                    <Plus className="w-3 h-3" />
                                                    {t('Manage or add another ERP connection')}
                                                </Link>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}

                            <div className="flex items-center justify-between pt-4 border-t">
                                <Button
                                    variant="outline"
                                    onClick={() => setStep(1)}
                                    className="text-xs h-9 gap-1"
                                >
                                    <ArrowLeft className="w-3.5 h-3.5" />
                                    {t('Back')}
                                </Button>
                                <Button
                                    onClick={handleProceedToMapping}
                                    disabled={isLoadingStep2 || (sourceType === 'excel' && !file)}
                                    className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs h-9 px-5 gap-1.5 shadow-xs"
                                >
                                    {isLoadingStep2 ? (
                                        <>
                                            <RefreshCw className="w-3.5 h-3.5 animate-spin" />
                                            {t('Inspecting Data & Auto-Mapping...')}
                                        </>
                                    ) : (
                                        <>
                                            <span>{t('Inspect Columns & Map Fields')}</span>
                                            <ArrowRight className="w-3.5 h-3.5" />
                                        </>
                                    )}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* STAGE 3: Visual Column Mapper */}
                {step === 3 && (
                    <Card className="border shadow-xs">
                        <CardHeader className="p-5 border-b bg-slate-50/50">
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div>
                                    <CardTitle className="text-base font-bold text-slate-900 flex items-center gap-2">
                                        <span className="w-6 h-6 rounded-full bg-emerald-100 text-emerald-800 text-xs flex items-center justify-center font-bold">3</span>
                                        {t('Visual Field Mapping & Column Matcher')}
                                    </CardTitle>
                                    <CardDescription className="text-xs">
                                        {t('Review and customize how your source fields map to GadaaCloud fields.')}
                                    </CardDescription>
                                </div>
                                <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300 font-mono text-xs">
                                    {totalRows} {t('Rows Detected')}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="p-6 space-y-6">
                            {/* Mapping Grid */}
                            <div className="space-y-3">
                                <h4 className="text-xs font-bold text-slate-700 uppercase tracking-wider">
                                    {t('Column Mapping Matrix')}
                                </h4>
                                <div className="border rounded-xl divide-y bg-white">
                                    {Object.entries(targetFields).map(([fieldKey, meta]) => {
                                        const currentVal = fieldMappings[fieldKey] || '';
                                        return (
                                            <div key={fieldKey} className="p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/60 transition-colors">
                                                <div className="w-full sm:w-1/2">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-xs font-bold text-slate-800">{meta.label}</span>
                                                        {meta.required ? (
                                                            <Badge className="bg-rose-100 text-rose-800 border-rose-200 text-[9px]">{t('Required')}</Badge>
                                                        ) : (
                                                            <Badge variant="outline" className="text-slate-400 text-[9px]">{t('Optional')}</Badge>
                                                        )}
                                                    </div>
                                                    <span className="text-[11px] font-mono text-slate-400 block mt-0.5">{fieldKey}</span>
                                                </div>

                                                <div className="w-full sm:w-1/2 flex items-center gap-2">
                                                    <ArrowRightLeft className="w-3.5 h-3.5 text-slate-400 shrink-0 hidden sm:block" />
                                                    <select
                                                        value={currentVal}
                                                        onChange={(e) => setFieldMappings({ ...fieldMappings, [fieldKey]: e.target.value })}
                                                        className={`w-full h-9 border rounded-lg text-xs px-2.5 font-medium ${
                                                            currentVal ? 'border-emerald-400 bg-emerald-50/30 text-emerald-900' : 'border-slate-200 text-slate-400'
                                                        }`}
                                                    >
                                                        <option value="">{t('— Skip this field —')}</option>
                                                        {sourceHeaders.map((hdr) => (
                                                            <option key={hdr} value={hdr}>
                                                                {hdr}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            {/* Sample Rows Preview */}
                            {sampleRows.length > 0 && (
                                <div className="space-y-2">
                                    <h4 className="text-xs font-bold text-slate-700 uppercase tracking-wider">
                                        {t('Source Data Preview (First 5 Rows)')}
                                    </h4>
                                    <div className="border rounded-xl overflow-x-auto max-h-48 bg-slate-50/50">
                                        <table className="w-full text-xs text-left">
                                            <thead className="bg-slate-100 border-b text-slate-600 font-semibold text-[11px]">
                                                <tr>
                                                    {sourceHeaders.slice(0, 8).map((h) => (
                                                        <th key={h} className="p-2.5 whitespace-nowrap">{h}</th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-200/60 font-mono text-[11px]">
                                                {sampleRows.map((r, i) => (
                                                    <tr key={i} className="hover:bg-white">
                                                        {sourceHeaders.slice(0, 8).map((h) => (
                                                            <td key={h} className="p-2 whitespace-nowrap text-slate-700">{String(r[h] ?? '')}</td>
                                                        ))}
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}

                            <div className="flex items-center justify-between pt-4 border-t">
                                <Button
                                    variant="outline"
                                    onClick={() => setStep(2)}
                                    className="text-xs h-9 gap-1"
                                >
                                    <ArrowLeft className="w-3.5 h-3.5" />
                                    {t('Back')}
                                </Button>
                                <Button
                                    onClick={handleRunDryRun}
                                    disabled={isDryRunning}
                                    className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs h-9 px-5 gap-1.5 shadow-xs"
                                >
                                    {isDryRunning ? (
                                        <>
                                            <RefreshCw className="w-3.5 h-3.5 animate-spin" />
                                            {t('Validating Records...')}
                                        </>
                                    ) : (
                                        <>
                                            <span>{t('Run Dry-Run Verification')}</span>
                                            <ArrowRight className="w-3.5 h-3.5" />
                                        </>
                                    )}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* STAGE 4: Validation & Dry-Run Matrix */}
                {step === 4 && dryRunSummary && (
                    <Card className="border shadow-xs">
                        <CardHeader className="p-5 border-b bg-slate-50/50">
                            <CardTitle className="text-base font-bold text-slate-900 flex items-center gap-2">
                                <span className="w-6 h-6 rounded-full bg-emerald-100 text-emerald-800 text-xs flex items-center justify-center font-bold">4</span>
                                {t('Dry-Run Verification Matrix')}
                            </CardTitle>
                            <CardDescription className="text-xs">
                                {t('Review statutory checks, Ethiopian TIN validation, and duplicate conflict warnings before executing.')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-6 space-y-6">
                            {/* KPI Metrics */}
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div className="p-3.5 rounded-xl border bg-slate-50 text-slate-900">
                                    <span className="text-[11px] text-slate-500 block uppercase font-semibold">{t('Total Scanned')}</span>
                                    <span className="text-xl font-extrabold">{dryRunSummary.total_rows}</span>
                                </div>
                                <div className="p-3.5 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-900">
                                    <span className="text-[11px] text-emerald-700 block uppercase font-semibold">{t('Valid Records')}</span>
                                    <span className="text-xl font-extrabold">{dryRunSummary.valid_rows}</span>
                                </div>
                                <div className="p-3.5 rounded-xl border border-amber-200 bg-amber-50 text-amber-900">
                                    <span className="text-[11px] text-amber-700 block uppercase font-semibold">{t('Warnings')}</span>
                                    <span className="text-xl font-extrabold">{dryRunSummary.warning_rows}</span>
                                </div>
                                <div className="p-3.5 rounded-xl border border-rose-200 bg-rose-50 text-rose-900">
                                    <span className="text-[11px] text-rose-700 block uppercase font-semibold">{t('Blocker Errors')}</span>
                                    <span className="text-xl font-extrabold">{dryRunSummary.error_rows}</span>
                                </div>
                            </div>

                            {/* Warnings / Error List */}
                            {dryRunSummary.issues && dryRunSummary.issues.length > 0 && (
                                <div className="space-y-2">
                                    <h4 className="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                        <AlertTriangle className="w-3.5 h-3.5 text-amber-500" />
                                        {t('Validation Notice Log')}
                                    </h4>
                                    <div className="border rounded-xl divide-y max-h-56 overflow-y-auto bg-slate-50/40">
                                        {dryRunSummary.issues.map((iss: any, i: number) => (
                                            <div key={i} className="p-2.5 flex items-start gap-2.5 text-xs">
                                                <Badge className={`text-[10px] shrink-0 ${iss.type === 'error' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800'}`}>
                                                    Row {iss.row}
                                                </Badge>
                                                <div className="flex-1">
                                                    <span className="font-semibold text-slate-800">{iss.identifier}: </span>
                                                    <span className="text-slate-600">{iss.message}</span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Skip Errors Toggle */}
                            <div className="flex items-center justify-between p-4 rounded-xl bg-slate-50 border">
                                <div>
                                    <Label className="text-xs font-bold text-slate-900 cursor-pointer" htmlFor="skip-errors-toggle">
                                        {t('Skip faulty rows and import all valid records')}
                                    </Label>
                                    <p className="text-[11px] text-slate-500">{t('Ensures that your migration succeeds without failing the entire batch over a few bad rows.')}</p>
                                </div>
                                <Switch
                                    id="skip-errors-toggle"
                                    checked={skipErrors}
                                    onCheckedChange={setSkipErrors}
                                />
                            </div>

                            <div className="flex items-center justify-between pt-4 border-t">
                                <Button
                                    variant="outline"
                                    onClick={() => setStep(3)}
                                    className="text-xs h-9 gap-1"
                                >
                                    <ArrowLeft className="w-3.5 h-3.5" />
                                    {t('Adjust Mappings')}
                                </Button>
                                <Button
                                    onClick={handleExecuteMigration}
                                    disabled={isExecuting || (!skipErrors && dryRunSummary.error_rows > 0)}
                                    className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs h-9 px-6 gap-2 shadow-xs"
                                >
                                    {isExecuting ? (
                                        <>
                                            <RefreshCw className="w-3.5 h-3.5 animate-spin" />
                                            {t('Writing Records to GadaaCloud...')}
                                        </>
                                    ) : (
                                        <>
                                            <Check className="w-3.5 h-3.5" />
                                            {t('Execute Migration into GadaaCloud')}
                                        </>
                                    )}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* STAGE 5: Complete & Audit Link */}
                {step === 5 && executionResult && (
                    <Card className="border shadow-sm border-emerald-200 bg-gradient-to-b from-emerald-50/30 to-white">
                        <CardContent className="p-8 text-center space-y-5">
                            <div className="w-14 h-14 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto shadow-xs">
                                <CheckCircle2 className="w-8 h-8" />
                            </div>
                            <div className="space-y-1">
                                <h3 className="text-xl font-extrabold text-slate-900">
                                    {t('Data Migration Completed Successfully!')}
                                </h3>
                                <p className="text-xs text-slate-500 max-w-md mx-auto">
                                    {executionResult.message}
                                </p>
                            </div>

                            {/* Summary Box */}
                            <div className="grid grid-cols-3 gap-3 max-w-lg mx-auto text-center font-mono">
                                <div className="p-3 rounded-xl bg-emerald-50 border border-emerald-200">
                                    <span className="text-[10px] text-emerald-700 uppercase font-bold block">{t('Imported')}</span>
                                    <span className="text-xl font-extrabold text-emerald-800">{executionResult.imported_count}</span>
                                </div>
                                <div className="p-3 rounded-xl bg-slate-50 border border-slate-200">
                                    <span className="text-[10px] text-slate-500 uppercase font-bold block">{t('Skipped')}</span>
                                    <span className="text-xl font-extrabold text-slate-700">{executionResult.skipped_count}</span>
                                </div>
                                <div className="p-3 rounded-xl bg-blue-50 border border-blue-200">
                                    <span className="text-[10px] text-blue-700 uppercase font-bold block">{t('Batch Code')}</span>
                                    <span className="text-sm font-extrabold text-blue-800 truncate block mt-1">{executionResult.batch_code}</span>
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center justify-center gap-3 pt-4">
                                <Link
                                    href={route('erp-migrate.batches.show', executionResult.batch_id)}
                                    className="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-xs"
                                >
                                    <span>{t('View Detailed Audit Log & Rollback Snapshot')}</span>
                                    <ArrowRight className="w-3.5 h-3.5" />
                                </Link>
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setStep(1);
                                        setFile(null);
                                        setDryRunSummary(null);
                                        setExecutionResult(null);
                                    }}
                                    className="text-xs h-9"
                                >
                                    {t('Start Another Migration')}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
