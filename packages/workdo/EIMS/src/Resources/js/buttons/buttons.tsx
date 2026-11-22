import React from 'react';
import { Link } from '@inertiajs/react';
import { ShieldCheck, QrCode } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

export const zatcaQRCodeBtn = (invoice: any) => {
    if (!invoice || !invoice.id) return null;

    return {
        id: 'eims-fiscal-qr-btn',
        order: 10,
        component: (
            <div className="border border-border/80 rounded-xl p-3 bg-muted/20 flex flex-col justify-between h-full">
                <div>
                    <div className="flex items-center justify-between mb-1">
                        <span className="text-xs font-semibold text-foreground flex items-center gap-1">
                            <ShieldCheck className="h-3.5 w-3.5 text-emerald-500" />
                            Ethiopian MoR (EIMS)
                        </span>
                        <Badge variant="outline" className="text-[9px] px-1 py-0">
                            Directive 1142
                        </Badge>
                    </div>
                    <p className="text-[10px] text-muted-foreground leading-tight">
                        Continuous Transaction Control clearance certificate & IRN registration.
                    </p>
                </div>
                <div className="mt-3">
                    <Link href={`/eims/invoices`}>
                        <Button variant="outline" size="sm" className="w-full h-7 text-[11px] gap-1.5 border-primary/30 text-primary hover:bg-primary/5">
                            <QrCode className="h-3.5 w-3.5" />
                            View Fiscal Certificate
                        </Button>
                    </Link>
                </div>
            </div>
        )
    };
};

export const invoiceActionButtons = (data: any) => {
    const invoiceId = data?.invoice_id;
    if (!invoiceId) return null;

    return {
        id: `eims-invoice-action-${invoiceId}`,
        order: 5,
        component: (
            <Link href={`/eims/invoices`} title="MoR EIMS Clearance">
                <Button variant="ghost" size="sm" className="h-7 w-7 p-0 text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50">
                    <ShieldCheck className="h-4 w-4" />
                </Button>
            </Link>
        )
    };
};
