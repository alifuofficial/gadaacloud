import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    Receipt, 
    ArrowLeft, 
    ShieldCheck, 
    Copy, 
    Check, 
    Download, 
    Printer, 
    Building2, 
    User, 
    Lock, 
    Clock, 
    Code2, 
    CheckCircle2, 
    AlertTriangle,
    QrCode
} from 'lucide-react';

interface InvoiceDetailProps {
    invoice: any;
}

export default function InvoiceDetail({ invoice }: InvoiceDetailProps) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState<string | null>(null);
    const [activeTab, setActiveTab] = useState<'overview' | 'payload' | 'audit'>('overview');

    const breadcrumbs = [
        { label: t('E-Invoicing (EIMS)'), url: route('eims.dashboard') },
        { label: t('Cleared Invoices'), url: route('eims.invoices.index') },
        { label: invoice.invoice_number, url: route('eims.invoices.show', invoice.id) },
    ];

    const copyText = (text: string, label: string) => {
        navigator.clipboard.writeText(text);
        setCopied(label);
        setTimeout(() => setCopied(null), 2000);
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'ETB',
            minimumFractionDigits: 2
        }).format(amount);
    };

    const payload = invoice.eims_payload || {};
    const lineItems = payload.lineItems || [];
    const seller = payload.seller || {};
    const buyer = payload.buyer || {};
    const summary = payload.summary || {};
    const payment = payload.payment || {};

    const downloadSvg = () => {
        if (!invoice.qr_code_image) return;
        const blob = new Blob([invoice.qr_code_image], { type: 'image/svg+xml' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `EIMS-QR-${invoice.invoice_number}.svg`;
        a.click();
        URL.revokeObjectURL(url);
    };

    return (
        <AuthenticatedLayout breadcrumbs={breadcrumbs} pageTitle={t('EIMS Fiscal Clearance Certificate')}>
            <Head title={`EIMS Invoice ${invoice.invoice_number} - Directive 1142/2026`} />

            <div className="space-y-6 p-4 md:p-6">
                {/* Top Actions & Title */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-3">
                        <Link href={route('eims.invoices.index')}>
                            <Button variant="outline" size="sm" className="h-8 w-8 p-0">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold tracking-tight text-foreground">
                                    {invoice.invoice_number}
                                </h1>
                                <Badge 
                                    variant={
                                        invoice.status === 'CLEARED' ? 'default' :
                                        invoice.status === 'PENDING_OFFLINE' ? 'secondary' :
                                        invoice.status === 'CANCELLED' ? 'destructive' : 'outline'
                                    }
                                >
                                    {invoice.status}
                                </Badge>
                                <Badge variant="outline" className="font-mono text-xs">
                                    {invoice.document_type || 'INV'}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground mt-0.5">
                                {t('Directive No. 1142/2026 Clearance Registration Record')}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {invoice.qr_code_image && (
                            <Button variant="outline" size="sm" onClick={downloadSvg} className="gap-1.5">
                                <Download className="h-3.5 w-3.5" />
                                {t('Download Fiscal QR')}
                            </Button>
                        )}
                        {invoice.sales_invoice_id && (
                            <Link href={`/sales-invoices/${invoice.sales_invoice_id}`}>
                                <Button variant="outline" size="sm" className="gap-1.5">
                                    <Receipt className="h-3.5 w-3.5" />
                                    {t('View Core Sales Invoice')}
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>

                {/* Sub Navigation Tabs */}
                <div className="flex gap-2 border-b text-xs font-medium">
                    <button
                        onClick={() => setActiveTab('overview')}
                        className={`pb-2.5 px-3 border-b-2 transition-colors ${
                            activeTab === 'overview'
                                ? 'border-primary text-primary font-bold'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        {t('Clearance Certificate & Items')}
                    </button>
                    <button
                        onClick={() => setActiveTab('payload')}
                        className={`pb-2.5 px-3 border-b-2 transition-colors ${
                            activeTab === 'payload'
                                ? 'border-primary text-primary font-bold'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        {t('MoR Schema Payload (JSON)')}
                    </button>
                    <button
                        onClick={() => setActiveTab('audit')}
                        className={`pb-2.5 px-3 border-b-2 transition-colors ${
                            activeTab === 'audit'
                                ? 'border-primary text-primary font-bold'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        {t('INSA Audit Trail ({{count}})', { count: invoice.audit_logs?.length || 0 })}
                    </button>
                </div>

                {activeTab === 'overview' && (
                    <div className="space-y-6">
                        {/* QR Code & Clearance Badge Section */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            {/* QR Code Preview */}
                            <Card className="shadow-sm md:col-span-1 flex flex-col items-center justify-center p-6 text-center">
                                <div className="border border-border rounded-xl p-3 bg-white dark:bg-slate-900 shadow-inner max-w-[220px]">
                                    {invoice.qr_code_image ? (
                                        <div 
                                            className="w-48 h-48 flex items-center justify-center"
                                            dangerouslySetInnerHTML={{ __html: invoice.qr_code_image }} 
                                        />
                                    ) : (
                                        <div className="w-48 h-48 flex flex-col items-center justify-center text-muted-foreground bg-muted/40 rounded">
                                            <QrCode className="h-12 w-12 mb-2 text-muted-foreground/40" />
                                            <span className="text-xs">{t('QR Unavailable')}</span>
                                        </div>
                                    )}
                                </div>
                                <div className="mt-4 space-y-1">
                                    <div className="flex items-center justify-center gap-1.5 text-xs font-semibold text-foreground">
                                        <ShieldCheck className="h-4 w-4 text-emerald-500" />
                                        {t('Ministry of Revenues Verified')}
                                    </div>
                                    <p className="text-[11px] text-muted-foreground max-w-[240px]">
                                        {t('Directive 1142/2026 Fiscal Clearance QR Code. Scannable on all official inspection devices.')}
                                    </p>
                                </div>
                            </Card>

                            {/* Cryptographic Clearance Details */}
                            <Card className="shadow-sm md:col-span-2">
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm font-bold flex items-center gap-2">
                                        <Lock className="h-4 w-4 text-primary" />
                                        {t('MoR Cryptographic Clearance Identifiers')}
                                    </CardTitle>
                                    <CardDescription className="text-xs">
                                        {t('Unique government registration credentials generated during Continuous Transaction Control.')}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3 text-xs">
                                    <div className="rounded-lg bg-muted/40 p-3 space-y-2 font-mono">
                                        <div>
                                            <span className="text-muted-foreground block text-[10px] uppercase font-sans">
                                                {t('Invoice Registration Number (IRN Hash):')}
                                            </span>
                                            <div className="flex items-center justify-between text-[11px] text-foreground font-semibold break-all pt-0.5">
                                                <span>{invoice.irn || '—'}</span>
                                                {invoice.irn && (
                                                    <button
                                                        onClick={() => copyText(invoice.irn, 'irn')}
                                                        className="ml-2 shrink-0 p-1 hover:text-primary"
                                                        title={t('Copy')}
                                                    >
                                                        {copied === 'irn' ? <Check className="h-3.5 w-3.5 text-emerald-500" /> : <Copy className="h-3.5 w-3.5" />}
                                                    </button>
                                                )}
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2 border-t border-border/50">
                                            <div>
                                                <span className="text-muted-foreground block text-[10px] uppercase font-sans">
                                                    {t('Receipt Number (RRN):')}
                                                </span>
                                                <span className="text-[11px] text-foreground font-semibold">
                                                    {invoice.rrn || '—'}
                                                </span>
                                            </div>
                                            <div>
                                                <span className="text-muted-foreground block text-[10px] uppercase font-sans">
                                                    {t('Clearance Timestamp (EAT):')}
                                                </span>
                                                <span className="text-[11px] text-foreground font-semibold">
                                                    {invoice.cleared_at ? new Date(invoice.cleared_at).toLocaleString() : '—'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {invoice.status === 'CANCELLED' && (
                                        <div className="rounded-lg border border-red-500/20 bg-red-500/10 p-3 space-y-1 text-red-700 dark:text-red-300">
                                            <div className="font-semibold flex items-center gap-1.5">
                                                <AlertTriangle className="h-4 w-4" />
                                                {t('Cancellation Record (Reason Code {{code}})', { code: invoice.cancel_reason_code })}
                                            </div>
                                            <p className="text-[11px]">
                                                {invoice.cancel_reason_text}: {invoice.cancel_remark || t('No remark provided')}
                                            </p>
                                        </div>
                                    )}

                                    {invoice.status === 'PENDING_OFFLINE' && invoice.offline_queue && (
                                        <div className="rounded-lg border border-amber-500/20 bg-amber-500/10 p-3 space-y-1 text-amber-700 dark:text-amber-300">
                                            <div className="font-semibold flex items-center gap-1.5">
                                                <Clock className="h-4 w-4" />
                                                {t('72-Hour Offline Resiliency Buffer Active')}
                                            </div>
                                            <p className="text-[11px]">
                                                {t('Expires in {{hours}} hours. This transaction is queued for automatic transmission once connection is restored.', {
                                                    hours: invoice.offline_queue.hours_remaining
                                                })}
                                            </p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        {/* Taxpayer & Buyer Cards */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {/* Seller / Supplier */}
                            <Card className="shadow-sm">
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm font-semibold flex items-center gap-2">
                                        <Building2 className="h-4 w-4 text-muted-foreground" />
                                        {t('Seller / Taxpayer Information')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-1.5 text-xs">
                                    <div className="flex justify-between py-1 border-b">
                                        <span className="text-muted-foreground">{t('Legal Name:')}</span>
                                        <span className="font-medium text-foreground">{seller.legalName || '—'}</span>
                                    </div>
                                    <div className="flex justify-between py-1 border-b">
                                        <span className="text-muted-foreground">{t('Trade Name:')}</span>
                                        <span className="font-medium text-foreground">{seller.tradeName || '—'}</span>
                                    </div>
                                    <div className="flex justify-between py-1 border-b">
                                        <span className="text-muted-foreground">{t('Taxpayer TIN:')}</span>
                                        <span className="font-mono font-medium text-foreground">{seller.tin || '—'}</span>
                                    </div>
                                    <div className="flex justify-between py-1 border-b">
                                        <span className="text-muted-foreground">{t('VAT Registration #:')}</span>
                                        <span className="font-mono font-medium text-foreground">{seller.vatNumber || '—'}</span>
                                    </div>
                                    <div className="flex justify-between py-1">
                                        <span className="text-muted-foreground">{t('Fiscal Branch / POS ID:')}</span>
                                        <span className="font-medium text-foreground">{seller.branchCode || 'MAIN'} / {seller.deviceId || 'POS-01'}</span>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Buyer */}
                            <Card className="shadow-sm">
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm font-semibold flex items-center gap-2">
                                        <User className="h-4 w-4 text-muted-foreground" />
                                        {t('Buyer Classification')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-1.5 text-xs">
                                    <div className="flex justify-between py-1 border-b">
                                        <span className="text-muted-foreground">{t('Buyer Type:')}</span>
                                        <Badge variant="secondary" className="font-mono text-[10px]">
                                            {buyer.buyerType || invoice.buyer_type || 'B2C'}
                                        </Badge>
                                    </div>
                                    <div className="flex justify-between py-1 border-b">
                                        <span className="text-muted-foreground">{t('Customer Name:')}</span>
                                        <span className="font-medium text-foreground">{buyer.legalName || invoice.buyer_name || 'Consumer'}</span>
                                    </div>
                                    <div className="flex justify-between py-1 border-b">
                                        <span className="text-muted-foreground">{t('Buyer TIN:')}</span>
                                        <span className="font-mono font-medium text-foreground">{buyer.tin || invoice.buyer_tin || '—'}</span>
                                    </div>
                                    <div className="flex justify-between py-1 border-b">
                                        <span className="text-muted-foreground">{t('Buyer VAT Number:')}</span>
                                        <span className="font-mono font-medium text-foreground">{buyer.vatNumber || '—'}</span>
                                    </div>
                                    <div className="flex justify-between py-1">
                                        <span className="text-muted-foreground">{t('Consumer ID:')}</span>
                                        <span className="font-medium text-foreground">
                                            {buyer.idType ? `${buyer.idType}: ${buyer.idNumber}` : '—'}
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Line Items Table */}
                        <Card className="shadow-sm">
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm font-semibold">{t('Directive 1142 Line Item Supplies')}</CardTitle>
                            </CardHeader>
                            <CardContent className="p-0">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead>
                                            <tr className="border-b bg-muted/30 font-semibold text-muted-foreground">
                                                <th className="p-3 pl-4">#</th>
                                                <th className="p-3">{t('SKU / Code')}</th>
                                                <th className="p-3">{t('Description')}</th>
                                                <th className="p-3">{t('Nature')}</th>
                                                <th className="p-3 text-right">{t('Qty')}</th>
                                                <th className="p-3 text-right">{t('Unit Price')}</th>
                                                <th className="p-3 text-right">{t('Pre-Tax')}</th>
                                                <th className="p-3 text-right">{t('Discount')}</th>
                                                <th className="p-3 text-right">{t('VAT 15%')}</th>
                                                <th className="p-3 pr-4 text-right">{t('Line Total')}</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {lineItems.map((item: any, idx: number) => (
                                                <tr key={idx} className="hover:bg-muted/40">
                                                    <td className="p-3 pl-4 font-mono text-muted-foreground">{item.lineNumber || idx + 1}</td>
                                                    <td className="p-3 font-mono">{item.itemCode || '—'}</td>
                                                    <td className="p-3 font-medium text-foreground">{item.productDescription}</td>
                                                    <td className="p-3">
                                                        <Badge variant="outline" className="text-[10px] uppercase font-mono">
                                                            {item.natureOfSupplies}
                                                        </Badge>
                                                    </td>
                                                    <td className="p-3 text-right font-medium">{item.quantity} {item.unitOfMeasure}</td>
                                                    <td className="p-3 text-right">{formatCurrency(item.unitPrice)}</td>
                                                    <td className="p-3 text-right">{formatCurrency(item.preTaxValue)}</td>
                                                    <td className="p-3 text-right text-muted-foreground">{formatCurrency(item.discount || 0)}</td>
                                                    <td className="p-3 text-right text-emerald-600 dark:text-emerald-400 font-medium">
                                                        {formatCurrency(item.taxAmount)}
                                                    </td>
                                                    <td className="p-3 pr-4 text-right font-bold text-foreground">
                                                        {formatCurrency(item.totalLineAmount)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                {/* Financial Summary Bottom Panel */}
                                <div className="border-t p-4 flex flex-col md:flex-row justify-between gap-6 bg-muted/10 text-xs">
                                    <div className="space-y-1.5 text-muted-foreground">
                                        <div><span className="font-semibold text-foreground">{t('Payment Mode:')}</span> {payment.mode || invoice.payment_mode || 'CASH'}</div>
                                        <div><span className="font-semibold text-foreground">{t('Payment Term:')}</span> {payment.paymentTerm || invoice.payment_term || 'IMMIDIATE'}</div>
                                        <div><span className="font-semibold text-foreground">{t('Currency:')}</span> {summary.currency || 'ETB'}</div>
                                    </div>

                                    <div className="w-full md:w-64 space-y-1.5">
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">{t('Pre-Tax Value:')}</span>
                                            <span>{formatCurrency(Number(summary.preTaxAmount || invoice.pre_tax_amount))}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">{t('Discount:')}</span>
                                            <span>-{formatCurrency(Number(summary.discountAmount || invoice.discount_amount))}</span>
                                        </div>
                                        <div className="flex justify-between text-emerald-600 dark:text-emerald-400 font-medium">
                                            <span>{t('VAT (15%):')}</span>
                                            <span>+{formatCurrency(Number(summary.taxAmount || invoice.tax_amount))}</span>
                                        </div>
                                        <div className="flex justify-between border-t pt-1.5 text-sm font-bold text-foreground">
                                            <span>{t('Total Cleared:')}</span>
                                            <span>{formatCurrency(Number(summary.totalAmount || invoice.total_amount))}</span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {activeTab === 'payload' && (
                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle className="text-sm font-bold flex items-center gap-2">
                                    <Code2 className="h-4 w-4 text-primary" />
                                    {t('Directive 1142/2026 Transmission Payload')}
                                </CardTitle>
                                <CardDescription className="text-xs">
                                    {t('Canonical JSON structure transmitted to the Ministry of Revenues gateway')}
                                </CardDescription>
                            </div>
                            <Button 
                                variant="outline" 
                                size="sm" 
                                onClick={() => copyText(JSON.stringify(payload, null, 2), 'payloadJson')}
                            >
                                {copied === 'payloadJson' ? <Check className="h-3.5 w-3.5 mr-1 text-emerald-500" /> : <Copy className="h-3.5 w-3.5 mr-1" />}
                                {t('Copy JSON')}
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <pre className="p-4 rounded-xl bg-slate-950 text-slate-100 font-mono text-[11px] overflow-x-auto max-h-[500px]">
                                {JSON.stringify(payload, null, 2)}
                            </pre>
                        </CardContent>
                    </Card>
                )}

                {activeTab === 'audit' && (
                    <Card className="shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-sm font-bold flex items-center gap-2">
                                <Lock className="h-4 w-4 text-primary" />
                                {t('INSA Cryptographic Audit Trail')}
                            </CardTitle>
                            <CardDescription className="text-xs">
                                {t('Information Network Security Administration tamper-evident signature log chain')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            {(!invoice.audit_logs || invoice.audit_logs.length === 0) ? (
                                <div className="p-6 text-center text-xs text-muted-foreground">
                                    {t('No specific audit log entries found for this invoice.')}
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead>
                                            <tr className="border-b bg-muted/30 font-semibold text-muted-foreground">
                                                <th className="p-3 pl-4">{t('Timestamp')}</th>
                                                <th className="p-3">{t('Action')}</th>
                                                <th className="p-3">{t('User')}</th>
                                                <th className="p-3">{t('Payload Hash')}</th>
                                                <th className="p-3 pr-4">{t('HMAC Signature')}</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y font-mono text-[11px]">
                                            {invoice.audit_logs.map((log: any) => (
                                                <tr key={log.id} className="hover:bg-muted/40">
                                                    <td className="p-3 pl-4 font-sans text-muted-foreground">
                                                        {new Date(log.created_at).toLocaleString()}
                                                    </td>
                                                    <td className="p-3">
                                                        <Badge variant="outline" className="font-sans text-[10px]">
                                                            {log.action}
                                                        </Badge>
                                                    </td>
                                                    <td className="p-3 font-sans">
                                                        {log.user?.name || 'System / Auto'}
                                                    </td>
                                                    <td className="p-3 text-muted-foreground">
                                                        {log.payload_hash.slice(0, 16)}...
                                                    </td>
                                                    <td className="p-3 pr-4 text-emerald-600 dark:text-emerald-400">
                                                        {log.signature.slice(0, 20)}...
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
