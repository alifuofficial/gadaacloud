import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { 
    Receipt, 
    Search, 
    Filter, 
    CheckCircle2, 
    AlertTriangle, 
    XCircle, 
    Copy, 
    Check, 
    Eye, 
    Ban, 
    ShieldCheck, 
    CloudOff, 
    QrCode,
    ExternalLink,
    RefreshCw
} from 'lucide-react';

interface EimsInvoicesProps {
    invoices: {
        data: any[];
        links: any[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        status?: string;
        buyer_type?: string;
        document_type?: string;
        search?: string;
    };
}

export default function EimsInvoices({ invoices, filters = {} }: EimsInvoicesProps) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [buyerTypeFilter, setBuyerTypeFilter] = useState(filters.buyer_type || '');
    const [copiedId, setCopiedId] = useState<number | null>(null);

    // Cancel modal state
    const [cancelModalOpen, setCancelModalOpen] = useState(false);
    const [selectedInvoice, setSelectedInvoice] = useState<any | null>(null);
    const [reasonCode, setReasonCode] = useState('2');
    const [cancelRemark, setCancelRemark] = useState('');
    const [isCancelling, setIsCancelling] = useState(false);

    // Verification modal state
    const [verifyModalOpen, setVerifyModalOpen] = useState(false);
    const [verificationResult, setVerificationResult] = useState<any | null>(null);
    const [isVerifying, setIsVerifying] = useState(false);

    const breadcrumbs = [
        { label: t('E-Invoicing (EIMS)'), url: route('eims.dashboard') },
        { label: t('Cleared Invoices'), url: route('eims.invoices.index') }
    ];

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'ETB',
            minimumFractionDigits: 2
        }).format(amount);
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('eims.invoices.index'), {
            search: search || undefined,
            status: statusFilter || undefined,
            buyer_type: buyerTypeFilter || undefined,
        }, { preserveState: true });
    };

    const handleFilterChange = (newStatus: string, newBuyerType: string) => {
        setStatusFilter(newStatus);
        setBuyerTypeFilter(newBuyerType);
        router.get(route('eims.invoices.index'), {
            search: search || undefined,
            status: newStatus || undefined,
            buyer_type: newBuyerType || undefined,
        }, { preserveState: true });
    };

    const copyToClipboard = (text: string, id: number) => {
        navigator.clipboard.writeText(text);
        setCopiedId(id);
        setTimeout(() => setCopiedId(null), 2000);
    };

    const openCancelModal = (inv: any) => {
        setSelectedInvoice(inv);
        setReasonCode('2');
        setCancelRemark('');
        setCancelModalOpen(true);
    };

    const submitCancel = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedInvoice) return;

        setIsCancelling(true);
        router.post(route('eims.invoices.cancel', selectedInvoice.id), {
            reason_code: parseInt(reasonCode),
            remark: cancelRemark,
        }, {
            onFinish: () => {
                setIsCancelling(false);
                setCancelModalOpen(false);
                setSelectedInvoice(null);
            }
        });
    };

    const runVerify = async (inv: any) => {
        setIsVerifying(true);
        setVerifyModalOpen(true);
        setVerificationResult(null);
        try {
            const resp = await fetch(route('eims.invoices.verify', inv.id));
            const data = await resp.json();
            setVerificationResult(data);
        } catch (err: any) {
            setVerificationResult({ success: false, error: err.message });
        } finally {
            setIsVerifying(false);
        }
    };

    return (
        <AuthenticatedLayout breadcrumbs={breadcrumbs} pageTitle={t('EIMS Cleared Invoices')}>
            <Head title={t('EIMS Invoices - Directive 1142/2026')} />

            <div className="space-y-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">
                            {t('MoR Cleared Invoices')}
                        </h1>
                        <p className="text-sm text-muted-foreground mt-1">
                            {t('Real-time registered sales invoices with IRN hashes, RRN receipts, and fiscal QR codes.')}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Link href={route('eims.offline-queue.index')}>
                            <Button variant="outline" size="sm">
                                <CloudOff className="mr-2 h-4 w-4" />
                                {t('Offline Queue')}
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Search & Filters */}
                <Card className="shadow-sm">
                    <CardContent className="p-4">
                        <form onSubmit={handleSearch} className="flex flex-col md:flex-row gap-3">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder={t('Search by Invoice #, Buyer Name, TIN, or IRN hash...')}
                                    className="pl-9"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                <select
                                    aria-label="Filter by Clearance Status"
                                    className="h-9 rounded-md border border-input bg-background px-3 py-1 text-xs shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                    value={statusFilter}
                                    onChange={(e) => handleFilterChange(e.target.value, buyerTypeFilter)}
                                >
                                    <option value="">{t('All Statuses')}</option>
                                    <option value="CLEARED">{t('Cleared')}</option>
                                    <option value="PENDING_OFFLINE">{t('Offline Buffered')}</option>
                                    <option value="CANCELLED">{t('Cancelled')}</option>
                                    <option value="FAILED">{t('Failed')}</option>
                                </select>

                                <select
                                    aria-label="Filter by Buyer Type"
                                    className="h-9 rounded-md border border-input bg-background px-3 py-1 text-xs shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                    value={buyerTypeFilter}
                                    onChange={(e) => handleFilterChange(statusFilter, e.target.value)}
                                >
                                    <option value="">{t('All Buyer Types')}</option>
                                    <option value="B2B">{t('B2B (Business)')}</option>
                                    <option value="B2C">{t('B2C (Consumer)')}</option>
                                    <option value="B2G">{t('B2G (Government)')}</option>
                                </select>

                                <Button type="submit" size="sm">
                                    <Filter className="mr-1.5 h-3.5 w-3.5" />
                                    {t('Filter')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Invoices Table */}
                <Card className="shadow-sm">
                    <CardContent className="p-0">
                        {invoices.data.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Receipt className="h-12 w-12 text-muted-foreground/40 mb-3" />
                                <p className="text-base font-medium text-foreground">{t('No e-invoices found')}</p>
                                <p className="text-xs text-muted-foreground mt-1 max-w-sm">
                                    {t('No transactions match your search criteria. Try modifying your filter options.')}
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b bg-muted/30 text-xs font-semibold text-muted-foreground">
                                            <th className="p-3 pl-4">{t('Invoice #')}</th>
                                            <th className="p-3">{t('Doc')}</th>
                                            <th className="p-3">{t('Buyer Details')}</th>
                                            <th className="p-3">{t('IRN Registration Number')}</th>
                                            <th className="p-3 text-right">{t('Pre-Tax')}</th>
                                            <th className="p-3 text-right">{t('VAT 15%')}</th>
                                            <th className="p-3 text-right">{t('Total Amount')}</th>
                                            <th className="p-3 text-center">{t('Status')}</th>
                                            <th className="p-3 pr-4 text-right">{t('Actions')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y text-xs">
                                        {invoices.data.map((inv) => (
                                            <tr key={inv.id} className="hover:bg-muted/40 transition-colors">
                                                <td className="p-3 pl-4 font-semibold text-foreground">
                                                    <Link href={route('eims.invoices.show', inv.id)} className="hover:underline text-primary">
                                                        {inv.invoice_number}
                                                    </Link>
                                                    <div className="text-[10px] text-muted-foreground">
                                                        {new Date(inv.created_at).toLocaleDateString()}
                                                    </div>
                                                </td>

                                                <td className="p-3">
                                                    <Badge variant="outline" className="font-mono text-[10px]">
                                                        {inv.document_type || 'INV'}
                                                    </Badge>
                                                </td>

                                                <td className="p-3 max-w-[180px]">
                                                    <div className="font-medium text-foreground truncate">
                                                        {inv.buyer_name || 'Consumer'}
                                                    </div>
                                                    <div className="text-[10px] text-muted-foreground flex items-center gap-1">
                                                        <Badge variant="secondary" className="text-[9px] px-1 py-0">
                                                            {inv.buyer_type}
                                                        </Badge>
                                                        {inv.buyer_tin && <span className="font-mono">TIN: {inv.buyer_tin}</span>}
                                                    </div>
                                                </td>

                                                <td className="p-3 max-w-[220px]">
                                                    {inv.irn ? (
                                                        <div className="flex items-center gap-1 font-mono text-[11px] text-muted-foreground">
                                                            <span className="truncate">{inv.irn.slice(0, 16)}...{inv.irn.slice(-6)}</span>
                                                            <button
                                                                onClick={() => copyToClipboard(inv.irn, inv.id)}
                                                                className="text-muted-foreground hover:text-foreground shrink-0 p-1"
                                                                title={t('Copy full IRN hash')}
                                                            >
                                                                {copiedId === inv.id ? (
                                                                    <Check className="h-3.5 w-3.5 text-emerald-500" />
                                                                ) : (
                                                                    <Copy className="h-3.5 w-3.5" />
                                                                )}
                                                            </button>
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground italic">—</span>
                                                    )}
                                                    {inv.rrn && (
                                                        <div className="text-[10px] text-muted-foreground font-mono">
                                                            RRN: {inv.rrn}
                                                        </div>
                                                    )}
                                                </td>

                                                <td className="p-3 text-right">
                                                    {formatCurrency(Number(inv.pre_tax_amount))}
                                                </td>

                                                <td className="p-3 text-right text-emerald-600 dark:text-emerald-400 font-medium">
                                                    {formatCurrency(Number(inv.tax_amount))}
                                                </td>

                                                <td className="p-3 text-right font-bold text-foreground">
                                                    {formatCurrency(Number(inv.total_amount))}
                                                </td>

                                                <td className="p-3 text-center">
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

                                                <td className="p-3 pr-4 text-right">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Link href={route('eims.invoices.show', inv.id)}>
                                                            <Button variant="ghost" size="sm" className="h-7 w-7 p-0" title={t('View Details & Fiscal QR')}>
                                                                <Eye className="h-3.5 w-3.5" />
                                                            </Button>
                                                        </Link>

                                                        {inv.status === 'CLEARED' && (
                                                            <>
                                                                <Button 
                                                                    variant="ghost" 
                                                                    size="sm" 
                                                                    className="h-7 w-7 p-0 text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                                                                    title={t('Verify IRN with MoR')}
                                                                    onClick={() => runVerify(inv)}
                                                                >
                                                                    <ShieldCheck className="h-3.5 w-3.5" />
                                                                </Button>

                                                                <Button 
                                                                    variant="ghost" 
                                                                    size="sm" 
                                                                    className="h-7 w-7 p-0 text-red-600 hover:text-red-700 hover:bg-red-50"
                                                                    title={t('Cancel with Ministry Reason Code')}
                                                                    onClick={() => openCancelModal(inv)}
                                                                >
                                                                    <Ban className="h-3.5 w-3.5" />
                                                                </Button>
                                                            </>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {/* Pagination */}
                        {invoices.links && invoices.links.length > 3 && (
                            <div className="flex items-center justify-between border-t p-3 text-xs text-muted-foreground">
                                <div>
                                    {t('Showing {{from}} to {{to}} of {{total}} e-invoices', {
                                        from: ((invoices.current_page - 1) * 15) + 1,
                                        to: Math.min(invoices.current_page * 15, invoices.total),
                                        total: invoices.total
                                    })}
                                </div>
                                <div className="flex gap-1">
                                    {invoices.links.map((link: any, idx: number) => (
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

            {/* MoR Cancellation Reason Modal */}
            {cancelModalOpen && selectedInvoice && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                    <div className="w-full max-w-md rounded-xl bg-card p-6 shadow-xl border border-border space-y-4">
                        <div className="flex items-start justify-between">
                            <div>
                                <h3 className="text-base font-bold text-foreground">
                                    {t('Cancel Cleared Invoice in MoR')}
                                </h3>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    {t('Invoice: {{num}}', { num: selectedInvoice.invoice_number })}
                                </p>
                            </div>
                            <button 
                                onClick={() => setCancelModalOpen(false)}
                                className="text-muted-foreground hover:text-foreground text-sm"
                            >
                                ✕
                            </button>
                        </div>

                        <form onSubmit={submitCancel} className="space-y-4 text-xs">
                            <div>
                                <label className="block font-semibold text-foreground mb-1">
                                    {t('Official Directive 1142 Cancellation Reason Code')}
                                </label>
                                <select
                                    className="w-full h-9 rounded-md border border-input bg-background px-3 text-xs"
                                    value={reasonCode}
                                    onChange={(e) => setReasonCode(e.target.value)}
                                >
                                    <option value="1">1 - Duplicate Invoice</option>
                                    <option value="2">2 - Data Entry Mistake</option>
                                    <option value="3">3 - Order Cancelled by Customer</option>
                                    <option value="4">4 - Others (Regulatory / Technical)</option>
                                </select>
                            </div>

                            <div>
                                <label className="block font-semibold text-foreground mb-1">
                                    {t('Explanation / Remark (Max 100 characters)')}
                                </label>
                                <Input
                                    value={cancelRemark}
                                    onChange={(e) => setCancelRemark(e.target.value)}
                                    placeholder={t('Explain reason for cancellation...')}
                                    maxLength={100}
                                />
                            </div>

                            <div className="rounded-lg bg-amber-500/10 border border-amber-500/20 p-2.5 text-amber-700 dark:text-amber-300">
                                <p className="text-[11px] leading-relaxed">
                                    {t('Under MoR regulations, cancellation invalidates the clearance IRN with the tax authority and issues a corresponding regulatory cancellation log.')}
                                </p>
                            </div>

                            <div className="flex justify-end gap-2 pt-2">
                                <Button 
                                    type="button" 
                                    variant="outline" 
                                    size="sm" 
                                    onClick={() => setCancelModalOpen(false)}
                                >
                                    {t('Cancel')}
                                </Button>
                                <Button 
                                    type="submit" 
                                    variant="destructive" 
                                    size="sm" 
                                    disabled={isCancelling}
                                >
                                    {isCancelling ? t('Submitting...') : t('Confirm Cancellation')}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Verification Result Modal */}
            {verifyModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                    <div className="w-full max-w-md rounded-xl bg-card p-6 shadow-xl border border-border space-y-4">
                        <div className="flex items-start justify-between">
                            <div className="flex items-center gap-2">
                                <ShieldCheck className="h-5 w-5 text-primary" />
                                <h3 className="text-base font-bold text-foreground">
                                    {t('MoR Gateway IRN Verification')}
                                </h3>
                            </div>
                            <button 
                                onClick={() => setVerifyModalOpen(false)}
                                className="text-muted-foreground hover:text-foreground text-sm"
                            >
                                ✕
                            </button>
                        </div>

                        {isVerifying ? (
                            <div className="flex flex-col items-center justify-center py-8 space-y-2">
                                <RefreshCw className="h-8 w-8 animate-spin text-primary" />
                                <p className="text-xs text-muted-foreground">{t('Querying Ministry clearance registry...')}</p>
                            </div>
                        ) : verificationResult ? (
                            <div className="space-y-3 text-xs">
                                <div className="rounded-lg bg-muted/40 p-3 space-y-1.5 font-mono text-[11px]">
                                    <div><span className="text-muted-foreground">IRN:</span> {verificationResult.irn}</div>
                                    <div>
                                        <span className="text-muted-foreground">Status:</span>{' '}
                                        <Badge variant="default" className="text-[10px]">
                                            {verificationResult.status || 'CLEARED'}
                                        </Badge>
                                    </div>
                                    <div><span className="text-muted-foreground">Gateway:</span> {verificationResult.source || 'MoR Certified Gateway'}</div>
                                    <div><span className="text-muted-foreground">Timestamp:</span> {verificationResult.verifiedAt || new Date().toISOString()}</div>
                                </div>
                                <div className="flex justify-end pt-2">
                                    <Button size="sm" onClick={() => setVerifyModalOpen(false)}>
                                        {t('Close')}
                                    </Button>
                                </div>
                            </div>
                        ) : null}
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
