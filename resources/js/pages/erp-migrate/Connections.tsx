import React, { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from '@/components/ui/dialog';
import { toast } from 'sonner';
import { 
    Database, 
    Plus, 
    Trash2, 
    CheckCircle2, 
    AlertTriangle, 
    RefreshCw, 
    ExternalLink, 
    Key, 
    ShieldCheck, 
    Zap, 
    ArrowLeft,
    Check
} from 'lucide-react';
import axios from 'axios';

interface ConnectionRow {
    id: number;
    name: string;
    source_type: string;
    host: string;
    database_name: string | null;
    username: string | null;
    api_key: string | null;
    api_secret: string | null;
    connection_status: string;
    last_tested_at: string | null;
    last_error: string | null;
    batches_count?: number;
}

interface ConnectionsProps {
    connections: ConnectionRow[];
}

export default function Connections({ connections = [] }: ConnectionsProps) {
    const { t } = useTranslation();
    const [modalOpen, setModalOpen] = useState(false);
    const [isTesting, setIsTesting] = useState(false);
    const [testResult, setTestResult] = useState<any>(null);
    const [testError, setTestError] = useState<string | null>(null);

    const { data, setData, post, processing, reset, errors } = useForm({
        id: '',
        name: '',
        source_type: 'odoo',
        host: '',
        database_name: '',
        username: '',
        api_key: '',
        api_secret: '',
    });

    const openCreateModal = () => {
        reset();
        setTestResult(null);
        setTestError(null);
        setModalOpen(true);
    };

    const handleTest = async () => {
        if (!data.host) {
            toast.error(t('Please enter a host URL to test'));
            return;
        }

        setIsTesting(true);
        setTestResult(null);
        setTestError(null);

        try {
            const res = await axios.post(route('erp-migrate.connections.test'), {
                source_type: data.source_type,
                host: data.host,
                database_name: data.database_name,
                username: data.username,
                api_key: data.api_key,
                api_secret: data.api_secret,
            });

            if (res.data.success) {
                setTestResult(res.data);
                toast.success(res.data.message || t('Connection successful!'));
            } else {
                setTestError(res.data.message || t('Connection failed.'));
            }
        } catch (err: any) {
            setTestError(err.response?.data?.message || t('Network error testing connection.'));
        } finally {
            setIsTesting(false);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('erp-migrate.connections.store'), {
            onSuccess: () => {
                setModalOpen(false);
                reset();
                toast.success(t('Connection saved and verified!'));
            },
        });
    };

    const handleDelete = (id: number, name: string) => {
        if (confirm(t(`Are you sure you want to delete connection "${name}"?`))) {
            router.delete(route('erp-migrate.connections.destroy', id), {
                onSuccess: () => toast.success(t('Connection removed.')),
            });
        }
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('ERP Data Migration'), url: route('erp-migrate.index') },
                { label: t('ERP API Connections') }
            ]}
            pageTitle={t('ERP API Gateways & Credentials')}
        >
            <Head title={t('ERP API Gateways & Credentials - Odoo & ERPNext')} />

            <div className="space-y-6 max-w-6xl mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-white border border-slate-200/80 shadow-2xs">
                    <div>
                        <h2 className="text-lg font-bold text-slate-900 flex items-center gap-2">
                            <Database className="w-5 h-5 text-emerald-600" />
                            {t('ERP API Connections')}
                        </h2>
                        <p className="text-xs text-slate-500 mt-0.5">
                            {t('Configure and manage direct API credentials for external Odoo and ERPNext instances.')}
                        </p>
                    </div>
                    <Button
                        onClick={openCreateModal}
                        className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs h-9 px-4 gap-1.5 shadow-xs"
                    >
                        <Plus className="w-4 h-4" />
                        {t('Add New Connection')}
                    </Button>
                </div>

                {/* Connection Cards Grid */}
                {connections.length === 0 ? (
                    <Card className="border shadow-xs text-center p-12">
                        <div className="max-w-sm mx-auto space-y-3">
                            <div className="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto text-slate-400">
                                <Database className="w-6 h-6" />
                            </div>
                            <h3 className="text-sm font-bold text-slate-800">{t('No ERP connections configured')}</h3>
                            <p className="text-xs text-slate-500">
                                {t('Connect your Odoo or ERPNext instance to enable 1-click live syncing of customers, products, accounts, and employees.')}
                            </p>
                            <Button
                                onClick={openCreateModal}
                                className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9 px-4 gap-1.5 shadow-xs mt-2"
                            >
                                <Plus className="w-3.5 h-3.5" />
                                {t('Configure First Connection')}
                            </Button>
                        </div>
                    </Card>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {connections.map((c) => (
                            <Card key={c.id} className="border border-slate-200/80 shadow-xs hover:border-slate-300 transition-all">
                                <CardHeader className="p-4 pb-3 flex flex-row items-start justify-between border-b bg-slate-50/50">
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2">
                                            <span className="text-base">{c.source_type === 'odoo' ? '🟣' : '🔵'}</span>
                                            <CardTitle className="text-sm font-bold text-slate-900">{c.name}</CardTitle>
                                        </div>
                                        <span className="text-xs font-mono text-slate-500 block">{c.host}</span>
                                    </div>
                                    <div>
                                        {c.connection_status === 'connected' ? (
                                            <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300 text-[10px]">
                                                ✓ {t('Connected')}
                                            </Badge>
                                        ) : c.connection_status === 'failed' ? (
                                            <Badge className="bg-rose-100 text-rose-800 border-rose-300 text-[10px]">
                                                ✕ {t('Failed')}
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline" className="text-slate-400 text-[10px]">
                                                {t('Untested')}
                                            </Badge>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent className="p-4 space-y-3 text-xs">
                                    <div className="grid grid-cols-2 gap-2 text-slate-600">
                                        <div>
                                            <span className="text-slate-400 block text-[10px] uppercase font-semibold">{t('Platform')}</span>
                                            <span className="font-semibold text-slate-800 uppercase">{c.source_type}</span>
                                        </div>
                                        <div>
                                            <span className="text-slate-400 block text-[10px] uppercase font-semibold">{t('Batches Run')}</span>
                                            <span className="font-semibold text-slate-800">{c.batches_count || 0} {t('Runs')}</span>
                                        </div>
                                    </div>

                                    {c.last_error && (
                                        <div className="p-2 rounded bg-rose-50 border border-rose-200 text-rose-700 text-[11px]">
                                            {c.last_error}
                                        </div>
                                    )}

                                    <div className="flex items-center justify-between pt-2 border-t">
                                        <span className="text-[10px] text-slate-400">
                                            {c.last_tested_at ? `${t('Tested')}: ${new Date(c.last_tested_at).toLocaleDateString()}` : t('Never tested')}
                                        </span>
                                        <div className="flex items-center gap-1.5">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => handleDelete(c.id, c.name)}
                                                className="h-7 px-2 text-rose-600 border-rose-200 hover:bg-rose-50 text-[11px]"
                                            >
                                                <Trash2 className="w-3 h-3" />
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {/* Add / Edit Connection Modal */}
                <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                    <DialogContent className="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2 text-base font-bold text-slate-900">
                                <Database className="w-4 h-4 text-emerald-600" />
                                {t('Configure ERP API Connection')}
                            </DialogTitle>
                            <DialogDescription className="text-xs">
                                {t('Enter your ERP host URL and credentials. Authentication will be verified automatically.')}
                            </DialogDescription>
                        </DialogHeader>

                        <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                            <div className="space-y-1">
                                <Label className="text-xs">{t('Connection Name')}</Label>
                                <Input
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g. Production Odoo 16 or Headquarter ERPNext"
                                    required
                                    className="h-9 text-xs"
                                />
                                {errors.name && <p className="text-[11px] text-rose-600">{errors.name}</p>}
                            </div>

                            <div className="space-y-1">
                                <Label className="text-xs">{t('Source ERP Type')}</Label>
                                <select
                                    value={data.source_type}
                                    onChange={(e) => setData('source_type', e.target.value as any)}
                                    className="w-full h-9 border border-slate-200 rounded-md text-xs px-2.5 bg-white"
                                >
                                    <option value="odoo">🟣 Odoo ERP (JSON-RPC / XML-RPC)</option>
                                    <option value="erpnext">🔵 ERPNext / Frappe (REST API)</option>
                                </select>
                            </div>

                            <div className="space-y-1">
                                <Label className="text-xs">{t('Host URL')}</Label>
                                <Input
                                    type="url"
                                    value={data.host}
                                    onChange={(e) => setData('host', e.target.value)}
                                    placeholder="https://myodoo.example.com or https://myerpnext.com"
                                    required
                                    className="h-9 text-xs font-mono"
                                />
                                {errors.host && <p className="text-[11px] text-rose-600">{errors.host}</p>}
                            </div>

                            {data.source_type === 'odoo' ? (
                                <>
                                    <div className="grid grid-cols-2 gap-2">
                                        <div className="space-y-1">
                                            <Label className="text-xs">{t('Database Name')}</Label>
                                            <Input
                                                value={data.database_name}
                                                onChange={(e) => setData('database_name', e.target.value)}
                                                placeholder="e.g. odoo_prod"
                                                required
                                                className="h-9 text-xs font-mono"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-xs">{t('Login / Username')}</Label>
                                            <Input
                                                value={data.username}
                                                onChange={(e) => setData('username', e.target.value)}
                                                placeholder="admin@example.com"
                                                required
                                                className="h-9 text-xs"
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs">{t('Odoo API Key / Password')}</Label>
                                        <Input
                                            type="password"
                                            value={data.api_key}
                                            onChange={(e) => setData('api_key', e.target.value)}
                                            placeholder="Generated in Odoo: Preferences > Account Security"
                                            required
                                            className="h-9 text-xs font-mono"
                                        />
                                    </div>
                                </>
                            ) : (
                                <>
                                    <div className="space-y-1">
                                        <Label className="text-xs">{t('Frappe API Key')}</Label>
                                        <Input
                                            value={data.api_key}
                                            onChange={(e) => setData('api_key', e.target.value)}
                                            placeholder="e.g. 7fa98c2b1e..."
                                            required
                                            className="h-9 text-xs font-mono"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs">{t('Frappe API Secret')}</Label>
                                        <Input
                                            type="password"
                                            value={data.api_secret}
                                            onChange={(e) => setData('api_secret', e.target.value)}
                                            placeholder="e.g. 9b8a7c..."
                                            required
                                            className="h-9 text-xs font-mono"
                                        />
                                    </div>
                                </>
                            )}

                            {/* Test Status Banner */}
                            {testResult && (
                                <div className="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center gap-2">
                                    <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
                                    <span>{testResult.message} ({testResult.server_version || testResult.server})</span>
                                </div>
                            )}

                            {testError && (
                                <div className="p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2">
                                    <AlertTriangle className="w-4 h-4 text-rose-600 shrink-0" />
                                    <span>{testError}</span>
                                </div>
                            )}

                            <DialogFooter className="flex items-center justify-between gap-2 pt-2 border-t">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleTest}
                                    disabled={isTesting || !data.host}
                                    className="text-xs h-9 gap-1.5"
                                >
                                    {isTesting ? (
                                        <>
                                            <RefreshCw className="w-3.5 h-3.5 animate-spin" />
                                            {t('Pinging...')}
                                        </>
                                    ) : (
                                        <>
                                            <Zap className="w-3.5 h-3.5 text-amber-500" />
                                            {t('Test Connection')}
                                        </>
                                    )}
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs h-9 px-4 gap-1.5 shadow-xs"
                                >
                                    <Check className="w-3.5 h-3.5" />
                                    {t('Save Connection')}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </AuthenticatedLayout>
    );
}
