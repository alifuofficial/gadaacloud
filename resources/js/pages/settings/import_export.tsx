import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { CheckCircle2, FileSpreadsheet, Anchor, Coins, Landmark, Calendar, Truck, ArrowUpDown } from 'lucide-react';

interface PurchaseOrder {
    id: number;
    invoice_number: string;
    total_amount: number;
    vendor_id: number;
}

interface Vendor {
    id: number;
    name: string;
    email: string;
}

interface LCRecord {
    id: number;
    lc_number: string;
    purchase_order_id?: number;
    purchase_order?: PurchaseOrder;
    vendor_id: number;
    vendor?: Vendor;
    issuing_bank: string;
    advising_bank?: string;
    amount: number;
    currency: string;
    exchange_rate: number;
    tolerance_percent: number;
    payment_terms?: string;
    latest_shipment_date?: string;
    expiry_date: string;
    status: 'draft' | 'open' | 'utilized' | 'closed';
}

interface ShipmentRecord {
    id: number;
    lc_id?: number;
    lc?: LCRecord;
    shipping_line: string;
    vessel_name: string;
    voyage_number?: string;
    container_numbers?: string[];
    bill_of_lading: string;
    etd?: string;
    eta?: string;
    atd?: string;
    ata?: string;
    status: 'on_port' | 'in_transit' | 'customs_clearance' | 'delivered';
}

interface LandedCostRecord {
    id: number;
    lc_id: number;
    lc?: LCRecord;
    freight_charges: number;
    insurance_fees: number;
    custom_duties: number;
    agent_fees: number;
    bank_fees: number;
    currency: string;
    allocation_method: 'value' | 'quantity';
    is_posted_to_accounts: boolean;
    journal_entry_id?: number;
    created_at: string;
}

interface AccountItem {
    id: number;
    account_name: string;
    account_code: string;
}

interface ImportExportProps {
    lcs: LCRecord[];
    shipments: ShipmentRecord[];
    landedCosts: LandedCostRecord[];
    purchaseOrders: PurchaseOrder[];
    vendors: Vendor[];
    accounts: AccountItem[];
}

export default function ImportExportDashboard({ lcs, shipments, landedCosts, purchaseOrders, vendors, accounts }: ImportExportProps) {
    const { t } = useTranslation();
    const [isLoading, setIsLoading] = useState(false);

    // Dialog state toggles
    const [isLcModalOpen, setIsLcModalOpen] = useState(false);
    const [isShipmentModalOpen, setIsShipmentModalOpen] = useState(false);
    const [isLcostModalOpen, setIsLcostModalOpen] = useState(false);

    // LC Form State
    const [lcForm, setLcForm] = useState({
        lc_number: '',
        purchase_order_id: '',
        vendor_id: '',
        issuing_bank: '',
        advising_bank: '',
        amount: '',
        currency: 'USD',
        exchange_rate: '115.500000',
        tolerance_percent: '0',
        payment_terms: 'Sight LC',
        latest_shipment_date: '',
        expiry_date: '',
    });

    // Shipment Form State
    const [shipmentForm, setShipmentForm] = useState({
        lc_id: '',
        shipping_line: '',
        vessel_name: '',
        voyage_number: '',
        container_numbers: '',
        bill_of_lading: '',
        etd: '',
        eta: '',
    });

    // Landed Cost Form State
    const [lcostForm, setLcostForm] = useState({
        lc_id: '',
        freight_charges: '0',
        insurance_fees: '0',
        custom_duties: '0',
        agent_fees: '0',
        bank_fees: '0',
        currency: 'ETB',
        allocation_method: 'value',
    });

    // Handle LC Save
    const handleSaveLc = (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        router.post(route('settings.import-export.lc.store'), lcForm, {
            onSuccess: () => {
                setIsLoading(false);
                setIsLcModalOpen(false);
                setLcForm({
                    lc_number: '', purchase_order_id: '', vendor_id: '', issuing_bank: '', advising_bank: '',
                    amount: '', currency: 'USD', exchange_rate: '115.500000', tolerance_percent: '0',
                    payment_terms: 'Sight LC', latest_shipment_date: '', expiry_date: ''
                });
            },
            onFinish: () => setIsLoading(false)
        });
    };

    // Handle Shipment Save
    const handleSaveShipment = (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        router.post(route('settings.import-export.shipment.store'), shipmentForm, {
            onSuccess: () => {
                setIsLoading(false);
                setIsShipmentModalOpen(false);
                setShipmentForm({
                    lc_id: '', shipping_line: '', vessel_name: '', voyage_number: '',
                    container_numbers: '', bill_of_lading: '', etd: '', eta: ''
                });
            },
            onFinish: () => setIsLoading(false)
        });
    };

    // Handle Landed Cost Save
    const handleSaveLcost = (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        router.post(route('settings.import-export.landed-cost.store'), lcostForm, {
            onSuccess: () => {
                setIsLoading(false);
                setIsLcostModalOpen(false);
                setLcostForm({
                    lc_id: '', freight_charges: '0', insurance_fees: '0', custom_duties: '0',
                    agent_fees: '0', bank_fees: '0', currency: 'ETB', allocation_method: 'value'
                });
            },
            onFinish: () => setIsLoading(false)
        });
    };

    // Update shipment logistics status
    const handleUpdateStatus = (id: number, status: string) => {
        if (!confirm(t('Change shipment status to ') + t(status) + '?')) return;
        router.post(route('settings.import-export.shipment.status', { id }), { status });
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Settings'), url: route('settings.index') },
                { label: t('Import & Export Operations') }
            ]}
            pageTitle={t('Import & Export Operations')}
        >
            <Head title={t('Import & Export Operations')} />

            <div className="space-y-8 max-w-6xl mx-auto">
                <Tabs defaultValue="lcs" className="w-full">
                    <TabsList className="grid w-full grid-cols-3 max-w-md">
                        <TabsTrigger value="lcs">{t('Letters of Credit')}</TabsTrigger>
                        <TabsTrigger value="shipments">{t('Shipment & Logistics')}</TabsTrigger>
                        <TabsTrigger value="landed_costs">{t('Landed Cost allocation')}</TabsTrigger>
                    </TabsList>

                    {/* Tab 1: Letter of Credit (LC) */}
                    <TabsContent value="lcs" className="space-y-6 mt-6">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle>{t('Letter of Credit (LC) Directory')}</CardTitle>
                                    <CardDescription>{t('Manage trade finance credits and bank advising documents.')}</CardDescription>
                                </div>
                                <Dialog open={isLcModalOpen} onOpenChange={setIsLcModalOpen}>
                                    <DialogTrigger asChild>
                                        <Button size="sm">
                                            <Landmark className="h-4 w-4 mr-2" />
                                            {t('Open New LC')}
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="max-w-lg">
                                        <DialogHeader>
                                            <DialogTitle>{t('Open Letter of Credit (LC)')}</DialogTitle>
                                        </DialogHeader>
                                        <form onSubmit={handleSaveLc} className="space-y-4">
                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <Label htmlFor="lc_number">{t('LC Reference Number')}</Label>
                                                    <Input
                                                        id="lc_number"
                                                        value={lcForm.lc_number}
                                                        onChange={(e) => setLcForm({ ...lcForm, lc_number: e.target.value })}
                                                        placeholder="e.g. LC-2026-0001"
                                                        required
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="purchase_order">{t('Link Purchase Order')}</Label>
                                                    <Select value={lcForm.purchase_order_id} onValueChange={(val) => setLcForm({ ...lcForm, purchase_order_id: val })}>
                                                        <SelectTrigger id="purchase_order">
                                                            <SelectValue placeholder={t('Select PO')} />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {purchaseOrders.map((po) => (
                                                                <SelectItem key={po.id} value={po.id.toString()}>
                                                                    {po.invoice_number} (${po.total_amount})
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <Label htmlFor="vendor">{t('Beneficiary Vendor')}</Label>
                                                    <Select value={lcForm.vendor_id} onValueChange={(val) => setLcForm({ ...lcForm, vendor_id: val })}>
                                                        <SelectTrigger id="vendor">
                                                            <SelectValue placeholder={t('Select Supplier')} />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {vendors.map((vendor) => (
                                                                <SelectItem key={vendor.id} value={vendor.id.toString()}>
                                                                    {vendor.name}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div>
                                                    <Label htmlFor="issuing_bank">{t('Issuing Bank')}</Label>
                                                    <Input
                                                        id="issuing_bank"
                                                        value={lcForm.issuing_bank}
                                                        onChange={(e) => setLcForm({ ...lcForm, issuing_bank: e.target.value })}
                                                        placeholder="Commercial Bank of Ethiopia"
                                                        required
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-3 gap-4">
                                                <div className="col-span-2">
                                                    <Label htmlFor="amount">{t('LC Amount')}</Label>
                                                    <Input
                                                        id="amount"
                                                        type="number"
                                                        step="0.01"
                                                        value={lcForm.amount}
                                                        onChange={(e) => setLcForm({ ...lcForm, amount: e.target.value })}
                                                        placeholder="100000.00"
                                                        required
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="currency">{t('Currency')}</Label>
                                                    <Select value={lcForm.currency} onValueChange={(val) => setLcForm({ ...lcForm, currency: val })}>
                                                        <SelectTrigger id="currency">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="USD">USD</SelectItem>
                                                            <SelectItem value="EUR">EUR</SelectItem>
                                                            <SelectItem value="ETB">ETB</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <Label htmlFor="exchange_rate">{t('Exchange Rate')}</Label>
                                                    <Input
                                                        id="exchange_rate"
                                                        type="number"
                                                        step="0.000001"
                                                        value={lcForm.exchange_rate}
                                                        onChange={(e) => setLcForm({ ...lcForm, exchange_rate: e.target.value })}
                                                        required
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="tolerance">{t('Tolerance (%)')}</Label>
                                                    <Input
                                                        id="tolerance"
                                                        type="number"
                                                        value={lcForm.tolerance_percent}
                                                        onChange={(e) => setLcForm({ ...lcForm, tolerance_percent: e.target.value })}
                                                        required
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <Label htmlFor="ship_date">{t('Latest Shipment Date')}</Label>
                                                    <Input
                                                        id="ship_date"
                                                        type="date"
                                                        value={lcForm.latest_shipment_date}
                                                        onChange={(e) => setLcForm({ ...lcForm, latest_shipment_date: e.target.value })}
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="expiry_date">{t('Expiry Date')}</Label>
                                                    <Input
                                                        id="expiry_date"
                                                        type="date"
                                                        value={lcForm.expiry_date}
                                                        onChange={(e) => setLcForm({ ...lcForm, expiry_date: e.target.value })}
                                                        required
                                                    />
                                                </div>
                                            </div>

                                            <div className="flex justify-end gap-2 pt-4">
                                                <Button type="button" variant="outline" onClick={() => setIsLcModalOpen(false)}>
                                                    {t('Cancel')}
                                                </Button>
                                                <Button type="submit" disabled={isLoading}>
                                                    {isLoading ? t('Saving...') : t('Open LC')}
                                                </Button>
                                            </div>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('LC Number')}</TableHead>
                                            <TableHead>{t('Bank Details')}</TableHead>
                                            <TableHead>{t('Vendor')}</TableHead>
                                            <TableHead>{t('LC Value (FX)')}</TableHead>
                                            <TableHead>{t('Exchange Rate')}</TableHead>
                                            <TableHead>{t('Expiry Date')}</TableHead>
                                            <TableHead>{t('Status')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {lcs.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={7} className="text-center text-muted-foreground py-6">
                                                    {t('No Letters of Credit opened.')}
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            lcs.map((lc) => (
                                                <TableRow key={lc.id}>
                                                    <TableCell className="font-bold">{lc.lc_number}</TableCell>
                                                    <TableCell>
                                                        <p className="font-medium">{lc.issuing_bank}</p>
                                                        {lc.advising_bank && <p className="text-xs text-muted-foreground">{t('Advising: ') + lc.advising_bank}</p>}
                                                    </TableCell>
                                                    <TableCell>{lc.vendor?.name || t('N/A')}</TableCell>
                                                    <TableCell className="font-bold text-emerald-700">
                                                        {lc.amount} {lc.currency}
                                                    </TableCell>
                                                    <TableCell>{lc.exchange_rate}</TableCell>
                                                    <TableCell>{new Date(lc.expiry_date).toLocaleDateString()}</TableCell>
                                                    <TableCell>
                                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800">
                                                            {lc.status.toUpperCase()}
                                                        </span>
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Tab 2: Shipment & Logistics */}
                    <TabsContent value="shipments" className="space-y-6 mt-6">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle>{t('Shipment & Consignment Tracker')}</CardTitle>
                                    <CardDescription>{t('Monitor shipping lines, voyage timelines, and container cargos.')}</CardDescription>
                                </div>
                                <Dialog open={isShipmentModalOpen} onOpenChange={setIsShipmentModalOpen}>
                                    <DialogTrigger asChild>
                                        <Button size="sm">
                                            <Anchor className="h-4 w-4 mr-2" />
                                            {t('Record Shipment')}
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="max-w-lg">
                                        <DialogHeader>
                                            <DialogTitle>{t('Record Shipment Logistics')}</DialogTitle>
                                        </DialogHeader>
                                        <form onSubmit={handleSaveShipment} className="space-y-4">
                                            <div>
                                                <Label htmlFor="shipment_lc">{t('Link to Letter of Credit')}</Label>
                                                <Select value={shipmentForm.lc_id} onValueChange={(val) => setShipmentForm({ ...shipmentForm, lc_id: val })}>
                                                    <SelectTrigger id="shipment_lc">
                                                        <SelectValue placeholder={t('Select LC')} />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {lcs.map((lc) => (
                                                            <SelectItem key={lc.id} value={lc.id.toString()}>
                                                                {lc.lc_number} ({lc.issuing_bank})
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <Label htmlFor="shipping_line">{t('Shipping Line')}</Label>
                                                    <Input
                                                        id="shipping_line"
                                                        value={shipmentForm.shipping_line}
                                                        onChange={(e) => setShipmentForm({ ...shipmentForm, shipping_line: e.target.value })}
                                                        placeholder="Maersk Line"
                                                        required
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="vessel_name">{t('Vessel / Flight Name')}</Label>
                                                    <Input
                                                        id="vessel_name"
                                                        value={shipmentForm.vessel_name}
                                                        onChange={(e) => setShipmentForm({ ...shipmentForm, vessel_name: e.target.value })}
                                                        placeholder="e.g. Mary Maersk"
                                                        required
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <Label htmlFor="voyage">{t('Voyage Number')}</Label>
                                                    <Input
                                                        id="voyage"
                                                        value={shipmentForm.voyage_number}
                                                        onChange={(e) => setShipmentForm({ ...shipmentForm, voyage_number: e.target.value })}
                                                        placeholder="V-2601"
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="bl">{t('Bill of Lading / Airway Bill')}</Label>
                                                    <Input
                                                        id="bl"
                                                        value={shipmentForm.bill_of_lading}
                                                        onChange={(e) => setShipmentForm({ ...shipmentForm, bill_of_lading: e.target.value })}
                                                        placeholder="Msk1234567890"
                                                        required
                                                    />
                                                </div>
                                            </div>

                                            <div>
                                                <Label htmlFor="containers">{t('Container Numbers (Comma separated)')}</Label>
                                                <Input
                                                    id="containers"
                                                    value={shipmentForm.container_numbers}
                                                    onChange={(e) => setShipmentForm({ ...shipmentForm, container_numbers: e.target.value })}
                                                    placeholder="MSKU1234567, MSKU9876543"
                                                />
                                            </div>

                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <Label htmlFor="etd">{t('Estimated Departure (ETD)')}</Label>
                                                    <Input
                                                        id="etd"
                                                        type="date"
                                                        value={shipmentForm.etd}
                                                        onChange={(e) => setShipmentForm({ ...shipmentForm, etd: e.target.value })}
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="eta">{t('Estimated Arrival (ETA)')}</Label>
                                                    <Input
                                                        id="eta"
                                                        type="date"
                                                        value={shipmentForm.eta}
                                                        onChange={(e) => setShipmentForm({ ...shipmentForm, eta: e.target.value })}
                                                    />
                                                </div>
                                            </div>

                                            <div className="flex justify-end gap-2 pt-4">
                                                <Button type="button" variant="outline" onClick={() => setIsShipmentModalOpen(false)}>
                                                    {t('Cancel')}
                                                </Button>
                                                <Button type="submit" disabled={isLoading}>
                                                    {isLoading ? t('Saving...') : t('Record Shipment')}
                                                </Button>
                                            </div>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('Bill of Lading')}</TableHead>
                                            <TableHead>{t('Shipping Vessel')}</TableHead>
                                            <TableHead>{t('LC Reference')}</TableHead>
                                            <TableHead>{t('Voyage Timeline')}</TableHead>
                                            <TableHead>{t('Status')}</TableHead>
                                            <TableHead>{t('Actions')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {shipments.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={6} className="text-center text-muted-foreground py-6">
                                                    {t('No active shipments recorded.')}
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            shipments.map((record) => (
                                                <TableRow key={record.id}>
                                                    <TableCell className="font-mono font-medium">{record.bill_of_lading}</TableCell>
                                                    <TableCell>
                                                        <p className="font-semibold">{record.vessel_name}</p>
                                                        <p className="text-xs text-muted-foreground">{t('Carrier: ') + record.shipping_line}</p>
                                                    </TableCell>
                                                    <TableCell className="font-bold text-blue-700">{record.lc?.lc_number || t('Direct Trade')}</TableCell>
                                                    <TableCell>
                                                        <p className="text-xs"><strong>{t('ETD:')}</strong> {record.etd ? new Date(record.etd).toLocaleDateString() : t('N/A')}</p>
                                                        <p className="text-xs"><strong>{t('ETA:')}</strong> {record.eta ? new Date(record.eta).toLocaleDateString() : t('N/A')}</p>
                                                    </TableCell>
                                                    <TableCell>
                                                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                                                            record.status === 'delivered' ? 'bg-emerald-100 text-emerald-800' :
                                                            record.status === 'customs_clearance' ? 'bg-purple-100 text-purple-800' : 'bg-amber-100 text-amber-800'
                                                        }`}>
                                                            {record.status.replace('_', ' ').toUpperCase()}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Select value={record.status} onValueChange={(val) => handleUpdateStatus(record.id, val)}>
                                                            <SelectTrigger className="h-8 w-32">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="on_port">{t('On Port')}</SelectItem>
                                                                <SelectItem value="in_transit">{t('In Transit')}</SelectItem>
                                                                <SelectItem value="customs_clearance">{t('Customs Clearance')}</SelectItem>
                                                                <SelectItem value="delivered">{t('Delivered')}</SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Tab 3: Landed Cost Allocation */}
                    <TabsContent value="landed_costs" className="space-y-6 mt-6">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle>{t('Landed Cost Allocation Ledger')}</CardTitle>
                                    <CardDescription>{t('Allocate shipping, customs, and banks charges to capitalize asset values.')}</CardDescription>
                                </div>
                                <Dialog open={isLcostModalOpen} onOpenChange={setIsLcostModalOpen}>
                                    <DialogTrigger asChild>
                                        <Button size="sm">
                                            <Coins className="h-4 w-4 mr-2" />
                                            {t('Allocate Landed Cost')}
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="max-w-lg">
                                        <DialogHeader>
                                            <DialogTitle>{t('Allocate Landed Cost charges')}</DialogTitle>
                                        </DialogHeader>
                                        <form onSubmit={handleSaveLcost} className="space-y-4">
                                            <div>
                                                <Label htmlFor="lcost_lc">{t('Link to Letter of Credit')}</Label>
                                                <Select value={lcostForm.lc_id} onValueChange={(val) => setLcostForm({ ...lcostForm, lc_id: val })}>
                                                    <SelectTrigger id="lcost_lc">
                                                        <SelectValue placeholder={t('Select LC')} />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {lcs.map((lc) => (
                                                            <SelectItem key={lc.id} value={lc.id.toString()}>
                                                                {lc.lc_number}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <Label htmlFor="freight">{t('Freight Charges')}</Label>
                                                    <Input
                                                        id="freight"
                                                        type="number"
                                                        value={lcostForm.freight_charges}
                                                        onChange={(e) => setLcostForm({ ...lcostForm, freight_charges: e.target.value })}
                                                        required
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="insurance">{t('Marine Insurance Fees')}</Label>
                                                    <Input
                                                        id="insurance"
                                                        type="number"
                                                        value={lcostForm.insurance_fees}
                                                        onChange={(e) => setLcostForm({ ...lcostForm, insurance_fees: e.target.value })}
                                                        required
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-3 gap-4">
                                                <div className="col-span-2">
                                                    <Label htmlFor="duties">{t('Custom Duties / Tariffs')}</Label>
                                                    <Input
                                                        id="duties"
                                                        type="number"
                                                        value={lcostForm.custom_duties}
                                                        onChange={(e) => setLcostForm({ ...lcostForm, custom_duties: e.target.value })}
                                                        required
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="currency_lcost">{t('Currency')}</Label>
                                                    <Select value={lcostForm.currency} onValueChange={(val) => setLcostForm({ ...lcostForm, currency: val })}>
                                                        <SelectTrigger id="currency_lcost">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="ETB">ETB</SelectItem>
                                                            <SelectItem value="USD">USD</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <Label htmlFor="agent_fees">{t('Clearing Agent Fees')}</Label>
                                                    <Input
                                                        id="agent_fees"
                                                        type="number"
                                                        value={lcostForm.agent_fees}
                                                        onChange={(e) => setLcostForm({ ...lcostForm, agent_fees: e.target.value })}
                                                        required
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="bank_fees">{t('Bank Commissions')}</Label>
                                                    <Input
                                                        id="bank_fees"
                                                        type="number"
                                                        value={lcostForm.bank_fees}
                                                        onChange={(e) => setLcostForm({ ...lcostForm, bank_fees: e.target.value })}
                                                        required
                                                    />
                                                </div>
                                            </div>

                                            <div>
                                                <Label htmlFor="allocation_method">{t('Allocation Method')}</Label>
                                                <Select value={lcostForm.allocation_method} onValueChange={(val) => setLcostForm({ ...lcostForm, allocation_method: val })}>
                                                    <SelectTrigger id="allocation_method">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="value">{t('Value-based (Prorate by item price)')}</SelectItem>
                                                        <SelectItem value="quantity">{t('Quantity-based (Prorate by item counts)')}</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="flex justify-end gap-2 pt-4">
                                                <Button type="button" variant="outline" onClick={() => setIsLcostModalOpen(false)}>
                                                    {t('Cancel')}
                                                </Button>
                                                <Button type="submit" disabled={isLoading}>
                                                    {isLoading ? t('Capitalizing...') : t('Book & Allocate')}
                                                </Button>
                                            </div>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('LC Reference')}</TableHead>
                                            <TableHead>{t('Allocation Method')}</TableHead>
                                            <TableHead>{t('Custom Duties')}</TableHead>
                                            <TableHead>{t('Freight / Insurance')}</TableHead>
                                            <TableHead>{t('Total Cost')}</TableHead>
                                            <TableHead>{t('DoubleEntry Journal')}</TableHead>
                                            <TableHead>{t('Date')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {landedCosts.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={7} className="text-center text-muted-foreground py-6">
                                                    {t('No landed cost allocations logged.')}
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            landedCosts.map((record) => {
                                                const total = parseFloat(record.freight_charges.toString()) +
                                                              parseFloat(record.insurance_fees.toString()) +
                                                              parseFloat(record.custom_duties.toString()) +
                                                              parseFloat(record.agent_fees.toString()) +
                                                              parseFloat(record.bank_fees.toString());
                                                return (
                                                    <TableRow key={record.id}>
                                                        <TableCell className="font-bold">{record.lc?.lc_number}</TableCell>
                                                        <TableCell className="capitalize text-xs">{record.allocation_method}</TableCell>
                                                        <TableCell>{record.custom_duties} {record.currency}</TableCell>
                                                        <TableCell>
                                                            <p className="text-xs"><strong>{t('Freight:')}</strong> {record.freight_charges} {record.currency}</p>
                                                            <p className="text-xs"><strong>{t('Ins:')}</strong> {record.insurance_fees} {record.currency}</p>
                                                        </TableCell>
                                                        <TableCell className="font-bold text-emerald-800">
                                                            {total.toFixed(2)} {record.currency}
                                                        </TableCell>
                                                        <TableCell>
                                                            {record.is_posted_to_accounts ? (
                                                                <span className="inline-flex items-center gap-1 text-emerald-700 text-xs font-semibold">
                                                                    <CheckCircle2 className="h-4 w-4" />
                                                                    {t('Posted (JE-') + record.journal_entry_id + ')'}
                                                                </span>
                                                            ) : (
                                                                <span className="text-muted-foreground text-xs italic">{t('Pending Ledger')}</span>
                                                            )}
                                                        </TableCell>
                                                        <TableCell>{new Date(record.created_at).toLocaleDateString()}</TableCell>
                                                    </TableRow>
                                                );
                                            })
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </AuthenticatedLayout>
    );
}
