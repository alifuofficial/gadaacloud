import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { 
    Settings, 
    ShieldCheck, 
    Building2, 
    Key, 
    Lock, 
    CheckCircle2, 
    AlertTriangle, 
    RefreshCw, 
    ExternalLink, 
    Clock, 
    FileText 
} from 'lucide-react';

interface SettingsProps {
    setting: {
        tin: string | null;
        vat_number: string | null;
        legal_name: string | null;
        trade_name: string | null;
        branch_code: string | null;
        device_id: string | null;
        environment: 'sandbox' | 'production';
        api_endpoint: string | null;
        has_api_key: boolean;
        has_api_secret: boolean;
        offline_resiliency_enabled: boolean;
        auto_clear_on_post: boolean;
    } | null;
    recentLogs: any[];
}

export default function EimsSettings({ setting, recentLogs = [] }: SettingsProps) {
    const { t } = useTranslation();

    const [form, setForm] = useState({
        tin: setting?.tin || '',
        vat_number: setting?.vat_number || '',
        legal_name: setting?.legal_name || '',
        trade_name: setting?.trade_name || '',
        branch_code: setting?.branch_code || 'MAIN',
        device_id: setting?.device_id || 'POS-01',
        environment: setting?.environment || 'sandbox',
        api_endpoint: setting?.api_endpoint || '',
        api_key: '',
        api_secret: '',
        offline_resiliency_enabled: setting?.offline_resiliency_enabled ?? true,
        auto_clear_on_post: setting?.auto_clear_on_post ?? true,
    });

    const [isSaving, setIsSaving] = useState(false);
    const [isTesting, setIsTesting] = useState(false);
    const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(null);

    const breadcrumbs = [
        { label: t('E-Invoicing (EIMS)'), url: route('eims.dashboard') },
        { label: t('MoR Settings'), url: route('eims.settings.index') },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSaving(true);
        router.post(route('eims.settings.update'), form, {
            onFinish: () => setIsSaving(false)
        });
    };

    const handleTestConnection = async () => {
        setIsTesting(true);
        setTestResult(null);
        try {
            const resp = await fetch(route('eims.settings.test'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
                }
            });
            const data = await resp.json();
            setTestResult(data);
        } catch (err: any) {
            setTestResult({ success: false, message: err.message || 'Connection test failed' });
        } finally {
            setIsTesting(false);
        }
    };

    return (
        <AuthenticatedLayout breadcrumbs={breadcrumbs} pageTitle={t('MoR EIMS Taxpayer Settings')}>
            <Head title={t('EIMS Settings - Directive 1142/2026')} />

            <div className="space-y-6 p-4 md:p-6 max-w-5xl">
                {/* Header */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight text-foreground">
                                {t('Ministry of Revenues (MoR) Settings')}
                            </h1>
                            <Badge variant={form.environment === 'production' ? 'default' : 'secondary'}>
                                {form.environment === 'production' ? t('Production CTC Mode') : t('Directive 1142 Sandbox')}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground mt-1">
                            {t('Configure your company TIN, fiscal device identifiers, and clearance credentials under Directive 1142/2026.')}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button 
                            variant="outline" 
                            size="sm" 
                            onClick={handleTestConnection} 
                            disabled={isTesting}
                            className="gap-1.5"
                        >
                            <RefreshCw className={`h-4 w-4 ${isTesting ? 'animate-spin' : ''}`} />
                            {isTesting ? t('Testing...') : t('Test Gateway')}
                        </Button>
                        <Link href={route('eims.audit-logs.index')}>
                            <Button variant="outline" size="sm" className="gap-1.5">
                                <FileText className="h-4 w-4" />
                                {t('INSA Audit Logs')}
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Connection Test Banner */}
                {testResult && (
                    <div className={`rounded-xl border p-4 ${
                        testResult.success 
                            ? 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200' 
                            : 'border-red-300 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200'
                    }`}>
                        <div className="flex items-center gap-2 text-sm font-semibold">
                            {testResult.success ? <CheckCircle2 className="h-4 w-4 text-emerald-600" /> : <AlertTriangle className="h-4 w-4 text-red-600" />}
                            {testResult.message}
                        </div>
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Taxpayer Identity Card */}
                    <Card className="shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-base font-semibold flex items-center gap-2">
                                <Building2 className="h-4 w-4 text-primary" />
                                {t('Taxpayer Profile & Identity')}
                            </CardTitle>
                            <CardDescription className="text-xs">
                                {t('Official business registration information as recorded in the Ministry of Revenues database')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                            <div>
                                <label className="block font-medium text-foreground mb-1">
                                    {t('Taxpayer Identification Number (TIN)')} <span className="text-red-500">*</span>
                                </label>
                                <Input
                                    value={form.tin}
                                    onChange={(e) => setForm({ ...form, tin: e.target.value })}
                                    placeholder="e.g. 0012345678"
                                    maxLength={10}
                                    pattern="\d{10}"
                                    required
                                    className="font-mono text-sm"
                                />
                                <p className="text-[10px] text-muted-foreground mt-1">
                                    {t('Must be exactly 10 digits as issued by the Ethiopian Ministry of Revenues.')}
                                </p>
                            </div>

                            <div>
                                <label className="block font-medium text-foreground mb-1">
                                    {t('VAT Registration Number')}
                                </label>
                                <Input
                                    value={form.vat_number}
                                    onChange={(e) => setForm({ ...form, vat_number: e.target.value })}
                                    placeholder="e.g. VAT0012345678"
                                    className="font-mono text-sm"
                                />
                                <p className="text-[10px] text-muted-foreground mt-1">
                                    {t('Defaults to VAT followed by your 10-digit TIN.')}
                                </p>
                            </div>

                            <div>
                                <label className="block font-medium text-foreground mb-1">
                                    {t('Registered Legal Name')} <span className="text-red-500">*</span>
                                </label>
                                <Input
                                    value={form.legal_name}
                                    onChange={(e) => setForm({ ...form, legal_name: e.target.value })}
                                    placeholder="e.g. Gadaa Commercial Trading PLC"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block font-medium text-foreground mb-1">
                                    {t('Trade Name / Brand')}
                                </label>
                                <Input
                                    value={form.trade_name}
                                    onChange={(e) => setForm({ ...form, trade_name: e.target.value })}
                                    placeholder="e.g. Gadaa Cloud Solutions"
                                />
                            </div>

                            <div>
                                <label className="block font-medium text-foreground mb-1">
                                    {t('Fiscal Branch Code')} <span className="text-red-500">*</span>
                                </label>
                                <Input
                                    value={form.branch_code}
                                    onChange={(e) => setForm({ ...form, branch_code: e.target.value })}
                                    placeholder="MAIN"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block font-medium text-foreground mb-1">
                                    {t('Registered Device / POS Terminal ID')}
                                </label>
                                <Input
                                    value={form.device_id}
                                    onChange={(e) => setForm({ ...form, device_id: e.target.value })}
                                    placeholder="POS-01"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* MoR Clearance Gateway API Card */}
                    <Card className="shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-base font-semibold flex items-center gap-2">
                                <Key className="h-4 w-4 text-primary" />
                                {t('Gateway Credentials & Environment')}
                            </CardTitle>
                            <CardDescription className="text-xs">
                                {t('Continuous Transaction Control (CTC) API endpoint and cryptographic signature keys')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 text-xs">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block font-medium text-foreground mb-1">
                                        {t('Clearance Environment')}
                                    </label>
                                    <select
                                        className="w-full h-9 rounded-md border border-input bg-background px-3 text-xs"
                                        value={form.environment}
                                        onChange={(e) => setForm({ ...form, environment: e.target.value as any })}
                                    >
                                        <option value="sandbox">{t('Directive 1142 Regulatory Sandbox (Simulation & Staging)')}</option>
                                        <option value="production">{t('Live MoR Gateway (Production Continuous Transaction Control)')}</option>
                                    </select>
                                    <p className="text-[10px] text-muted-foreground mt-1">
                                        {t('In sandbox mode, GadaaCloud uses the deterministic Directive 1142 clearance engine to simulate IRN & QR generation.')}
                                    </p>
                                </div>

                                <div>
                                    <label className="block font-medium text-foreground mb-1">
                                        {t('Gateway Endpoint URL')}
                                    </label>
                                    <Input
                                        value={form.api_endpoint}
                                        onChange={(e) => setForm({ ...form, api_endpoint: e.target.value })}
                                        placeholder="https://eims.mor.gov.et"
                                        className="font-mono text-xs"
                                    />
                                    <p className="text-[10px] text-muted-foreground mt-1">
                                        {t('Leave empty to use built-in compliant sandbox simulation.')}
                                    </p>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2 border-t">
                                <div>
                                    <label className="block font-medium text-foreground mb-1">
                                        {t('API Key / Bearer Token')}
                                    </label>
                                    <Input
                                        type="password"
                                        value={form.api_key}
                                        onChange={(e) => setForm({ ...form, api_key: e.target.value })}
                                        placeholder={setting?.has_api_key ? '••••••••••••••••••••' : t('Enter API Key')}
                                        className="font-mono text-xs"
                                    />
                                    <p className="text-[10px] text-muted-foreground mt-1">
                                        {setting?.has_api_key && <span className="text-emerald-600 font-medium">{t('Saved & AES-256 Encrypted. ')}</span>}
                                        {t('Leave empty to keep current key.')}
                                    </p>
                                </div>

                                <div>
                                    <label className="block font-medium text-foreground mb-1">
                                        {t('HMAC-SHA256 Secret Key')}
                                    </label>
                                    <Input
                                        type="password"
                                        value={form.api_secret}
                                        onChange={(e) => setForm({ ...form, api_secret: e.target.value })}
                                        placeholder={setting?.has_api_secret ? '••••••••••••••••••••' : t('Enter Secret Key')}
                                        className="font-mono text-xs"
                                    />
                                    <p className="text-[10px] text-muted-foreground mt-1">
                                        {setting?.has_api_secret && <span className="text-emerald-600 font-medium">{t('Saved & AES-256 Encrypted. ')}</span>}
                                        {t('Used for INSA cryptographic audit signatures.')}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Automation & Resiliency Toggles */}
                    <Card className="shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-base font-semibold flex items-center gap-2">
                                <Clock className="h-4 w-4 text-primary" />
                                {t('Automation & Offline Resiliency')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 text-xs">
                            <label className="flex items-start gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={form.auto_clear_on_post}
                                    onChange={(e) => setForm({ ...form, auto_clear_on_post: e.target.checked })}
                                    className="mt-0.5 rounded border-gray-300 text-primary focus:ring-primary h-4 w-4"
                                />
                                <div>
                                    <div className="font-semibold text-foreground">
                                        {t('Automatic Clearance on Sales Invoice Posting')}
                                    </div>
                                    <p className="text-muted-foreground text-[11px] mt-0.5">
                                        {t('When an accountant or sales agent posts an invoice in GadaaCloud, immediately generate the Directive 1142 payload and register with MoR.')}
                                    </p>
                                </div>
                            </label>

                            <label className="flex items-start gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={form.offline_resiliency_enabled}
                                    onChange={(e) => setForm({ ...form, offline_resiliency_enabled: e.target.checked })}
                                    className="mt-0.5 rounded border-gray-300 text-primary focus:ring-primary h-4 w-4"
                                />
                                <div>
                                    <div className="font-semibold text-foreground">
                                        {t('Directive 1142 72-Hour Offline Resiliency Buffer')}
                                    </div>
                                    <p className="text-muted-foreground text-[11px] mt-0.5">
                                        {t('If internet connectivity drops during checkout, buffer encrypted transaction locally and auto-synchronize in the background within 72 hours.')}
                                    </p>
                                </div>
                            </label>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-2">
                        <Button type="submit" disabled={isSaving} className="gap-2">
                            <ShieldCheck className="h-4 w-4" />
                            {isSaving ? t('Saving Settings...') : t('Save MoR Configuration')}
                        </Button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
