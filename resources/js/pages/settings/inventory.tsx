import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { CheckCircle2, AlertTriangle, ArrowRightLeft, Settings, ClipboardList, Warehouse, PlusCircle, History, QrCode } from 'lucide-react';

interface ProductItem {
    id: number;
    name: string;
    sku: string;
    sale_price: number;
    inventory_type?: 'standard' | 'serialized';
}

interface WarehouseItem {
    id: number;
    name: string;
}

interface ReorderRuleRecord {
    id: number;
    product_id: number;
    product?: ProductItem;
    warehouse_id: number;
    warehouse?: WarehouseItem;
    min_stock_level: number;
}

interface AdjustmentRecord {
    id: number;
    product_id: number;
    product?: ProductItem;
    warehouse_id: number;
    warehouse?: WarehouseItem;
    adjustment_type: 'addition' | 'subtraction';
    quantity: number;
    reason: string;
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
    qty_delta: number;
    final_qty: number;
    creator?: { name: string };
    created_at: string;
}

interface LowStockAlertItem {
    id: number;
    product_name: string;
    sku: string;
    warehouse_name: string;
    current_qty: number;
    min_qty: number;
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

interface InventoryProps {
    warehouses: WarehouseItem[];
    products: ProductItem[];
    serializedProducts: ProductItem[];
    serials: SerialRecord[];
    adjustments: AdjustmentRecord[];
    reorderRules: ReorderRuleRecord[];
    auditLogs: AuditLogRecord[];
    lowStockAlerts: LowStockAlertItem[];
}

export default function InventoryDashboard({ warehouses, products, serializedProducts, serials, adjustments, reorderRules, auditLogs, lowStockAlerts }: InventoryProps) {
    const { t } = useTranslation();
    const [isLoading, setIsLoading] = useState(false);

    // Modal triggers
    const [isAdjustModalOpen, setIsAdjustModalOpen] = useState(false);
    const [isRuleModalOpen, setIsRuleModalOpen] = useState(false);
    const [isSerialsModalOpen, setIsSerialsModalOpen] = useState(false);

    // Serial filter state
    const [serialFilter, setSerialFilter] = useState<'all' | 'available' | 'sold' | 'damaged'>('all');
    const [serialProductFilter, setSerialProductFilter] = useState('all');

    // Adjustment Form
    const [adjustForm, setAdjustForm] = useState({
        warehouse_id: '',
        product_id: '',
        adjustment_type: 'addition',
        quantity: '',
        reason: '',
    });

    // Reorder Rule Form
    const [ruleForm, setRuleForm] = useState({
        warehouse_id: '',
        product_id: '',
        min_stock_level: '5.00',
    });

    // Serial Number Form
    const [serialForm, setSerialForm] = useState({
        product_id: '',
        warehouse_id: '',
        serials_text: '',
        action: 'add' as 'add' | 'mark_sold' | 'mark_damaged',
    });

    // Handle adjustment save
    const handleSaveAdjustment = (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        router.post(route('settings.inventory.adjustment.store'), adjustForm, {
            onSuccess: () => {
                setIsLoading(false);
                setIsAdjustModalOpen(false);
                setAdjustForm({ warehouse_id: '', product_id: '', adjustment_type: 'addition', quantity: '', reason: '' });
            },
            onFinish: () => setIsLoading(false)
        });
    };

    // Handle reorder rule save
    const handleSaveRule = (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        router.post(route('settings.inventory.reorder.store'), ruleForm, {
            onSuccess: () => {
                setIsLoading(false);
                setIsRuleModalOpen(false);
                setRuleForm({ warehouse_id: '', product_id: '', min_stock_level: '5.00' });
            },
            onFinish: () => setIsLoading(false)
        });
    };

    // Handle serial numbers save
    const handleSaveSerials = (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        router.post(route('settings.inventory.serials.store'), serialForm, {
            onSuccess: () => {
                setIsLoading(false);
                setIsSerialsModalOpen(false);
                setSerialForm({ product_id: '', warehouse_id: '', serials_text: '', action: 'add' });
            },
            onFinish: () => setIsLoading(false)
        });
    };

    // Filter serials
    const filteredSerials = serials.filter(s => {
        const statusOk = serialFilter === 'all' || s.status === serialFilter;
        const productOk = serialProductFilter === 'all' || s.product_id.toString() === serialProductFilter;
        return statusOk && productOk;
    });

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Settings'), url: route('settings.index') },
                { label: t('Inventory Management') }
            ]}
            pageTitle={t('Inventory Management')}
        >
            <Head title={t('Inventory Management')} />

            <div className="space-y-8 max-w-6xl mx-auto">
                {/* Low Stock Banner Alert */}
                {lowStockAlerts.length > 0 && (
                    <div className="flex gap-3 border border-red-200 bg-red-50 text-red-900 p-4 rounded-lg">
                        <AlertTriangle className="h-5 w-5 text-red-600 shrink-0" />
                        <div>
                            <h5 className="font-bold">{t('Critical Stock Shortages Detected')}</h5>
                            <p className="text-xs mt-1">
                                {t('There are currently ')} <strong>{lowStockAlerts.length}</strong> {t(' items carrying stock levels below their safety reorder thresholds. Please review the safety rules.')}
                            </p>
                        </div>
                    </div>
                )}

                <Tabs defaultValue="alerts" className="w-full">
                    <TabsList className="grid w-full grid-cols-5 max-w-2xl">
                        <TabsTrigger value="alerts">{t('Low Stock')}</TabsTrigger>
                        <TabsTrigger value="rules">{t('Reorder Rules')}</TabsTrigger>
                        <TabsTrigger value="adjustments">{t('Adjustments')}</TabsTrigger>
                        <TabsTrigger value="serials">{t('Serial Numbers')}</TabsTrigger>
                        <TabsTrigger value="audit_logs">{t('Audit Trail')}</TabsTrigger>
                    </TabsList>

                    {/* Tab 1: Low Stock Warnings */}
                    <TabsContent value="alerts" className="space-y-6 mt-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Low Stock Warnings List')}</CardTitle>
                                <CardDescription>{t('Inventory items that have dropped below safety thresholds.')}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('Product Name')}</TableHead>
                                            <TableHead>{t('SKU Code')}</TableHead>
                                            <TableHead>{t('Warehouse Location')}</TableHead>
                                            <TableHead>{t('Current stock count')}</TableHead>
                                            <TableHead>{t('Safety Limit')}</TableHead>
                                            <TableHead>{t('Deficit')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {lowStockAlerts.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={6} className="text-center text-emerald-700 font-medium py-6 bg-emerald-50">
                                                    <CheckCircle2 className="h-6 w-6 mx-auto mb-2 text-emerald-600" />
                                                    {t('All inventory stocks are healthy. No deficits recorded.')}
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            lowStockAlerts.map((item) => (
                                                <TableRow key={item.id} className="bg-red-50/40 hover:bg-red-50/70 border-red-100">
                                                    <TableCell className="font-semibold text-red-900">{item.product_name}</TableCell>
                                                    <TableCell className="font-mono text-xs">{item.sku}</TableCell>
                                                    <TableCell>{item.warehouse_name}</TableCell>
                                                    <TableCell className="font-bold text-red-600">{item.current_qty}</TableCell>
                                                    <TableCell>{item.min_qty}</TableCell>
                                                    <TableCell className="font-bold text-red-700">
                                                        -{(item.min_qty - item.current_qty).toFixed(2)}
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Tab 2: Reorder Rules */}
                    <TabsContent value="rules" className="space-y-6 mt-6">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle>{t('Safety stock threshold configurations')}</CardTitle>
                                    <CardDescription>{t('Configure the minimum safety parameters to trigger reorder alarms.')}</CardDescription>
                                </div>
                                <Dialog open={isRuleModalOpen} onOpenChange={setIsRuleModalOpen}>
                                    <DialogTrigger asChild>
                                        <Button size="sm">
                                            <Settings className="h-4 w-4 mr-2" />
                                            {t('Create Safety rule')}
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="max-w-md">
                                        <DialogHeader>
                                            <DialogTitle>{t('Add safety stock rule')}</DialogTitle>
                                        </DialogHeader>
                                        <form onSubmit={handleSaveRule} className="space-y-4">
                                            <div>
                                                <Label htmlFor="rule_warehouse">{t('Warehouse Location')}</Label>
                                                <Select value={ruleForm.warehouse_id} onValueChange={(val) => setRuleForm({ ...ruleForm, warehouse_id: val })}>
                                                    <SelectTrigger id="rule_warehouse">
                                                        <SelectValue placeholder={t('Select Warehouse')} />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {warehouses.map((w) => (
                                                            <SelectItem key={w.id} value={w.id.toString()}>
                                                                {w.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div>
                                                <Label htmlFor="rule_product">{t('Inventory Product')}</Label>
                                                <Select value={ruleForm.product_id} onValueChange={(val) => setRuleForm({ ...ruleForm, product_id: val })}>
                                                    <SelectTrigger id="rule_product">
                                                        <SelectValue placeholder={t('Select Product')} />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {products.map((p) => (
                                                            <SelectItem key={p.id} value={p.id.toString()}>
                                                                {p.name} ({p.sku})
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div>
                                                <Label htmlFor="rule_min">{t('Minimum stock count limit')}</Label>
                                                <Input
                                                    id="rule_min"
                                                    type="number"
                                                    step="0.01"
                                                    value={ruleForm.min_stock_level}
                                                    onChange={(e) => setRuleForm({ ...ruleForm, min_stock_level: e.target.value })}
                                                    required
                                                />
                                            </div>

                                            <div className="flex justify-end gap-2 pt-4">
                                                <Button type="button" variant="outline" onClick={() => setIsRuleModalOpen(false)}>
                                                    {t('Cancel')}
                                                </Button>
                                                <Button type="submit" disabled={isLoading}>
                                                    {isLoading ? t('Saving...') : t('Set Rule')}
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
                                            <TableHead>{t('Product Name')}</TableHead>
                                            <TableHead>{t('SKU')}</TableHead>
                                            <TableHead>{t('Warehouse')}</TableHead>
                                            <TableHead>{t('Min stock rule limit')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {reorderRules.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={4} className="text-center text-muted-foreground py-6">
                                                    {t('No safety threshold parameters configured.')}
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            reorderRules.map((rule) => (
                                                <TableRow key={rule.id}>
                                                    <TableCell className="font-semibold">{rule.product?.name}</TableCell>
                                                    <TableCell className="font-mono text-xs">{rule.product?.sku}</TableCell>
                                                    <TableCell>{rule.warehouse?.name}</TableCell>
                                                    <TableCell className="font-bold text-blue-700">{rule.min_stock_level}</TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Tab 3: Stock Adjustments */}
                    <TabsContent value="adjustments" className="space-y-6 mt-6">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle>{t('Stocktake adjustments log')}</CardTitle>
                                    <CardDescription>{t('Physical stock corrections history and discrepancies adjustments.')}</CardDescription>
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
                                            <DialogTitle>{t('Record physical stock adjustment')}</DialogTitle>
                                        </DialogHeader>
                                        <form onSubmit={handleSaveAdjustment} className="space-y-4">
                                            <div>
                                                <Label htmlFor="adj_warehouse">{t('Warehouse')}</Label>
                                                <Select value={adjustForm.warehouse_id} onValueChange={(val) => setAdjustForm({ ...adjustForm, warehouse_id: val })}>
                                                    <SelectTrigger id="adj_warehouse">
                                                        <SelectValue placeholder={t('Select Warehouse')} />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {warehouses.map((w) => (
                                                            <SelectItem key={w.id} value={w.id.toString()}>
                                                                {w.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div>
                                                <Label htmlFor="adj_product">{t('Product')}</Label>
                                                <Select value={adjustForm.product_id} onValueChange={(val) => setAdjustForm({ ...adjustForm, product_id: val })}>
                                                    <SelectTrigger id="adj_product">
                                                        <SelectValue placeholder={t('Select Product')} />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {products.map((p) => (
                                                            <SelectItem key={p.id} value={p.id.toString()}>
                                                                {p.name} ({p.sku})
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <Label htmlFor="adj_type">{t('Adjustment Type')}</Label>
                                                    <Select value={adjustForm.adjustment_type} onValueChange={(val) => setAdjustForm({ ...adjustForm, adjustment_type: val })}>
                                                        <SelectTrigger id="adj_type">
                                                            <SelectValue />
                                                        </SelectTrigger>
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
                                            </div>

                                            <div>
                                                <Label htmlFor="adj_reason">{t('Reason / Remarks')}</Label>
                                                <Input
                                                    id="adj_reason"
                                                    value={adjustForm.reason}
                                                    onChange={(e) => setAdjustForm({ ...adjustForm, reason: e.target.value })}
                                                    placeholder="e.g. Damaged during logistics, Stocktake discrepancy"
                                                    required
                                                />
                                            </div>

                                            <div className="flex justify-end gap-2 pt-4">
                                                <Button type="button" variant="outline" onClick={() => setIsAdjustModalOpen(false)}>
                                                    {t('Cancel')}
                                                </Button>
                                                <Button type="submit" disabled={isLoading}>
                                                    {isLoading ? t('Adjusting...') : t('Save Adjustment')}
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
                                            <TableHead>{t('Product Name')}</TableHead>
                                            <TableHead>{t('Warehouse')}</TableHead>
                                            <TableHead>{t('Adjustment count')}</TableHead>
                                            <TableHead>{t('Reason Code')}</TableHead>
                                            <TableHead>{t('Logged By')}</TableHead>
                                            <TableHead>{t('Date')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {adjustments.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={6} className="text-center text-muted-foreground py-6">
                                                    {t('No physical adjustments logged.')}
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            adjustments.map((record) => (
                                                <TableRow key={record.id}>
                                                    <TableCell className="font-semibold">{record.product?.name}</TableCell>
                                                    <TableCell>{record.warehouse?.name}</TableCell>
                                                    <TableCell className={`font-bold ${record.adjustment_type === 'addition' ? 'text-emerald-700' : 'text-red-600'}`}>
                                                        {record.adjustment_type === 'addition' ? '+' : '-'}{record.quantity}
                                                    </TableCell>
                                                    <TableCell className="text-sm italic">{record.reason}</TableCell>
                                                    <TableCell className="text-xs">{record.adjusted_by?.name || t('N/A')}</TableCell>
                                                    <TableCell className="text-xs">{new Date(record.created_at).toLocaleDateString()}</TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Tab 4: Stock Audit Trail Logs */}
                    <TabsContent value="audit_logs" className="space-y-6 mt-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Stock Audit trail ledger')}</CardTitle>
                                <CardDescription>{t('Historical trail of all stock movements, invoices, transfers and adjustments.')}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('Product')}</TableHead>
                                            <TableHead>{t('Warehouse')}</TableHead>
                                            <TableHead>{t('Activity')}</TableHead>
                                            <TableHead>{t('Delta change')}</TableHead>
                                            <TableHead>{t('Resulting stock count')}</TableHead>
                                            <TableHead>{t('Authorized By')}</TableHead>
                                            <TableHead>{t('Timestamp')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {auditLogs.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={7} className="text-center text-muted-foreground py-6">
                                                    {t('No inventory movements logged in audit trail.')}
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            auditLogs.map((log) => (
                                                <TableRow key={log.id}>
                                                    <TableCell>
                                                        <p className="font-semibold">{log.product?.name}</p>
                                                        <p className="text-xs text-muted-foreground font-mono">{log.product?.sku}</p>
                                                    </TableCell>
                                                    <TableCell>{log.warehouse?.name}</TableCell>
                                                    <TableCell>
                                                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold uppercase ${
                                                            log.action_type === 'purchase' ? 'bg-blue-100 text-blue-800' :
                                                            log.action_type === 'sale' ? 'bg-green-100 text-green-800' :
                                                            log.action_type === 'adjustment' ? 'bg-amber-100 text-amber-800' : 'bg-purple-100 text-purple-800'
                                                        }`}>
                                                            {log.action_type}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell className={`font-bold ${parseFloat(log.qty_delta.toString()) >= 0 ? 'text-emerald-700' : 'text-red-600'}`}>
                                                        {parseFloat(log.qty_delta.toString()) >= 0 ? '+' : ''}{log.qty_delta}
                                                    </TableCell>
                                                    <TableCell className="font-bold text-slate-800">{log.final_qty}</TableCell>
                                                    <TableCell className="text-xs">{log.creator?.name || t('System Autogen')}</TableCell>
                                                    <TableCell className="text-xs text-muted-foreground">{new Date(log.created_at).toLocaleString()}</TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>
                    {/* Tab 5: Serial Numbers Management */}
                    <TabsContent value="serials" className="space-y-6 mt-6">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle>{t('Serial & Chassis Number Registry')}</CardTitle>
                                    <CardDescription>{t('Track individual units by unique serial numbers (e.g., Chassis Numbers for vehicles).')}</CardDescription>
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
                                                    <Label>{t('Product')}</Label>
                                                    <Select value={serialForm.product_id} onValueChange={(val) => setSerialForm({ ...serialForm, product_id: val })}>
                                                        <SelectTrigger><SelectValue placeholder={t('Select Product')} /></SelectTrigger>
                                                        <SelectContent>
                                                            {serializedProducts.map((p) => (
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
                                                            {warehouses.map((w) => (
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
                                                    placeholder={t('Paste serial numbers here — one per line or comma-separated\nExample:\nCHAS-001-A\nCHAS-002-B\nCHAS-003-C')}
                                                    rows={8}
                                                    className="font-mono text-sm"
                                                    required
                                                />
                                                <p className="text-xs text-muted-foreground mt-1">{t('Accepts one per line OR comma-separated values.')}</p>
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
                                {/* Filter bar */}
                                <div className="flex flex-wrap gap-3 mb-4">
                                    <Select value={serialProductFilter} onValueChange={setSerialProductFilter}>
                                        <SelectTrigger className="w-48"><SelectValue placeholder={t('All Products')} /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All Products')}</SelectItem>
                                            {serializedProducts.map(p => (
                                                <SelectItem key={p.id} value={p.id.toString()}>{p.name}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <div className="flex gap-1">
                                        {(['all', 'available', 'sold', 'damaged'] as const).map(s => (
                                            <Button key={s} size="sm" variant={serialFilter === s ? 'default' : 'outline'} onClick={() => setSerialFilter(s)}>
                                                {s === 'all' ? t('All') : t(s.charAt(0).toUpperCase() + s.slice(1))}
                                            </Button>
                                        ))}
                                    </div>
                                    <span className="text-sm text-muted-foreground ml-auto self-center">{filteredSerials.length} {t('records')}</span>
                                </div>
                                {serializedProducts.length === 0 ? (
                                    <div className="text-center py-12 text-muted-foreground">
                                        <QrCode className="h-10 w-10 mx-auto mb-3 opacity-30" />
                                        <p className="font-semibold">{t('No Serialized Products Configured')}</p>
                                        <p className="text-sm mt-1">{t('Go to Products & Services and set Inventory Tracking to "Serialized" to enable this feature.')}</p>
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
                                                                s.status === 'sold' ? 'bg-blue-100 text-blue-800' :
                                                                'bg-red-100 text-red-800'
                                                            }`}>
                                                                {t(s.status.charAt(0).toUpperCase() + s.status.slice(1))}
                                                            </span>
                                                        </TableCell>
                                                        <TableCell className="text-xs text-muted-foreground">{new Date(s.created_at).toLocaleDateString()}</TableCell>
                                                    </TableRow>
                                                ))
                                            )}
                                        </TableBody>
                                    </Table>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                </Tabs>
            </div>
        </AuthenticatedLayout>
    );
}
