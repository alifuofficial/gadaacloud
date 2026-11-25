import { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { formatCurrency, formatDate, formatDateTime } from '@/utils/helpers';
import {
    CheckCircle2,
    AlertTriangle,
    Settings,
    Warehouse,
    PlusCircle,
    QrCode,
    Boxes,
    DollarSign,
    ShoppingCart,
    Layers,
    ShieldCheck,
    Search,
    Check
} from 'lucide-react';

interface ProductItem {
    id: number;
    name: string;
    sku: string;
    sale_price: number;
    purchase_price?: number;
    inventory_type?: 'standard' | 'serialized' | 'batch';
}

interface WarehouseItem {
    id: number;
    name: string;
}

interface VendorItem {
    id: number;
    name: string;
}

interface StockValuationRow {
    id: number;
    product_id: number;
    product_name: string;
    sku: string;
    inventory_type: 'standard' | 'serialized' | 'batch';
    warehouse_id: number;
    warehouse_name: string;
    quantity: number;
    unit_cost: number;
    total_value: number;
}

interface ReorderRuleRecord {
    id: number;
    product_id: number;
    product?: ProductItem;
    warehouse_id: number;
    warehouse?: WarehouseItem;
    preferred_vendor?: VendorItem;
    min_stock_level: number;
    reorder_qty?: number;
    preferred_vendor_id?: number | null;
}

interface AdjustmentRecord {
    id: number;
    product_id: number;
    product?: ProductItem;
    warehouse_id: number;
    warehouse?: WarehouseItem;
    adjustment_type: 'addition' | 'subtraction';
    quantity: number;
    unit_cost?: number;
    reason: string;
    status?: 'pending' | 'approved' | 'rejected';
    adjusted_by?: { name: string };
    created_at: string;
}

interface AuditLogRecord {
    id: number;
    product_id: number;
    product?: ProductItem;
    warehouse_id: number;
    warehouse?: WarehouseItem;
    action_type: string;
    reference_type?: string | null;
    reference_id?: number | null;
    qty_delta: number;
    final_qty: number;
    notes?: string | null;
    creator?: { name: string };
    created_at: string;
}

interface LowStockAlertItem {
    id: number | string;
    product_id: number;
    warehouse_id: number;
    product_name: string;
    sku: string;
    warehouse_name: string;
    current_qty: number;
    min_qty: number;
    reorder_qty?: number;
    preferred_vendor_id?: number | null;
}

interface SerialRecord {
    id: number;
    product_id: number;
    product?: ProductItem;
    warehouse_id: number;
    warehouse?: WarehouseItem;
    serial_number: string;
    status: 'available' | 'sold' | 'damaged';
    created_at: string;
}

interface BatchRecord {
    id: number;
    product_id: number;
    product?: ProductItem;
    warehouse_id: number;
    warehouse?: WarehouseItem;
    batch_number: string;
    lot_grade?: string | null;
    origin?: string | null;
    manufacture_date?: string | null;
    expiry_date?: string | null;
    initial_quantity: number;
    remaining_quantity: number;
    unit_cost: number;
    status: 'active' | 'depleted' | 'expired' | 'quarantined';
    created_at: string;
}

interface InventoryStats {
    total_skus: number;
    total_units: number;
    total_inventory_value: number;
    low_stock_count: number;
    pending_adjustments: number;
    active_serials: number;
    active_batches: number;
    expiring_soon_batches: number;
}

interface InventoryProps {
    warehouses?: WarehouseItem[];
    products?: ProductItem[];
    vendors?: VendorItem[];
    serializedProducts?: ProductItem[];
    batchProducts?: ProductItem[];
    serials?: SerialRecord[];
    batches?: BatchRecord[];
    stockValuation?: StockValuationRow[];
    adjustments?: AdjustmentRecord[];
    reorderRules?: ReorderRuleRecord[];
    auditLogs?: AuditLogRecord[];
    lowStockAlerts?: LowStockAlertItem[];
    stats?: InventoryStats;
    hasWorkflow?: boolean;
}

export default function InventoryDashboard({
    warehouses = [],
    products = [],
    vendors = [],
    serializedProducts = [],
    batchProducts = [],
    serials = [],
    batches = [],
    stockValuation = [],
    adjustments = [],
    reorderRules = [],
    auditLogs = [],
    lowStockAlerts = [],
    stats,
    hasWorkflow = false
}: InventoryProps) {
    const { t } = useTranslation();
    const pageProps = usePage().props as any;
    const [isLoading, setIsLoading] = useState(false);

    // Search & Filters
    const [stockSearch, setStockSearch] = useState('');
    const [stockWarehouseFilter, setStockWarehouseFilter] = useState('all');
    const [serialFilter, setSerialFilter] = useState<'all' | 'available' | 'sold' | 'damaged'>('all');
    const [serialProductFilter, setSerialProductFilter] = useState('all');

    // Modal triggers
    const [isAdjustModalOpen, setIsAdjustModalOpen] = useState(false);
    const [isRuleModalOpen, setIsRuleModalOpen] = useState(false);
    const [isSerialsModalOpen, setIsSerialsModalOpen] = useState(false);
    const [isBatchModalOpen, setIsBatchModalOpen] = useState(false);

    // Adjustment Form
    const [adjustForm, setAdjustForm] = useState({
        warehouse_id: '',
        product_id: '',
        adjustment_type: 'addition',
        quantity: '',
        unit_cost: '',
        reason: '',
    });

    // Reorder Rule Form
    const [ruleForm, setRuleForm] = useState({
        warehouse_id: '',
        product_id: '',
        min_stock_level: '5.00',
        reorder_qty: '20',
        preferred_vendor_id: 'none',
    });

    // Serial Number Form
    const [serialForm, setSerialForm] = useState({
        product_id: '',
        warehouse_id: '',
        serials_text: '',
        action: 'add' as 'add' | 'mark_sold' | 'mark_damaged',
    });

    // Batch / Lot Form
    const [batchForm, setBatchForm] = useState({
        product_id: '',
        warehouse_id: '',
        batch_number: '',
        lot_grade: '',
        origin: '',
        manufacture_date: '',
        expiry_date: '',
        quantity: '',
        unit_cost: '',
    });

    const safeStats: InventoryStats = stats || {
        total_skus: (stockValuation || []).length,
        total_units: 0,
        total_inventory_value: 0,
        low_stock_count: (lowStockAlerts || []).length,
        pending_adjustments: 0,
        active_serials: 0,
        active_batches: 0,
        expiring_soon_batches: 0,
    };

    const filteredValuation = useMemo(() => {
        const q = stockSearch.trim().toLowerCase();
        return (stockValuation || []).filter((row) => {
            const whOk = stockWarehouseFilter === 'all' || String(row.warehouse_id) === stockWarehouseFilter;
            const searchOk = !q ||
                (row.product_name || '').toLowerCase().includes(q) ||
                (row.sku || '').toLowerCase().includes(q) ||
                (row.warehouse_name || '').toLowerCase().includes(q);
            return whOk && searchOk;
        });
    }, [stockValuation, stockSearch, stockWarehouseFilter]);

    const filteredSerials = useMemo(() => {
        return (serials || []).filter((s) => {
            const statusOk = serialFilter === 'all' || s.status === serialFilter;
            const productOk = serialProductFilter === 'all' || String(s.product_id) === serialProductFilter;
            return statusOk && productOk;
        });
    }, [serials, serialFilter, serialProductFilter]);

    // Handlers
    const handleSaveAdjustment = (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        router.post(route('settings.inventory.adjustment.store'), adjustForm, {
            onSuccess: () => {
                setIsAdjustModalOpen(false);
                setAdjustForm({ warehouse_id: '', product_id: '', adjustment_type: 'addition', quantity: '', unit_cost: '', reason: '' });
            },
            onFinish: () => setIsLoading(false)
        });
    };

    const handleApproveAdjustment = (id: number) => {
        setIsLoading(true);
        router.post(route('settings.inventory.adjustment.approve', id), {}, {
            onFinish: () => setIsLoading(false)
        });
    };

    const handleSaveRule = (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        router.post(route('settings.inventory.reorder.store'), {
            ...ruleForm,
            preferred_vendor_id: ruleForm.preferred_vendor_id === 'none' ? null : ruleForm.preferred_vendor_id,
        }, {
            onSuccess: () => {
                setIsRuleModalOpen(false);
                setRuleForm({ warehouse_id: '', product_id: '', min_stock_level: '5.00', reorder_qty: '20', preferred_vendor_id: 'none' });
            },
            onFinish: () => setIsLoading(false)
        });
    };

    const handleSaveSerials = (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        router.post(route('settings.inventory.serials.store'), serialForm, {
            onSuccess: () => {
                setIsSerialsModalOpen(false);
                setSerialForm({ product_id: '', warehouse_id: '', serials_text: '', action: 'add' });
            },
            onFinish: () => setIsLoading(false)
        });
    };

    const handleSaveBatch = (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        router.post(route('settings.inventory.batches.store'), batchForm, {
            onSuccess: () => {
                setIsBatchModalOpen(false);
                setBatchForm({ product_id: '', warehouse_id: '', batch_number: '', lot_grade: '', origin: '', manufacture_date: '', expiry_date: '', quantity: '', unit_cost: '' });
            },
            onFinish: () => setIsLoading(false)
        });
    };

    const handleBatchStatus = (batchId: number, status: 'active' | 'quarantined' | 'expired') => {
        router.post(route('settings.inventory.batches.status', batchId), { status });
    };

    const handleCreatePurchaseFromAlert = (alert: LowStockAlertItem) => {
        const params: Record<string, string | number> = {
            warehouse_id: alert.warehouse_id,
            product_id: alert.product_id,
            quantity: alert.reorder_qty || Math.max(10, Math.ceil(alert.min_qty - alert.current_qty)),
        };
        if (alert.preferred_vendor_id) {
            params.vendor_id = alert.preferred_vendor_id;
        }
        router.visit(route('purchase-invoices.create', params));
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Dashboard'), url: route('dashboard') },
                { label: t('Inventory Management') }
            ]}
            pageTitle={t('Inventory Management Control Tower')}
        >
            <Head title={t('Inventory Management')} />

            <div className="space-y-6 max-w-7xl mx-auto">
                {/* KPI Summary Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card className="border-emerald-100 bg-gradient-to-br from-emerald-50/60 to-white">
                        <CardContent className="p-5 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase text-muted-foreground">{t('Total Inventory Asset')}</p>
                                <p className="text-2xl font-black text-emerald-700 mt-1">
                                    {formatCurrency(safeStats.total_inventory_value, pageProps)}
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    {safeStats.total_skus} {t('SKU-Warehouse lines')}
                                </p>
                            </div>
                            <div className="w-11 h-11 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center">
                                <DollarSign className="h-5 w-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-5 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase text-muted-foreground">{t('Physical Units On-Hand')}</p>
                                <p className="text-2xl font-black text-slate-900 mt-1">
                                    {safeStats.total_units.toLocaleString()}
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    {(warehouses || []).length} {t('active warehouses')}
                                </p>
                            </div>
                            <div className="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                <Boxes className="h-5 w-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className={safeStats.low_stock_count > 0 ? 'border-red-200 bg-red-50/40' : ''}>
                        <CardContent className="p-5 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase text-muted-foreground">{t('Low Stock Alerts')}</p>
                                <p className={`text-2xl font-black mt-1 ${safeStats.low_stock_count > 0 ? 'text-red-600' : 'text-emerald-700'}`}>
                                    {safeStats.low_stock_count}
                                </p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    {safeStats.pending_adjustments} {t('pending approvals')}
                                </p>
                            </div>
                            <div className={`w-11 h-11 rounded-xl flex items-center justify-center ${safeStats.low_stock_count > 0 ? 'bg-red-100 text-red-600' : 'bg-emerald-50 text-emerald-600'}`}>
                                <AlertTriangle className="h-5 w-5" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-5 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase text-muted-foreground">{t('Serials & Active Lots')}</p>
                                <p className="text-2xl font-black text-slate-900 mt-1">
                                    {safeStats.active_serials} / {safeStats.active_batches}
                                </p>
                                <p className={`text-xs mt-0.5 ${safeStats.expiring_soon_batches > 0 ? 'text-amber-600 font-semibold' : 'text-muted-foreground'}`}>
                                    {safeStats.expiring_soon_batches} {t('lots expiring within 30d')}
                                </p>
                            </div>
                            <div className="w-11 h-11 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                                <Layers className="h-5 w-5" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Low Stock Critical Banner */}
                {(lowStockAlerts || []).length > 0 && (
                    <div className="flex flex-wrap items-center justify-between gap-3 border border-red-200 bg-red-50 text-red-900 p-4 rounded-xl">
                        <div className="flex gap-3 items-start">
                            <AlertTriangle className="h-5 w-5 text-red-600 shrink-0 mt-0.5" />
                            <div>
                                <h5 className="font-bold text-sm">{t('Critical Stock Shortages Detected')}</h5>
                                <p className="text-xs mt-0.5">
                                    <strong>{lowStockAlerts.length}</strong> {t('warehouse-item lines have fallen below safety reorder thresholds. Use 1-Click Reorder in the Low Stock tab to issue a draft Purchase Invoice.')}
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                <Tabs defaultValue="valuation" className="w-full">
                    <TabsList className="grid w-full grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 h-auto gap-1 p-1">
                        <TabsTrigger value="valuation">{t('Stock & Valuation')}</TabsTrigger>
                        <TabsTrigger value="alerts">
                            {t('Low Stock & Rules')} {safeStats.low_stock_count > 0 ? `(${safeStats.low_stock_count})` : ''}
                        </TabsTrigger>
                        <TabsTrigger value="adjustments">
                            {t('Adjustments')} {safeStats.pending_adjustments > 0 ? `(${safeStats.pending_adjustments})` : ''}
                        </TabsTrigger>
                        <TabsTrigger value="serials">{t('Serial Numbers')}</TabsTrigger>
                        <TabsTrigger value="batches">{t('Batches & Lots')}</TabsTrigger>
                        <TabsTrigger value="audit_logs">{t('Audit Trail')}</TabsTrigger>
                    </TabsList>

                    {/* TAB 1: STOCK ON-HAND & WEIGHTED-AVERAGE VALUATION */}
                    <TabsContent value="valuation" className="space-y-4 mt-5">
                        <Card>
                            <CardHeader className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <CardTitle>{t('Live Warehouse Stock & Valuation (Weighted Average)')}</CardTitle>
                                    <CardDescription>{t('Reconcile physical inventory quantities and unit cost valuation against General Ledger Account 1200.')}</CardDescription>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <div className="relative">
                                        <Search className="h-4 w-4 text-muted-foreground absolute left-2.5 top-2.5" />
                                        <Input
                                            value={stockSearch}
                                            onChange={(e) => setStockSearch(e.target.value)}
                                            placeholder={t('Search item or SKU...')}
                                            className="pl-8 w-48"
                                        />
                                    </div>
                                    <Select value={stockWarehouseFilter} onValueChange={setStockWarehouseFilter}>
                                        <SelectTrigger className="w-44">
                                            <SelectValue placeholder={t('All Warehouses')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All Warehouses')}</SelectItem>
                                            {(warehouses || []).map((w) => (
                                                <SelectItem key={w.id} value={String(w.id)}>{w.name}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('Product')}</TableHead>
                                            <TableHead>{t('SKU')}</TableHead>
                                            <TableHead>{t('Tracking')}</TableHead>
                                            <TableHead>{t('Warehouse')}</TableHead>
                                            <TableHead className="text-right">{t('On-Hand Qty')}</TableHead>
                                            <TableHead className="text-right">{t('Avg Unit Cost')}</TableHead>
                                            <TableHead className="text-right">{t('Total Value')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {filteredValuation.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={7} className="text-center text-muted-foreground py-8">
                                                    {t('No warehouse stock balances found matching your filter.')}
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            filteredValuation.map((row) => (
                                                <TableRow key={row.id}>
                                                    <TableCell className="font-semibold">{row.product_name}</TableCell>
                                                    <TableCell className="font-mono text-xs">{row.sku}</TableCell>
                                                    <TableCell>
                                                        <Badge variant="outline" className="capitalize text-[11px]">
                                                            {row.inventory_type || 'standard'}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell>{row.warehouse_name}</TableCell>
                                                    <TableCell className={`text-right font-bold ${row.quantity <= 0 ? 'text-red-600' : 'text-slate-900'}`}>
                                                        {row.quantity}
                                                    </TableCell>
                                                    <TableCell className="text-right text-xs">
                                                        {formatCurrency(row.unit_cost, pageProps)}
                                                    </TableCell>
                                                    <TableCell className="text-right font-bold text-emerald-700">
                                                        {formatCurrency(row.total_value, pageProps)}
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* TAB 2: LOW STOCK ALERTS + REORDER RULES */}
                    <TabsContent value="alerts" className="space-y-6 mt-5">
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Low Stock Replenishment Queue')}</CardTitle>
                                <CardDescription>{t('Items below safety limits. Click Reorder to generate a pre-filled Purchase Invoice draft.')}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('Product')}</TableHead>
                                            <TableHead>{t('SKU')}</TableHead>
                                            <TableHead>{t('Warehouse')}</TableHead>
                                            <TableHead>{t('On Hand')}</TableHead>
                                            <TableHead>{t('Safety Limit')}</TableHead>
                                            <TableHead>{t('Deficit')}</TableHead>
                                            <TableHead className="text-right">{t('Action')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {(lowStockAlerts || []).length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={7} className="text-center text-emerald-700 font-medium py-6 bg-emerald-50">
                                                    <CheckCircle2 className="h-6 w-6 mx-auto mb-2 text-emerald-600" />
                                                    {t('All inventory stocks are healthy. No deficits recorded.')}
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            (lowStockAlerts || []).map((item) => (
                                                <TableRow key={item.id} className="bg-red-50/40 hover:bg-red-50/70">
                                                    <TableCell className="font-semibold text-red-900">{item.product_name}</TableCell>
                                                    <TableCell className="font-mono text-xs">{item.sku}</TableCell>
                                                    <TableCell>{item.warehouse_name}</TableCell>
                                                    <TableCell className="font-bold text-red-600">{item.current_qty}</TableCell>
                                                    <TableCell>{item.min_qty}</TableCell>
                                                    <TableCell className="font-bold text-red-700">
                                                        -{Math.max(0, item.min_qty - item.current_qty).toFixed(2)}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="border-emerald-300 text-emerald-700 hover:bg-emerald-50"
                                                            onClick={() => handleCreatePurchaseFromAlert(item)}
                                                        >
                                                            <ShoppingCart className="h-3.5 w-3.5 mr-1.5" />
                                                            {t('Reorder')} ({item.reorder_qty || 10})
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>

                        {/* Safety Reorder Rules Table */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle>{t('Safety Stock & Reorder Rules')}</CardTitle>
                                    <CardDescription>{t('Configure minimum safety limits, default reorder quantities, and preferred vendors.')}</CardDescription>
                                </div>
                                <Dialog open={isRuleModalOpen} onOpenChange={setIsRuleModalOpen}>
                                    <DialogTrigger asChild>
                                        <Button size="sm">
                                            <Settings className="h-4 w-4 mr-2" />
                                            {t('Create Safety Rule')}
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="max-w-md">
                                        <DialogHeader>
                                            <DialogTitle>{t('Configure Reorder Rule')}</DialogTitle>
                                        </DialogHeader>
                                        <form onSubmit={handleSaveRule} className="space-y-4">
                                            <div>
                                                <Label htmlFor="rule_warehouse">{t('Warehouse Location')}</Label>
                                                <Select value={ruleForm.warehouse_id} onValueChange={(val) => setRuleForm({ ...ruleForm, warehouse_id: val })}>
                                                    <SelectTrigger id="rule_warehouse"><SelectValue placeholder={t('Select Warehouse')} /></SelectTrigger>
                                                    <SelectContent>
                                                        {(warehouses || []).map((w) => (
                                                            <SelectItem key={w.id} value={w.id.toString()}>{w.name}</SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div>
                                                <Label htmlFor="rule_product">{t('Inventory Product')}</Label>
                                                <Select value={ruleForm.product_id} onValueChange={(val) => setRuleForm({ ...ruleForm, product_id: val })}>
                                                    <SelectTrigger id="rule_product"><SelectValue placeholder={t('Select Product')} /></SelectTrigger>
                                                    <SelectContent>
                                                        {(products || []).map((p) => (
                                                            <SelectItem key={p.id} value={p.id.toString()}>{p.name} ({p.sku})</SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="grid grid-cols-2 gap-3">
                                                <div>
                                                    <Label htmlFor="rule_min">{t('Min Safety Level')}</Label>
                                                    <Input
                                                        id="rule_min"
                                                        type="number"
                                                        step="0.01"
                                                        value={ruleForm.min_stock_level}
                                                        onChange={(e) => setRuleForm({ ...ruleForm, min_stock_level: e.target.value })}
                                                        required
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="rule_qty">{t('Default Reorder Qty')}</Label>
                                                    <Input
                                                        id="rule_qty"
                                                        type="number"
                                                        step="1"
                                                        value={ruleForm.reorder_qty}
                                                        onChange={(e) => setRuleForm({ ...ruleForm, reorder_qty: e.target.value })}
                                                    />
                                                </div>
                                            </div>
                                            <div>
                                                <Label htmlFor="rule_vendor">{t('Preferred Vendor (Optional)')}</Label>
                                                <Select value={ruleForm.preferred_vendor_id} onValueChange={(val) => setRuleForm({ ...ruleForm, preferred_vendor_id: val })}>
                                                    <SelectTrigger id="rule_vendor"><SelectValue placeholder={t('No Preferred Vendor')} /></SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="none">{t('No Preferred Vendor')}</SelectItem>
                                                        {(vendors || []).map((v) => (
                                                            <SelectItem key={v.id} value={String(v.id)}>{v.name}</SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="flex justify-end gap-2 pt-2">
                                                <Button type="button" variant="outline" onClick={() => setIsRuleModalOpen(false)}>{t('Cancel')}</Button>
                                                <Button type="submit" disabled={isLoading}>{isLoading ? t('Saving...') : t('Save Rule')}</Button>
                                            </div>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('Product')}</TableHead>
                                            <TableHead>{t('Warehouse')}</TableHead>
                                            <TableHead>{t('Min Limit')}</TableHead>
                                            <TableHead>{t('Reorder Qty')}</TableHead>
                                            <TableHead>{t('Preferred Vendor')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {(reorderRules || []).length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={5} className="text-center text-muted-foreground py-6">
                                                    {t('No safety threshold parameters configured.')}
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            (reorderRules || []).map((rule) => (
                                                <TableRow key={rule.id}>
                                                    <TableCell className="font-semibold">{rule.product?.name} <span className="text-xs font-mono text-muted-foreground">({rule.product?.sku})</span></TableCell>
                                                    <TableCell>{rule.warehouse?.name}</TableCell>
                                                    <TableCell className="font-bold text-blue-700">{rule.min_stock_level}</TableCell>
                                                    <TableCell>{rule.reorder_qty ?? '—'}</TableCell>
                                                    <TableCell className="text-xs">{rule.preferred_vendor?.name || t('Any Vendor')}</TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* TAB 3: STOCK ADJUSTMENTS & APPROVALS */}
                    <TabsContent value="adjustments" className="space-y-6 mt-5">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle>{t('Stocktake Adjustments & Shrinkage Write-Offs')}</CardTitle>
                                    <CardDescription>
                                        {hasWorkflow
                                            ? t('GadaaCloud Studio approval workflow is active: adjustments enter Pending until signed off.')
                                            : t('Approved adjustments immediately adjust WarehouseStock and post balanced GL entries (1200 / 5100).')}
                                    </CardDescription>
                                </div>
                                <Dialog open={isAdjustModalOpen} onOpenChange={setIsAdjustModalOpen}>
                                    <DialogTrigger asChild>
                                        <Button size="sm">
                                            <PlusCircle className="h-4 w-4 mr-2" />
                                            {t('Create Adjustment')}
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="max-w-md">
                                        <DialogHeader>
                                            <DialogTitle>{t('Record Physical Stock Adjustment')}</DialogTitle>
                                        </DialogHeader>
                                        <form onSubmit={handleSaveAdjustment} className="space-y-4">
                                            <div>
                                                <Label htmlFor="adj_warehouse">{t('Warehouse')}</Label>
                                                <Select value={adjustForm.warehouse_id} onValueChange={(val) => setAdjustForm({ ...adjustForm, warehouse_id: val })}>
                                                    <SelectTrigger id="adj_warehouse"><SelectValue placeholder={t('Select Warehouse')} /></SelectTrigger>
                                                    <SelectContent>
                                                        {(warehouses || []).map((w) => (
                                                            <SelectItem key={w.id} value={w.id.toString()}>{w.name}</SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div>
                                                <Label htmlFor="adj_product">{t('Product')}</Label>
                                                <Select value={adjustForm.product_id} onValueChange={(val) => setAdjustForm({ ...adjustForm, product_id: val })}>
                                                    <SelectTrigger id="adj_product"><SelectValue placeholder={t('Select Product')} /></SelectTrigger>
                                                    <SelectContent>
                                                        {(products || []).map((p) => (
                                                            <SelectItem key={p.id} value={p.id.toString()}>{p.name} ({p.sku})</SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="grid grid-cols-3 gap-3">
                                                <div>
                                                    <Label htmlFor="adj_type">{t('Type')}</Label>
                                                    <Select value={adjustForm.adjustment_type} onValueChange={(val) => setAdjustForm({ ...adjustForm, adjustment_type: val })}>
                                                        <SelectTrigger id="adj_type"><SelectValue /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="addition">{t('Addition (+)')}</SelectItem>
                                                            <SelectItem value="subtraction">{t('Subtraction (-)')}</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div>
                                                    <Label htmlFor="adj_qty">{t('Quantity')}</Label>
                                                    <Input
                                                        id="adj_qty"
                                                        type="number"
                                                        step="0.0001"
                                                        value={adjustForm.quantity}
                                                        onChange={(e) => setAdjustForm({ ...adjustForm, quantity: e.target.value })}
                                                        required
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="adj_cost">{t('Unit Cost')}</Label>
                                                    <Input
                                                        id="adj_cost"
                                                        type="number"
                                                        step="0.01"
                                                        placeholder={t('Auto')}
                                                        value={adjustForm.unit_cost}
                                                        onChange={(e) => setAdjustForm({ ...adjustForm, unit_cost: e.target.value })}
                                                    />
                                                </div>
                                            </div>
                                            <div>
                                                <Label htmlFor="adj_reason">{t('Reason / Remarks')}</Label>
                                                <Input
                                                    id="adj_reason"
                                                    value={adjustForm.reason}
                                                    onChange={(e) => setAdjustForm({ ...adjustForm, reason: e.target.value })}
                                                    placeholder="e.g. Damaged during transit, Cycle count variance"
                                                    required
                                                />
                                            </div>
                                            <div className="flex justify-end gap-2 pt-2">
                                                <Button type="button" variant="outline" onClick={() => setIsAdjustModalOpen(false)}>{t('Cancel')}</Button>
                                                <Button type="submit" disabled={isLoading}>{isLoading ? t('Saving...') : t('Save Adjustment')}</Button>
                                            </div>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('Product')}</TableHead>
                                            <TableHead>{t('Warehouse')}</TableHead>
                                            <TableHead>{t('Qty')}</TableHead>
                                            <TableHead>{t('Unit Cost')}</TableHead>
                                            <TableHead>{t('Reason')}</TableHead>
                                            <TableHead>{t('Status')}</TableHead>
                                            <TableHead>{t('Date')}</TableHead>
                                            <TableHead className="text-right">{t('Action')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {(adjustments || []).length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={8} className="text-center text-muted-foreground py-6">
                                                    {t('No physical adjustments logged.')}
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            (adjustments || []).map((record) => (
                                                <TableRow key={record.id}>
                                                    <TableCell className="font-semibold">{record.product?.name}</TableCell>
                                                    <TableCell>{record.warehouse?.name}</TableCell>
                                                    <TableCell className={`font-bold ${record.adjustment_type === 'addition' ? 'text-emerald-700' : 'text-red-600'}`}>
                                                        {record.adjustment_type === 'addition' ? '+' : '-'}{record.quantity}
                                                    </TableCell>
                                                    <TableCell className="text-xs">
                                                        {record.unit_cost ? formatCurrency(record.unit_cost, pageProps) : '—'}
                                                    </TableCell>
                                                    <TableCell className="text-xs italic">{record.reason}</TableCell>
                                                    <TableCell>
                                                        <Badge variant={record.status === 'approved' ? 'default' : record.status === 'pending' ? 'secondary' : 'destructive'} className="capitalize text-[11px]">
                                                            {record.status || 'approved'}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-xs">{formatDate(record.created_at, pageProps)}</TableCell>
                                                    <TableCell className="text-right">
                                                        {record.status === 'pending' && (
                                                            <Button size="sm" variant="outline" onClick={() => handleApproveAdjustment(record.id)} disabled={isLoading}>
                                                                <Check className="h-3.5 w-3.5 mr-1" /> {t('Approve')}
                                                            </Button>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* TAB 4: SERIAL NUMBERS MANAGEMENT */}
                    <TabsContent value="serials" className="space-y-6 mt-5">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle>{t('Serial & Chassis Number Registry')}</CardTitle>
                                    <CardDescription>{t('Track individual units by unique serial or vehicle chassis numbers. Automatically synced across sales and transfers.')}</CardDescription>
                                </div>
                                <Dialog open={isSerialsModalOpen} onOpenChange={setIsSerialsModalOpen}>
                                    <DialogTrigger asChild>
                                        <Button size="sm">
                                            <PlusCircle className="h-4 w-4 mr-2" />
                                            {t('Register Serials')}
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="max-w-lg">
                                        <DialogHeader>
                                            <DialogTitle>{t('Bulk Serial / Chassis Number Entry')}</DialogTitle>
                                        </DialogHeader>
                                        <form onSubmit={handleSaveSerials} className="space-y-4">
                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <Label>{t('Serialized Product')}</Label>
                                                    <Select value={serialForm.product_id} onValueChange={(val) => setSerialForm({ ...serialForm, product_id: val })}>
                                                        <SelectTrigger><SelectValue placeholder={t('Select Product')} /></SelectTrigger>
                                                        <SelectContent>
                                                            {(serializedProducts || []).map((p) => (
                                                                <SelectItem key={p.id} value={p.id.toString()}>{p.name}</SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div>
                                                    <Label>{t('Warehouse')}</Label>
                                                    <Select value={serialForm.warehouse_id} onValueChange={(val) => setSerialForm({ ...serialForm, warehouse_id: val })}>
                                                        <SelectTrigger><SelectValue placeholder={t('Select Warehouse')} /></SelectTrigger>
                                                        <SelectContent>
                                                            {(warehouses || []).map((w) => (
                                                                <SelectItem key={w.id} value={w.id.toString()}>{w.name}</SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                            <div>
                                                <Label>{t('Action')}</Label>
                                                <Select value={serialForm.action} onValueChange={(val) => setSerialForm({ ...serialForm, action: val as 'add' | 'mark_sold' | 'mark_damaged' })}>
                                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="add">{t('Register as Available (Add Stock)')}</SelectItem>
                                                        <SelectItem value="mark_sold">{t('Mark as Sold')}</SelectItem>
                                                        <SelectItem value="mark_damaged">{t('Mark as Damaged / Written Off')}</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div>
                                                <Label>{t('Serial / Chassis Numbers')}</Label>
                                                <Textarea
                                                    value={serialForm.serials_text}
                                                    onChange={(e) => setSerialForm({ ...serialForm, serials_text: e.target.value })}
                                                    placeholder={t('Paste serial numbers — one per line or comma-separated')}
                                                    rows={6}
                                                    className="font-mono text-sm"
                                                    required
                                                />
                                            </div>
                                            <div className="flex justify-end gap-2 pt-2">
                                                <Button type="button" variant="outline" onClick={() => setIsSerialsModalOpen(false)}>{t('Cancel')}</Button>
                                                <Button type="submit" disabled={isLoading}>{isLoading ? t('Processing...') : t('Submit')}</Button>
                                            </div>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-wrap gap-3 mb-4">
                                    <Select value={serialProductFilter} onValueChange={setSerialProductFilter}>
                                        <SelectTrigger className="w-48"><SelectValue placeholder={t('All Products')} /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All Products')}</SelectItem>
                                            {(serializedProducts || []).map((p) => (
                                                <SelectItem key={p.id} value={p.id.toString()}>{p.name}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <div className="flex gap-1">
                                        {(['all', 'available', 'sold', 'damaged'] as const).map((s) => (
                                            <Button key={s} size="sm" variant={serialFilter === s ? 'default' : 'outline'} onClick={() => setSerialFilter(s)}>
                                                {s === 'all' ? t('All') : t(s.charAt(0).toUpperCase() + s.slice(1))}
                                            </Button>
                                        ))}
                                    </div>
                                    <span className="text-sm text-muted-foreground ml-auto self-center">{filteredSerials.length} {t('records')}</span>
                                </div>

                                {(serializedProducts || []).length === 0 ? (
                                    <div className="text-center py-10 text-muted-foreground">
                                        <QrCode className="h-10 w-10 mx-auto mb-3 opacity-30" />
                                        <p className="font-semibold">{t('No Serialized Products Configured')}</p>
                                        <p className="text-sm mt-1">{t('Set Inventory Tracking to "Serialized" on any item in Product & Services to manage individual serials.')}</p>
                                    </div>
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>{t('Serial / Chassis No.')}</TableHead>
                                                <TableHead>{t('Product')}</TableHead>
                                                <TableHead>{t('Warehouse')}</TableHead>
                                                <TableHead>{t('Status')}</TableHead>
                                                <TableHead>{t('Registered')}</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {filteredSerials.length === 0 ? (
                                                <TableRow>
                                                    <TableCell colSpan={5} className="text-center text-muted-foreground py-6">
                                                        {t('No serial numbers found matching filters.')}
                                                    </TableCell>
                                                </TableRow>
                                            ) : (
                                                filteredSerials.map((s) => (
                                                    <TableRow key={s.id}>
                                                        <TableCell className="font-mono font-semibold text-sm">{s.serial_number}</TableCell>
                                                        <TableCell>{s.product?.name}</TableCell>
                                                        <TableCell>{s.warehouse?.name}</TableCell>
                                                        <TableCell>
                                                            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${
                                                                s.status === 'available' ? 'bg-emerald-100 text-emerald-800' :
                                                                s.status === 'sold' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800'
                                                            }`}>
                                                                {t(s.status.charAt(0).toUpperCase() + s.status.slice(1))}
                                                            </span>
                                                        </TableCell>
                                                        <TableCell className="text-xs text-muted-foreground">{formatDate(s.created_at, pageProps)}</TableCell>
                                                    </TableRow>
                                                ))
                                            )}
                                        </TableBody>
                                    </Table>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* TAB 5: BATCH / LOT & EXPIRY TRACKING (FEFO) */}
                    <TabsContent value="batches" className="space-y-6 mt-5">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle>{t('Batch / Lot & Expiry Management (FEFO)')}</CardTitle>
                                    <CardDescription>{t('Track Ethiopian coffee ECX grades, agribusiness harvest lots, and expiry-controlled goods. Deductions automatically pick earliest expiry first.')}</CardDescription>
                                </div>
                                <Dialog open={isBatchModalOpen} onOpenChange={setIsBatchModalOpen}>
                                    <DialogTrigger asChild>
                                        <Button size="sm">
                                            <PlusCircle className="h-4 w-4 mr-2" />
                                            {t('Register Batch / Lot')}
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="max-w-lg">
                                        <DialogHeader>
                                            <DialogTitle>{t('Register New Inventory Batch / Lot')}</DialogTitle>
                                        </DialogHeader>
                                        <form onSubmit={handleSaveBatch} className="space-y-4">
                                            <div className="grid grid-cols-2 gap-3">
                                                <div>
                                                    <Label>{t('Product')}</Label>
                                                    <Select value={batchForm.product_id} onValueChange={(val) => setBatchForm({ ...batchForm, product_id: val })}>
                                                        <SelectTrigger><SelectValue placeholder={t('Select Product')} /></SelectTrigger>
                                                        <SelectContent>
                                                            {(batchProducts.length > 0 ? batchProducts : products || []).map((p) => (
                                                                <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div>
                                                    <Label>{t('Warehouse')}</Label>
                                                    <Select value={batchForm.warehouse_id} onValueChange={(val) => setBatchForm({ ...batchForm, warehouse_id: val })}>
                                                        <SelectTrigger><SelectValue placeholder={t('Select Warehouse')} /></SelectTrigger>
                                                        <SelectContent>
                                                            {(warehouses || []).map((w) => (
                                                                <SelectItem key={w.id} value={String(w.id)}>{w.name}</SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                            <div className="grid grid-cols-3 gap-3">
                                                <div>
                                                    <Label>{t('Batch / Lot No.')}</Label>
                                                    <Input value={batchForm.batch_number} onChange={(e) => setBatchForm({ ...batchForm, batch_number: e.target.value })} placeholder="LOT-2026-01" required />
                                                </div>
                                                <div>
                                                    <Label>{t('ECX / Lot Grade')}</Label>
                                                    <Input value={batchForm.lot_grade} onChange={(e) => setBatchForm({ ...batchForm, lot_grade: e.target.value })} placeholder="Grade 1 / Q88" />
                                                </div>
                                                <div>
                                                    <Label>{t('Origin')}</Label>
                                                    <Input value={batchForm.origin} onChange={(e) => setBatchForm({ ...batchForm, origin: e.target.value })} placeholder="Sidama / Yirgacheffe" />
                                                </div>
                                            </div>
                                            <div className="grid grid-cols-2 gap-3">
                                                <div>
                                                    <Label>{t('Manufacture / Harvest Date')}</Label>
                                                    <DatePicker value={batchForm.manufacture_date} onChange={(date) => setBatchForm({ ...batchForm, manufacture_date: date })} placeholder={t('Select date')} />
                                                </div>
                                                <div>
                                                    <Label>{t('Expiry / Best-Before Date')}</Label>
                                                    <DatePicker value={batchForm.expiry_date} onChange={(date) => setBatchForm({ ...batchForm, expiry_date: date })} placeholder={t('Select date')} />
                                                </div>
                                            </div>
                                            <div className="grid grid-cols-2 gap-3">
                                                <div>
                                                    <Label>{t('Quantity')}</Label>
                                                    <Input type="number" step="0.0001" value={batchForm.quantity} onChange={(e) => setBatchForm({ ...batchForm, quantity: e.target.value })} required />
                                                </div>
                                                <div>
                                                    <Label>{t('Unit Cost')}</Label>
                                                    <Input type="number" step="0.01" value={batchForm.unit_cost} onChange={(e) => setBatchForm({ ...batchForm, unit_cost: e.target.value })} placeholder={t('Auto from purchase price')} />
                                                </div>
                                            </div>
                                            <div className="flex justify-end gap-2 pt-2">
                                                <Button type="button" variant="outline" onClick={() => setIsBatchModalOpen(false)}>{t('Cancel')}</Button>
                                                <Button type="submit" disabled={isLoading}>{isLoading ? t('Saving...') : t('Register Lot')}</Button>
                                            </div>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('Batch / Lot No.')}</TableHead>
                                            <TableHead>{t('Product')}</TableHead>
                                            <TableHead>{t('Warehouse')}</TableHead>
                                            <TableHead>{t('Grade / Origin')}</TableHead>
                                            <TableHead>{t('Expiry Date')}</TableHead>
                                            <TableHead className="text-right">{t('Remaining / Init')}</TableHead>
                                            <TableHead>{t('Status')}</TableHead>
                                            <TableHead className="text-right">{t('Control')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {(batches || []).length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={8} className="text-center text-muted-foreground py-8">
                                                    {t('No batch or lot records registered yet.')}
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            (batches || []).map((b) => (
                                                <TableRow key={b.id}>
                                                    <TableCell className="font-mono font-bold text-xs">{b.batch_number}</TableCell>
                                                    <TableCell className="font-semibold">{b.product?.name}</TableCell>
                                                    <TableCell>{b.warehouse?.name}</TableCell>
                                                    <TableCell className="text-xs">
                                                        {[b.lot_grade, b.origin].filter(Boolean).join(' · ') || '—'}
                                                    </TableCell>
                                                    <TableCell className="text-xs">
                                                        {b.expiry_date ? formatDate(b.expiry_date, pageProps) : t('No Expiry')}
                                                    </TableCell>
                                                    <TableCell className="text-right font-bold">
                                                        {b.remaining_quantity} <span className="text-xs text-muted-foreground font-normal">/ {b.initial_quantity}</span>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge variant={b.status === 'active' ? 'default' : b.status === 'depleted' ? 'secondary' : 'destructive'} className="capitalize text-[11px]">
                                                            {b.status}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {b.status === 'active' ? (
                                                            <Button size="sm" variant="outline" onClick={() => handleBatchStatus(b.id, 'quarantined')}>
                                                                {t('Quarantine')}
                                                            </Button>
                                                        ) : b.status === 'quarantined' ? (
                                                            <Button size="sm" variant="outline" onClick={() => handleBatchStatus(b.id, 'active')}>
                                                                {t('Activate')}
                                                            </Button>
                                                        ) : null}
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* TAB 6: STOCK AUDIT TRAIL LEDGER */}
                    <TabsContent value="audit_logs" className="space-y-6 mt-5">
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Stock Audit Trail Ledger')}</CardTitle>
                                <CardDescription>{t('Immutable trail of all stock movements across purchases, sales, POS, waybill transfers, landed cost capitalizations, and adjustments.')}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('Product')}</TableHead>
                                            <TableHead>{t('Warehouse')}</TableHead>
                                            <TableHead>{t('Activity')}</TableHead>
                                            <TableHead>{t('Reference')}</TableHead>
                                            <TableHead>{t('Delta')}</TableHead>
                                            <TableHead>{t('Resulting Qty')}</TableHead>
                                            <TableHead>{t('Authorized By')}</TableHead>
                                            <TableHead>{t('Timestamp')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {(auditLogs || []).length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={8} className="text-center text-muted-foreground py-6">
                                                    {t('No inventory movements logged in audit trail.')}
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            (auditLogs || []).map((log) => (
                                                <TableRow key={log.id}>
                                                    <TableCell>
                                                        <p className="font-semibold">{log.product?.name}</p>
                                                        <p className="text-xs text-muted-foreground font-mono">{log.product?.sku}</p>
                                                    </TableCell>
                                                    <TableCell>{log.warehouse?.name}</TableCell>
                                                    <TableCell>
                                                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold uppercase ${
                                                            log.action_type === 'purchase' || log.action_type === 'batch_receipt' ? 'bg-blue-100 text-blue-800' :
                                                            log.action_type === 'sale' || log.action_type === 'pos_sale' ? 'bg-emerald-100 text-emerald-800' :
                                                            log.action_type === 'adjustment' || log.action_type === 'transfer_damage' ? 'bg-amber-100 text-amber-800' :
                                                            'bg-purple-100 text-purple-800'
                                                        }`}>
                                                            {log.action_type}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell className="text-xs">
                                                        {log.reference_type ? `${log.reference_type} #${log.reference_id ?? ''}` : (log.notes || '—')}
                                                    </TableCell>
                                                    <TableCell className={`font-bold ${parseFloat(log.qty_delta.toString()) >= 0 ? 'text-emerald-700' : 'text-red-600'}`}>
                                                        {parseFloat(log.qty_delta.toString()) >= 0 ? '+' : ''}{log.qty_delta}
                                                    </TableCell>
                                                    <TableCell className="font-bold text-slate-800">{log.final_qty}</TableCell>
                                                    <TableCell className="text-xs">{log.creator?.name || t('System')}</TableCell>
                                                    <TableCell className="text-xs text-muted-foreground">{formatDateTime(log.created_at, pageProps)}</TableCell>
                                                </TableRow>
                                            ))
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
