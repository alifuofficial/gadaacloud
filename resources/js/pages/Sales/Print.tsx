import React, { useEffect, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import html2pdf from 'html2pdf.js';
import { formatCurrency, formatDate, getCompanySetting } from '@/utils/helpers';
import { SalesInvoice } from './types';
import { usePageButtons } from '@/hooks/usePageButtons';

interface PrintProps {
    invoice: SalesInvoice;
    [key: string]: any;
}

export default function Print() {
    const { t } = useTranslation();
    const { invoice } = usePage<PrintProps>().props;
    const [isDownloading, setIsDownloading] = useState(false);

    const signaturePrintButtons = usePageButtons('signaturePrintBtn', {
        invoice: invoice,
        invoiceType: 'sales'
    });

    useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('download') === 'pdf') {
            downloadPDF();
        }
    }, []);

    const downloadPDF = async () => {
        setIsDownloading(true);

        const printContent = document.querySelector('.invoice-container');
        if (printContent) {
            const opt = {
                margin: 0.25,
                filename: `sales-invoice-${invoice.invoice_number}.pdf`,
                image: { type: 'jpeg' as const, quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' as const }
            };

            try {
                await html2pdf().set(opt).from(printContent as HTMLElement).save();
                setTimeout(() => window.close(), 1000);
            } catch (error) {
                console.error('PDF generation failed:', error);
            }
        }

        setIsDownloading(false);
    };

    return (
        <div className="min-h-screen bg-white">
            <Head title={t('Sales Invoice')} />

            {isDownloading && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white p-6 rounded-lg shadow-lg">
                        <div className="flex items-center space-x-3">
                            <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                            <p className="text-lg font-semibold text-gray-700">{t('Generating PDF...')}</p>
                        </div>
                    </div>
                </div>
            )}

            <div className="invoice-container bg-white max-w-4xl mx-auto p-8">
                <div className="flex justify-between items-start mb-8">
                    <div className="w-1/2">
                        <h1 className="text-2xl font-bold mb-4">{getCompanySetting('company_name') || 'YOUR COMPANY'}</h1>
                        <div className="text-sm space-y-1">
                            {getCompanySetting('company_address') && <p>{getCompanySetting('company_address')}</p>}
                            {(getCompanySetting('company_city') || getCompanySetting('company_state') || getCompanySetting('company_zipcode')) && (
                                <p>
                                    {getCompanySetting('company_city')}{getCompanySetting('company_state') && `, ${getCompanySetting('company_state')}`} {getCompanySetting('company_zipcode')}
                                </p>
                            )}
                            {getCompanySetting('company_country') && <p>{getCompanySetting('company_country')}</p>}
                            {getCompanySetting('company_telephone') && <p>{t('Phone')}: {getCompanySetting('company_telephone')}</p>}
                            {getCompanySetting('company_email') && <p>{t('Email')}: {getCompanySetting('company_email')}</p>}
                            {getCompanySetting('registration_number') && <p>{t('Registration')}: {getCompanySetting('registration_number')}</p>}
                        </div>
                    </div>
                    <div className="text-right w-1/2">
                        <h2 className="text-2xl font-bold mb-2">{t('SALES INVOICE')}</h2>
                        <p className="text-lg font-semibold">#{invoice.invoice_number}</p>
                        <div className="text-sm mt-2 space-y-1">
                            <p>{t('Date')}: {formatDate(invoice.invoice_date)}</p>
                            <p>{t('Due')}: {formatDate(invoice.due_date)}</p>
                        </div>
                    </div>
                </div>

                <div className="flex justify-between mb-8">
                    <div className="w-1/2">
                        <h3 className="font-bold mb-3">{t('BILL TO')}</h3>
                        <div className="text-sm space-y-1">
                            <p className="font-semibold">{invoice.customer?.name}</p>
                            <p>{invoice.customer?.email}</p>
                            {invoice.customer_details?.tax_number && (
                                <p><span className="font-semibold">{t('TIN')}:</span> <span className="font-mono">{invoice.customer_details.tax_number}</span></p>
                            )}
                            {invoice.customer_details?.billing_address && (
                                <>
                                    <p>{invoice.customer_details.billing_address.name}</p>
                                    <p>{invoice.customer_details.billing_address.address_line_1}</p>
                                    <p>{invoice.customer_details.billing_address.city}, {invoice.customer_details.billing_address.state} {invoice.customer_details.billing_address.zip_code}</p>
                                </>
                            )}
                        </div>
                    </div>
                    <div className="text-right w-1/2">
                        <h3 className="font-bold mb-3">{t('SHIP TO')}</h3>
                        <div className="text-sm space-y-1">
                            {invoice.customer_details?.shipping_address ? (
                                <>
                                    <p className="font-semibold">{invoice.customer_details.shipping_address.name}</p>
                                    <p>{invoice.customer_details.shipping_address.address_line_1}</p>
                                    <p>{invoice.customer_details.shipping_address.city}, {invoice.customer_details.shipping_address.state} {invoice.customer_details.shipping_address.zip_code}</p>
                                </>
                            ) : (
                                <p className="text-gray-500">{t('Same as billing address')}</p>
                            )}
                        </div>
                    </div>
                </div>

                <div className="mb-8">
                    <table className="w-full table-fixed">
                        <thead>
                            <tr className="border-b border-gray-300">
                                <th className="text-left py-3 font-bold">{t('ITEM')}</th>
                                {invoice.type === 'product' && (
                                    <th className="text-center py-3 font-bold">{t('QTY')}</th>
                                )}
                                <th className="text-right py-3 font-bold">{t('PRICE')}</th>
                                <th className="text-right py-3 font-bold">{t('DISCOUNT')}</th>
                                <th className="text-right py-3 font-bold">{t('TAX')}</th>
                                <th className="text-right py-3 font-bold">{t('TOTAL')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {invoice.items?.map((item, index) => (
                                <tr key={index} className="page-break-inside-avoid">
                                    <td className="py-4">
                                        <div className="font-semibold">{item.product?.name}</div>
                                        {item.product?.sku && (
                                            <div className="text-xs text-gray-500">{t('SKU')}: {item.product.sku}</div>
                                        )}
                                    </td>
                                    {invoice.type === 'product' && (
                                        <td className="text-center py-4">{item.quantity}</td>
                                    )}
                                    <td className="text-right py-4">{formatCurrency(item.unit_price)}</td>
                                    <td className="text-right py-4">
                                        {item.discount_percentage > 0 ? (
                                            <>
                                                <div className="text-sm">{item.discount_percentage}%</div>
                                                <div className="text-sm font-medium">-{formatCurrency(item.discount_amount)}</div>
                                            </>
                                        ) : (
                                            <div className="text-sm">0%</div>
                                        )}
                                    </td>
                                    <td className="text-right py-4">
                                        {item.taxes && item.taxes.length > 0 ? (
                                            <>
                                                {item.taxes.map((tax, taxIndex) => (
                                                    <div key={taxIndex} className="text-sm">{tax.tax_name} ({tax.tax_rate}%)</div>
                                                ))}
                                                <div className="text-sm font-medium">{formatCurrency(item.tax_amount)}</div>
                                            </>
                                        ) : item.tax_percentage > 0 ? (
                                            <>
                                                <div className="text-sm">{item.tax_percentage}%</div>
                                                <div className="text-sm font-medium">{formatCurrency(item.tax_amount)}</div>
                                            </>
                                        ) : (
                                            <div className="text-sm">0%</div>
                                        )}
                                    </td>
                                    <td className="text-right py-4 font-semibold">{formatCurrency(item.total_amount)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="flex justify-between items-start gap-4 mb-4 page-break-inside-avoid">
                    {/* Left: Ethiopian MoR (EIMS Directive 1142) Fiscal Clearance Certificate Box */}
                    {invoice.eims_invoice && invoice.eims_invoice.status === 'CLEARED' ? (
                        <div className="border border-gray-400 p-3 max-w-sm rounded bg-gray-50/80 flex items-start gap-3">
                            {invoice.eims_invoice.qr_code_image && (
                                <div 
                                    className="w-24 h-24 shrink-0 bg-white p-1 border border-gray-300"
                                    dangerouslySetInnerHTML={{ __html: invoice.eims_invoice.qr_code_image }}
                                />
                            )}
                            <div className="text-[10px] space-y-0.5 leading-tight font-mono text-gray-800">
                                <div className="font-bold font-sans text-[11px] text-gray-900">
                                    ETHIOPIAN MoR (EIMS)
                                </div>
                                <div className="text-[9px] text-gray-600 font-sans">Directive No. 1142/2026 Cleared</div>
                                <div className="break-all pt-1"><span className="font-bold">IRN:</span> {invoice.eims_invoice.irn}</div>
                                {invoice.eims_invoice.rrn && <div><span className="font-bold">RRN:</span> {invoice.eims_invoice.rrn}</div>}
                                {invoice.customer_details?.tax_number && <div><span className="font-bold">Buyer TIN:</span> {invoice.customer_details.tax_number}</div>}
                                {invoice.eims_invoice.cleared_at && <div><span className="font-bold">Date:</span> {new Date(invoice.eims_invoice.cleared_at).toLocaleString()}</div>}
                            </div>
                        </div>
                    ) : invoice.eims_invoice && invoice.eims_invoice.status === 'PENDING_OFFLINE' ? (
                        <div className="border border-dashed border-amber-500 p-3 max-w-sm rounded bg-amber-50/50 text-[10px] text-amber-900 space-y-1">
                            <div className="font-bold text-[11px]">Directive 1142 Resiliency Buffer</div>
                            <p>This invoice was registered offline during network unavailability. It will be cleared with the Ministry of Revenues within the 72-hour statutory window.</p>
                        </div>
                    ) : (
                        <div />
                    )}

                    <div className="w-80 page-break-inside-avoid">
                        <div className="border border-gray-400 p-4 page-break-inside-avoid">
                            <div className="space-y-2">
                                <div className="flex justify-between">
                                    <span>{t('Subtotal')}:</span>
                                    <span>{formatCurrency(invoice.subtotal)}</span>
                                </div>
                                {invoice.discount_amount > 0 && (
                                    <div className="flex justify-between">
                                        <span>{t('Discount')}:</span>
                                        <span>-{formatCurrency(invoice.discount_amount)}</span>
                                    </div>
                                )}
                                {invoice.tax_amount > 0 && (
                                    <div className="flex justify-between">
                                        <span>{t('Tax')}:</span>
                                        <span>{formatCurrency(invoice.tax_amount)}</span>
                                    </div>
                                )}
                                <div className="border-t border-gray-400 pt-2 mt-2">
                                    <div className="flex justify-between font-bold text-lg">
                                        <span>{t('TOTAL')}:</span>
                                        <span>{formatCurrency(invoice.total_amount)}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="border-t border-gray-400 pt-4 text-center">
                     {/* Signature Print Display */}
                    {signaturePrintButtons.length > 0 && signaturePrintButtons.map((button) => (
                        <div key={button.id}>{button.component}</div>
                    ))}
                    <p className="font-semibold">{t('PAYMENT TERMS')}: {invoice.payment_terms || t('Net 30 Days')}</p>
                    <p className="text-sm mt-2">{t('Thank you for your business!')}</p>
                </div>
            </div>

            <style>{`
                body {
                    -webkit-print-color-adjust: exact;
                    color-adjust: exact;
                    font-family: Arial, sans-serif;
                }

                @page {
                    margin: 0.5in;
                    size: A4;
                }

                .invoice-container {
                    max-width: 100%;
                    margin: 0;
                    box-shadow: none;
                }

                .page-break-inside-avoid {
                    page-break-inside: avoid;
                    break-inside: avoid;
                }

                @media print {
                    body {
                        background: white;
                    }

                    .invoice-container {
                        box-shadow: none;
                    }
                }
            `}</style>
        </div>
    );
}
