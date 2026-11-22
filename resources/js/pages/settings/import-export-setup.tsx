import React, { useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { 
    Compass, 
    Ship, 
    Anchor, 
    Key, 
    ShieldCheck, 
    Zap, 
    Layers, 
    CheckCircle2, 
    Eye, 
    EyeOff, 
    Info, 
    AlertTriangle, 
    RefreshCw, 
    ExternalLink, 
    Radio,
    Clock,
    DollarSign,
    Check
} from 'lucide-react';

interface SetupProps {
    trackingSettings: {
        provider: string;
        apiKey: string;
        sandboxMode: boolean;
        webhookSecret: string;
        freeDays: number;
        dailyPenalty20ft: number;
        dailyPenalty40ft: number;
    };
}

export default function ImportExportSetup({ trackingSettings }: SetupProps) {
    const { t } = useTranslation();
    const [showKey, setShowKey] = useState(false);
    const [testContainer, setTestContainer] = useState('MSCU9284710');
    const [testShippingLine, setTestShippingLine] = useState('MSC');
    const [isTesting, setIsTesting] = useState(false);
    const [testResult, setTestResult] = useState<any>(null);
    const [testError, setTestError] = useState<string | null>(null);

    const { data, setData, post, processing, errors } = useForm({
        provider: trackingSettings.provider || 'trackingmore',
        apiKey: trackingSettings.apiKey || '',
        sandboxMode: trackingSettings.sandboxMode ?? true,
        webhookSecret: trackingSettings.webhookSecret || '',
        freeDays: trackingSettings.freeDays || 8,
        dailyPenalty20ft: trackingSettings.dailyPenalty20ft || 45,
        dailyPenalty40ft: trackingSettings.dailyPenalty40ft || 85,
    });

    let indexUrl = '/import-export/dashboard';
    let modulesUrl = '/modules';
    let saveUrl = '/settings/import-export/setup';
    let testApiUrl = '/settings/import-export/test-tracking';
    try {
        if (typeof route === 'function') {
            indexUrl = route('import-export.dashboard');
            modulesUrl = route('modules.list');
            saveUrl = route('settings.import-export.setup.save');
            testApiUrl = route('settings.import-export.test-tracking');
        }
    } catch (e) {}

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(saveUrl);
    };

    const handleRunTest = async () => {
        if (!testContainer.trim()) return;
        setIsTesting(true);
        setTestResult(null);
        setTestError(null);

        try {
            const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
            const res = await fetch(testApiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
                body: JSON.stringify({
                    container_number: testContainer.trim(),
                    shipping_line: testShippingLine,
                }),
            });

            const json = await res.json();
            if (res.ok && json.success) {
                setTestResult(json);
            } else {
                setTestError(json.message || json.api_error || 'Failed to fetch tracking data.');
            }
        } catch (err: any) {
            setTestError(err.message || 'Network error occurred during API test.');
        } finally {
            setIsTesting(false);
        }
    };

    const providers = [
        {
            id: 'trackingmore',
            name: 'TrackingMore (Ocean API)',
            badge: 'Recommended for SaaS',
            badgeColor: 'bg-emerald-100 text-emerald-800 border-emerald-300',
            desc: 'Multi-carrier tracking for 100+ ocean shipping lines (MSC, Maersk, CMA CGM, COSCO, ESLSE). Auto-detects carrier from container prefix and provides real-time webhooks.',
            pricing: 'Free Sandbox / ~$0.04 per container lookup',
            docsUrl: 'https://www.trackingmore.com/v4/api-ocean-tracking.html',
        },
        {
            id: 'shipsgo',
            name: 'ShipsGo API',
            badge: 'Live Satellite Maps',
            badgeColor: 'bg-blue-100 text-blue-800 border-blue-300',
            desc: 'Satellite coordinates and live vessel tracking on interactive maps with automated port demurrage and detention notifications.',
            pricing: 'Free Trial / ~$0.20 per container query',
            docsUrl: 'https://shipsgo.com/api',
        },
        {
            id: 'terminal49',
            name: 'Terminal49 API',
            badge: 'Terminal Visibility',
            badgeColor: 'bg-purple-100 text-purple-800 border-purple-300',
            desc: 'Modern developer-first API covering major shipping lines and port terminals with milestone webhook feeds.',
            pricing: 'Tiered Developer Subscription',
            docsUrl: 'https://terminal49.com/api',
        },
        {
            id: 'sandbox',
            name: 'GadaaCloud Simulator (Built-in)',
            badge: 'Zero External Cost',
            badgeColor: 'bg-amber-100 text-amber-800 border-amber-300',
            desc: 'High-fidelity deterministic container journey simulator modeled after the Red Sea, Bab-el-Mandeb, and Djibouti Doraleh corridor. Ideal for demos.',
            pricing: '100% Free / No API Key required',
            docsUrl: '#',
        },
    ];

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Add-on Manager'), url: modulesUrl },
                { label: t('Import & Export'), url: indexUrl },
                { label: t('Container Tracking API Setup') }
            ]}
            pageTitle={t('Global Container Tracking & Djibouti Port Demurrage Setup')}
        >
            <Head title={t('Global Container Tracking & Djibouti Port Demurrage Setup')} />

            <div className="max-w-6xl mx-auto space-y-6">
                {/* Superadmin Header Banner */}
                <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-emerald-950 to-slate-900 text-white p-6 md:p-8 shadow-md border border-emerald-500/20">
                    <div className="relative z-10 space-y-3">
                        <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 text-xs font-semibold uppercase tracking-wider">
                            <ShieldCheck className="w-3.5 h-3.5 text-emerald-400" />
                            {t('Superadmin Global Configuration')}
                        </div>
                        <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight text-white flex items-center gap-2.5">
                            <Compass className="w-7 h-7 text-emerald-400" />
                            {t('Ocean Container Tracking & Djibouti Port Rates')}
                        </h1>
                        <p className="text-slate-300 text-sm md:text-base max-w-3xl leading-relaxed">
                            {t('Configure global ocean carrier tracking APIs across all GadaaCloud multi-tenant accounts. When enabled, tenants can track their shipments in real time from origin ports through Bab-el-Mandeb to the Port of Djibouti (Doraleh) and onward dry ports (Modjo/Kality).')}
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Provider Selection */}
                    <Card className="border shadow-sm">
                        <CardHeader className="border-b bg-gray-50/50 p-5">
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="text-lg font-bold text-gray-900 flex items-center gap-2">
                                        <Ship className="w-5 h-5 text-emerald-600" />
                                        {t('Select Container Tracking Provider')}
                                    </CardTitle>
                                    <CardDescription>
                                        {t('Choose which ocean tracking aggregator will serve live container telemetry for tenant dashboards.')}
                                    </CardDescription>
                                </div>
                                <Badge variant="outline" className="text-xs bg-emerald-50 text-emerald-700 border-emerald-200">
                                    {data.provider.toUpperCase()}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="p-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {providers.map((p) => {
                                    const isSelected = data.provider === p.id;
                                    return (
                                        <div
                                            key={p.id}
                                            onClick={() => setData('provider', p.id)}
                                            className={`relative cursor-pointer rounded-xl border p-4.5 transition-all ${
                                                isSelected
                                                    ? 'border-emerald-600 bg-emerald-50/40 ring-2 ring-emerald-500/20 shadow-sm'
                                                    : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50/70'
                                            }`}
                                        >
                                            <div className="flex items-start justify-between gap-2 mb-2">
                                                <div>
                                                    <h3 className="text-sm font-bold text-gray-900 flex items-center gap-1.5">
                                                        {p.name}
                                                        {isSelected && <CheckCircle2 className="w-4 h-4 text-emerald-600" />}
                                                    </h3>
                                                    <span className={`inline-block mt-1 text-[10px] font-bold px-2 py-0.5 rounded-full border ${p.badgeColor}`}>
                                                        {p.badge}
                                                    </span>
                                                </div>
                                                <div className="text-right">
                                                    <span className="text-[11px] font-medium text-gray-500 block">{p.pricing}</span>
                                                    {p.docsUrl !== '#' && (
                                                        <a
                                                            href={p.docsUrl}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            onClick={(e) => e.stopPropagation()}
                                                            className="inline-flex items-center gap-1 text-[11px] text-emerald-600 hover:text-emerald-700 font-medium mt-1"
                                                        >
                                                            <span>{t('API Docs')}</span>
                                                            <ExternalLink className="w-2.5 h-2.5" />
                                                        </a>
                                                    )}
                                                </div>
                                            </div>
                                            <p className="text-xs text-gray-600 leading-relaxed mt-2">{p.desc}</p>
                                        </div>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>

                    {/* API Key & Credentials */}
                    <Card className="border shadow-sm">
                        <CardHeader className="border-b bg-gray-50/50 p-5">
                            <CardTitle className="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <Key className="w-5 h-5 text-emerald-600" />
                                {t('API Credentials & Sandbox Settings')}
                            </CardTitle>
                            <CardDescription>
                                {t('Enter your API keys provided by TrackingMore or ShipsGo. Sandbox mode allows simulated tracking without external API costs.')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-6 space-y-5">
                            {/* Sandbox Toggle */}
                            <div className="flex items-center justify-between p-4 rounded-xl bg-amber-50/60 border border-amber-200">
                                <div className="space-y-0.5">
                                    <div className="flex items-center gap-2">
                                        <Label className="text-sm font-bold text-amber-900 cursor-pointer" htmlFor="sandbox-toggle">
                                            {t('Enable Sandbox / Demonstration Mode')}
                                        </Label>
                                        <Badge variant="outline" className="text-[10px] bg-amber-100 text-amber-800 border-amber-300">
                                            {data.sandboxMode ? t('Active: Free Simulation') : t('Inactive: Live API')}
                                        </Badge>
                                    </div>
                                    <p className="text-xs text-amber-700">
                                        {t('When active or if API key is blank, GadaaCloud generates authentic simulated vessel telemetry without consuming paid API calls.')}
                                    </p>
                                </div>
                                <Switch
                                    id="sandbox-toggle"
                                    checked={data.sandboxMode}
                                    onCheckedChange={(checked) => setData('sandboxMode', checked)}
                                />
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label className="text-xs font-semibold text-gray-700 flex items-center justify-between">
                                        <span>{t('API Secret Key / Access Token')}</span>
                                        {data.provider !== 'sandbox' && (
                                            <span className="text-[11px] text-gray-400 font-normal">{t('Required for live API')}</span>
                                        )}
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            type={showKey ? 'text' : 'password'}
                                            value={data.apiKey}
                                            onChange={(e) => setData('apiKey', e.target.value)}
                                            placeholder={data.provider === 'sandbox' ? t('Not required in sandbox mode') : 'e.g. tm_live_9a87f6e5...'}
                                            className="font-mono text-xs pr-10"
                                            disabled={data.provider === 'sandbox'}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowKey(!showKey)}
                                            className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                        >
                                            {showKey ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                                        </button>
                                    </div>
                                    {errors.apiKey && <p className="text-xs text-red-600">{errors.apiKey}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label className="text-xs font-semibold text-gray-700">
                                        {t('Webhook Secret (Optional)')}
                                    </Label>
                                    <Input
                                        type="text"
                                        value={data.webhookSecret}
                                        onChange={(e) => setData('webhookSecret', e.target.value)}
                                        placeholder="e.g. whsec_73b98c..."
                                        className="font-mono text-xs"
                                    />
                                    <p className="text-[11px] text-gray-400">
                                        {t('Used to authenticate asynchronous status callbacks from TrackingMore / ShipsGo.')}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Djibouti Port Demurrage Policies */}
                    <Card className="border shadow-sm">
                        <CardHeader className="border-b bg-gray-50/50 p-5">
                            <CardTitle className="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <Anchor className="w-5 h-5 text-emerald-600" />
                                {t('Djibouti Port (Doraleh) Demurrage & Free Days Policy')}
                            </CardTitle>
                            <CardDescription>
                                {t('Standardize statutory port storage grace periods and daily demurrage penalty fees across the Ethiopia-Djibouti corridor.')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-6">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
                                <div className="space-y-2">
                                    <Label className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                                        <Clock className="w-3.5 h-3.5 text-gray-500" />
                                        {t('Free Days at Djibouti Port')}
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            type="number"
                                            min={1}
                                            max={60}
                                            value={data.freeDays}
                                            onChange={(e) => setData('freeDays', parseInt(e.target.value) || 8)}
                                            className="font-semibold text-sm"
                                        />
                                        <span className="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium">
                                            {t('Days')}
                                        </span>
                                    </div>
                                    <p className="text-[11px] text-gray-500">
                                        {t('Standard: 8 days at Doraleh Container Terminal (DCT).')}
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                                        <DollarSign className="w-3.5 h-3.5 text-gray-500" />
                                        {t('20FT Container Daily Penalty')}
                                    </Label>
                                    <div className="relative">
                                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-bold">$</span>
                                        <Input
                                            type="number"
                                            min={0}
                                            step="0.01"
                                            value={data.dailyPenalty20ft}
                                            onChange={(e) => setData('dailyPenalty20ft', parseFloat(e.target.value) || 0)}
                                            className="pl-7 font-semibold text-sm"
                                        />
                                        <span className="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium">USD / day</span>
                                    </div>
                                    <p className="text-[11px] text-gray-500">
                                        {t('Carrier penalty after free days expire (Typical: $45 USD).')}
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label className="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                                        <DollarSign className="w-3.5 h-3.5 text-gray-500" />
                                        {t('40FT Container Daily Penalty')}
                                    </Label>
                                    <div className="relative">
                                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-bold">$</span>
                                        <Input
                                            type="number"
                                            min={0}
                                            step="0.01"
                                            value={data.dailyPenalty40ft}
                                            onChange={(e) => setData('dailyPenalty40ft', parseFloat(e.target.value) || 0)}
                                            className="pl-7 font-semibold text-sm"
                                        />
                                        <span className="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 font-medium">USD / day</span>
                                    </div>
                                    <p className="text-[11px] text-gray-500">
                                        {t('Carrier penalty for 40FT/High Cube (Typical: $85 USD).')}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Action Buttons */}
                    <div className="flex items-center justify-end gap-3 pt-2">
                        <Link
                            href={modulesUrl}
                            className="inline-flex items-center justify-center h-10 px-4 rounded-lg border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            {t('Cancel')}
                        </Link>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold h-10 px-6 gap-2 shadow-sm"
                        >
                            {processing ? (
                                <>
                                    <RefreshCw className="w-4 h-4 animate-spin" />
                                    {t('Saving Policies...')}
                                </>
                            ) : (
                                <>
                                    <Check className="w-4 h-4" />
                                    {t('Save API Configuration')}
                                </>
                            )}
                        </Button>
                    </div>
                </form>

                {/* Live Test & Verification Console */}
                <Card className="border shadow-sm bg-slate-900 text-white">
                    <CardHeader className="border-b border-slate-800 p-5">
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-base font-bold text-white flex items-center gap-2">
                                <Radio className="w-4 h-4 text-emerald-400 animate-pulse" />
                                {t('Container Tracking API Live Connection Tester')}
                            </CardTitle>
                            <Badge variant="outline" className="bg-emerald-950 text-emerald-400 border-emerald-500/30 text-xs">
                                {data.sandboxMode ? 'Sandbox Simulator Ready' : 'Live Carrier Gateway'}
                            </Badge>
                        </div>
                        <CardDescription className="text-slate-400 text-xs">
                            {t('Test your connection or simulate container tracking in real time with real carrier container numbers (e.g. MSCU, MAEU, CMAU, ESLU).')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-5 space-y-4">
                        <div className="flex flex-col sm:flex-row items-center gap-3">
                            <div className="w-full sm:w-1/2">
                                <Label className="text-xs text-slate-300 mb-1 block">{t('Sample Container Number')}</Label>
                                <Input
                                    type="text"
                                    value={testContainer}
                                    onChange={(e) => setTestContainer(e.target.value.toUpperCase())}
                                    placeholder="e.g. MSCU9284710"
                                    className="bg-slate-800 border-slate-700 text-white font-mono text-sm uppercase"
                                />
                            </div>
                            <div className="w-full sm:w-1/3">
                                <Label className="text-xs text-slate-300 mb-1 block">{t('Shipping Line')}</Label>
                                <Input
                                    type="text"
                                    value={testShippingLine}
                                    onChange={(e) => setTestShippingLine(e.target.value)}
                                    placeholder="MSC / Maersk / CMA CGM"
                                    className="bg-slate-800 border-slate-700 text-white text-sm"
                                />
                            </div>
                            <div className="w-full sm:w-auto self-end pt-5 sm:pt-0">
                                <Button
                                    type="button"
                                    onClick={handleRunTest}
                                    disabled={isTesting || !testContainer.trim()}
                                    className="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs h-10 px-5 gap-2"
                                >
                                    {isTesting ? (
                                        <>
                                            <RefreshCw className="w-3.5 h-3.5 animate-spin" />
                                            {t('Pinging API...')}
                                        </>
                                    ) : (
                                        <>
                                            <Zap className="w-3.5 h-3.5" />
                                            {t('Run Test Lookup')}
                                        </>
                                    )}
                                </Button>
                            </div>
                        </div>

                        {/* Error Notice */}
                        {testError && (
                            <div className="p-3.5 rounded-lg bg-red-950/70 border border-red-800/80 text-red-200 text-xs flex items-center gap-2">
                                <AlertTriangle className="w-4 h-4 text-red-400 shrink-0" />
                                <span>{testError}</span>
                            </div>
                        )}

                        {/* Test Result Display */}
                        {testResult && (
                            <div className="mt-4 p-4 rounded-xl bg-slate-800/90 border border-slate-700 space-y-4">
                                <div className="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-3 border-b border-slate-700">
                                    <div className="flex items-center gap-2.5">
                                        <Badge className="bg-emerald-500 text-white font-mono font-bold text-xs">
                                            {testResult.container_number}
                                        </Badge>
                                        <span className="text-xs font-semibold text-slate-200">
                                            {testResult.carrier?.name || 'Carrier'}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs text-slate-400 font-mono">
                                            Vessel: <strong className="text-emerald-400">{testResult.vessel?.name}</strong> (Voyage: {testResult.vessel?.voyage})
                                        </span>
                                        <span className="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                            {testResult.status_label}
                                        </span>
                                    </div>
                                </div>

                                {/* Demurrage & Route Overview */}
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                                    <div className="p-2.5 rounded-lg bg-slate-900/60 border border-slate-700/60">
                                        <span className="text-slate-400 block text-[11px]">{t('Port of Loading')}</span>
                                        <span className="font-semibold text-slate-100">{testResult.route?.origin}</span>
                                    </div>
                                    <div className="p-2.5 rounded-lg bg-slate-900/60 border border-slate-700/60">
                                        <span className="text-slate-400 block text-[11px]">{t('Destination Port')}</span>
                                        <span className="font-semibold text-emerald-400">{testResult.route?.destination}</span>
                                    </div>
                                    <div className="p-2.5 rounded-lg bg-slate-900/60 border border-slate-700/60">
                                        <span className="text-slate-400 block text-[11px]">{t('Djibouti Free Days Left')}</span>
                                        <span className="font-semibold text-amber-300">
                                            {testResult.demurrage?.days_remaining} {t('Days Remaining')}
                                        </span>
                                    </div>
                                    <div className="p-2.5 rounded-lg bg-slate-900/60 border border-slate-700/60">
                                        <span className="text-slate-400 block text-[11px]">{t('Active Engine')}</span>
                                        <span className="font-mono text-[11px] text-slate-300">{testResult.provider}</span>
                                    </div>
                                </div>

                                {/* Milestones preview */}
                                {testResult.milestones && testResult.milestones.length > 0 && (
                                    <div className="space-y-2 pt-1">
                                        <span className="text-xs font-semibold text-slate-300 block">{t('Milestone Events:')}</span>
                                        <div className="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                                            {testResult.milestones.map((m: any, idx: number) => (
                                                <div key={idx} className="flex items-start gap-2.5 text-xs p-2 rounded bg-slate-900/40 border border-slate-800">
                                                    <span className="w-2 h-2 rounded-full bg-emerald-400 mt-1 shrink-0" />
                                                    <div className="flex-1">
                                                        <div className="flex items-center justify-between">
                                                            <strong className="text-slate-200">{m.title}</strong>
                                                            <span className="text-slate-500 font-mono text-[10px]">{m.date}</span>
                                                        </div>
                                                        <p className="text-slate-400 text-[11px]">{m.description} — <em className="text-slate-500 not-italic">{m.location}</em></p>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
