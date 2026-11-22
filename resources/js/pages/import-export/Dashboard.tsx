import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Head, router } from "@inertiajs/react";
import { useTranslation } from "react-i18next";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { formatCurrency } from "@/utils/helpers";
import { toast } from "sonner";
import {
    Ship,
    Anchor,
    Coins,
    Calculator,
    Award,
    FileText,
    TrendingUp,
    AlertTriangle,
    CheckCircle2,
    Clock,
    Plus,
    Building2,
    DollarSign,
    RefreshCw,
    Layers,
    ArrowRight,
    ExternalLink,
    ShieldCheck,
    Truck,
    MapPin,
    Compass,
    Radio,
    Search
} from "lucide-react";
import { useState } from "react";
import axios from "axios";

interface IEDashboardProps {
    lcs: any[];
    shipments: any[];
    landedCosts: any[];
    forexQueues: any[];
    djiboutiContainers: any[];
    eccCustomsDuties: any[];
    ecxContracts: any[];
    vendors?: any[];
    purchaseOrders?: any[];
    summary: {
        totalLcValueUsd: number;
        activeShipmentsCount: number;
        pendingForexUsd: number;
        totalDemurrageRiskUsd: number;
        totalExportUsd: number;
    };
}

export default function IEDashboard({
    lcs = [],
    shipments = [],
    landedCosts = [],
    forexQueues = [],
    djiboutiContainers = [],
    eccCustomsDuties = [],
    ecxContracts = [],
    vendors = [],
    purchaseOrders = [],
    summary
}: IEDashboardProps) {
    const { t } = useTranslation();
    const urlParams = new URLSearchParams(window.location.search);
    const initialTab = urlParams.get('tab') || 'forex';

    const [activeTab, setActiveTab] = useState(initialTab);
    const [submitting, setSubmitting] = useState(false);

    // Modal States
    const [forexModalOpen, setForexModalOpen] = useState(false);
    const [djiboutiModalOpen, setDjiboutiModalOpen] = useState(false);
    const [eccModalOpen, setEccModalOpen] = useState(false);
    const [ecxModalOpen, setEcxModalOpen] = useState(false);
    const [lcModalOpen, setLcModalOpen] = useState(false);

    // Live Container Tracking State
    const [trackingModalOpen, setTrackingModalOpen] = useState(false);
    const [trackingData, setTrackingData] = useState<any>(null);
    const [isTrackingLoading, setIsTrackingLoading] = useState(false);
    const [quickContainerInput, setQuickContainerInput] = useState('');

    // Forex Form State
    const [lcNumber, setLcNumber] = useState('');
    const [bankName, setBankName] = useState('Commercial Bank of Ethiopia');
    const [nbeQueueNo, setNbeQueueNo] = useState('');
    const [amountUsd, setAmountUsd] = useState('');
    const [isFrancoValuta, setIsFrancoValuta] = useState(false);
    const [applicationDate, setApplicationDate] = useState('');

    // Djibouti Form State
    const [containerNo, setContainerNo] = useState('');
    const [vesselName, setVesselName] = useState('');
    const [billOfLading, setBillOfLading] = useState('');
    const [dischargeDate, setDischargeDate] = useState('');
    const [freeDays, setFreeDays] = useState('8');
    const [dailyDemurrageUsd, setDailyDemurrageUsd] = useState('50.00');

    // ECC Duty Calculator State
    const [hsCode, setHsCode] = useState('');
    const [hsDescription, setHsDescription] = useState('');
    const [cifValueEtb, setCifValueEtb] = useState('');
    const [dutyRate, setDutyRate] = useState('20');
    const [exciseRate, setExciseRate] = useState('0');
    const [vatRate, setVatRate] = useState('15');
    const [surtaxRate, setSurtaxRate] = useState('10');

    // ECX Export Form State
    const [contractNo, setContractNo] = useState('');
    const [commodityType, setCommodityType] = useState('Yirgacheffe Arabica Coffee');
    const [ecxGrade, setEcxGrade] = useState('Grade 1');
    const [quantityTons, setQuantityTons] = useState('');
    const [contractValUsd, setContractValUsd] = useState('');
    const [destinationCountry, setDestinationCountry] = useState('Germany');

    // New LC Form State
    const [newLcCbeNumber, setNewLcCbeNumber] = useState('');
    const [newLcVendorId, setNewLcVendorId] = useState('');
    const [newLcBank, setNewLcBank] = useState('Commercial Bank of Ethiopia');
    const [newLcAmount, setNewLcAmount] = useState('');
    const [newLcCurrency, setNewLcCurrency] = useState('USD');
    const [newLcRate, setNewLcRate] = useState('125.000000');
    const [newLcExpiry, setNewLcExpiry] = useState('');

    const handleAddForex = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            setSubmitting(true);
            await axios.post(route('settings.import-export.forex.store'), {
                lc_number: lcNumber,
                bank_name: bankName,
                nbe_queue_number: nbeQueueNo,
                amount_usd: parseFloat(amountUsd) || 0,
                is_franco_valuta: isFrancoValuta,
                application_date: applicationDate || new Date().toISOString().split('T')[0],
            });
            setForexModalOpen(false);
            toast.success(t("Forex allocation queue entry saved"));
            router.reload();
        } catch (err: any) {
            toast.error(err.response?.data?.message || t("Failed to save forex entry"));
        } finally {
            setSubmitting(false);
        }
    };

    const handleAddDjibouti = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            setSubmitting(true);
            await axios.post(route('settings.import-export.djibouti.store'), {
                container_number: containerNo,
                vessel_name: vesselName,
                bill_of_lading: billOfLading,
                discharge_date: dischargeDate,
                free_storage_days: parseInt(freeDays) || 8,
                daily_demurrage_usd: parseFloat(dailyDemurrageUsd) || 50.00,
            });
            setDjiboutiModalOpen(false);
            toast.success(t("Container tracking initiated"));
            router.reload();
        } catch (err: any) {
            toast.error(err.response?.data?.message || t("Failed to track container"));
        } finally {
            setSubmitting(false);
        }
    };

    const handleCalculateEcc = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            setSubmitting(true);
            await axios.post(route('settings.import-export.ecc.calculate'), {
                hs_code: hsCode,
                description: hsDescription,
                cif_value_etb: parseFloat(cifValueEtb) || 0,
                duty_rate_percent: parseFloat(dutyRate) || 0,
                excise_rate_percent: parseFloat(exciseRate) || 0,
                vat_percent: parseFloat(vatRate) || 15,
                surtax_percent: parseFloat(surtaxRate) || 10,
                withholding_percent: 3,
            });
            setEccModalOpen(false);
            toast.success(t("ECC Customs duty calculated and stored"));
            router.reload();
        } catch (err: any) {
            toast.error(err.response?.data?.message || t("Calculation failed"));
        } finally {
            setSubmitting(false);
        }
    };

    const handleAddEcx = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            setSubmitting(true);
            await axios.post(route('settings.import-export.ecx.store'), {
                contract_number: contractNo,
                commodity_type: commodityType,
                ecx_grade: ecxGrade,
                quantity_metric_tons: parseFloat(quantityTons) || 0,
                contract_value_usd: parseFloat(contractValUsd) || 0,
                destination_country: destinationCountry,
            });
            setEcxModalOpen(false);
            toast.success(t("ECX export contract saved"));
            router.reload();
        } catch (err: any) {
            toast.error(err.response?.data?.message || t("Failed to record contract"));
        } finally {
            setSubmitting(false);
        }
    };

    const handleCreateLc = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            setSubmitting(true);
            await axios.post(route('settings.import-export.lc.store'), {
                lc_number: newLcCbeNumber,
                vendor_id: newLcVendorId || (vendors[0]?.id || 1),
                issuing_bank: newLcBank,
                amount: parseFloat(newLcAmount) || 0,
                currency: newLcCurrency,
                exchange_rate: parseFloat(newLcRate) || 125,
                tolerance_percent: 5,
                payment_terms: 'LC at sight 100%',
                expiry_date: newLcExpiry,
            });
            setLcModalOpen(false);
            toast.success(t("Letter of Credit opened successfully"));
            router.reload();
        } catch (err: any) {
            toast.error(err.response?.data?.message || t("Failed to open Letter of Credit"));
        } finally {
            setSubmitting(false);
        }
    };

    const handleLiveTrack = async (containerNum: string, shippingLine?: string) => {
        if (!containerNum || !containerNum.trim()) {
            toast.error(t("Please enter a valid container number"));
            return;
        }
        setIsTrackingLoading(true);
        setTrackingModalOpen(true);
        setTrackingData(null);

        try {
            const res = await axios.get(`/settings/import-export/track/${encodeURIComponent(containerNum.trim())}`, {
                params: { shipping_line: shippingLine }
            });
            if (res.data && res.data.success) {
                setTrackingData(res.data);
            } else {
                toast.error(res.data?.message || t("Unable to fetch container tracking data"));
            }
        } catch (err: any) {
            toast.error(err.response?.data?.message || t("Failed to connect to container tracking API"));
        } finally {
            setIsTrackingLoading(false);
        }
    };

    const tradePipelineSteps = [
        { label: t("1. LC & Proforma"), desc: t("Bank issuance / CAD"), icon: FileText, active: lcs.length > 0 },
        { label: t("2. NBE Forex Queue"), desc: t("FX wait / Franco Valuta"), icon: Coins, active: forexQueues.length > 0 },
        { label: t("3. Ocean Transit"), desc: t("Vessel en route to Doraleh"), icon: Ship, active: summary.activeShipmentsCount > 0 },
        { label: t("4. Djibouti Port (DCT)"), desc: t("8 Free Storage Days"), icon: Anchor, active: djiboutiContainers.length > 0 },
        { label: t("5. ECC Duty & Dry Port"), desc: t("Mojo clearance & Landed Cost"), icon: Truck, active: eccCustomsDuties.length > 0 },
    ];

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Dashboard') }, { label: t('Import & Export Operations') }]}
            pageTitle={t('Import & Export Operations Dashboard')}
        >
            <Head title={t('Import & Export Operations')} />

            <div className="space-y-6 max-w-7xl mx-auto pb-12">
                {/* Modern Visual Trade Pipeline */}
                <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs bg-white dark:bg-card overflow-hidden">
                    <CardHeader className="p-4 pb-3 border-b border-slate-100 dark:border-slate-800 flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="text-sm font-bold flex items-center gap-2 text-slate-900 dark:text-slate-100">
                                <Ship className="w-4 h-4 text-emerald-600" />
                                {t('Ethiopian & International Trade Logistics Pipeline')}
                            </CardTitle>
                            <CardDescription className="text-xs text-slate-500 mt-0.5">
                                {t('End-to-end statutory import clearance: Letter of Credit ➔ NBE Forex ➔ Djibouti Port ➔ Mojo Dry Port.')}
                            </CardDescription>
                        </div>
                        <Badge className="bg-emerald-50 text-emerald-700 border-emerald-200 text-[10px]">
                            {t('ESLSE Multimodal Supported')}
                        </Badge>
                    </CardHeader>
                    <CardContent className="p-4">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                            {tradePipelineSteps.map((step, idx) => (
                                <div
                                    key={idx}
                                    className={`p-3 rounded-xl border transition-all ${
                                        step.active
                                            ? 'bg-emerald-50/50 dark:bg-emerald-950/20 border-emerald-300 dark:border-emerald-800'
                                            : 'bg-slate-50/60 dark:bg-slate-900/40 border-slate-200 dark:border-slate-800'
                                    }`}
                                >
                                    <div className="flex items-center gap-2">
                                        <div className={`p-1.5 rounded-lg ${step.active ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600'}`}>
                                            <step.icon className="w-3.5 h-3.5" />
                                        </div>
                                        <div className="text-xs font-bold text-slate-900 dark:text-slate-100 leading-tight">
                                            {step.label}
                                        </div>
                                    </div>
                                    <div className="text-[11px] text-slate-500 mt-1 pl-7">
                                        {step.desc}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* 5 Core Summary KPI Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs hover:border-emerald-300 transition-colors">
                        <CardContent className="p-4 space-y-2">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-medium text-slate-500">{t('Active LCs Total Value')}</span>
                                <div className="p-1.5 rounded-md bg-emerald-50 dark:bg-emerald-950 text-emerald-600">
                                    <FileText className="w-4 h-4" />
                                </div>
                            </div>
                            <div className="text-xl font-bold text-slate-900 dark:text-slate-100">
                                ${summary.totalLcValueUsd.toLocaleString()} <span className="text-xs text-slate-400 font-sans">USD</span>
                            </div>
                            <div className="text-[11px] text-emerald-600 font-medium">
                                {lcs.length} {t('Opened LCs')}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs hover:border-amber-300 transition-colors">
                        <CardContent className="p-4 space-y-2">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-medium text-slate-500">{t('NBE Forex Queue')}</span>
                                <div className="p-1.5 rounded-md bg-amber-50 dark:bg-amber-950 text-amber-600">
                                    <Coins className="w-4 h-4" />
                                </div>
                            </div>
                            <div className="text-xl font-bold text-slate-900 dark:text-slate-100">
                                ${summary.pendingForexUsd.toLocaleString()} <span className="text-xs text-slate-400 font-sans">USD</span>
                            </div>
                            <div className="text-[11px] text-amber-600 font-medium">
                                {t('Pending Bank Allocation')}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs hover:border-rose-300 transition-colors">
                        <CardContent className="p-4 space-y-2">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-medium text-slate-500">{t('Djibouti Demurrage')}</span>
                                <div className="p-1.5 rounded-md bg-rose-50 dark:bg-rose-950 text-rose-600">
                                    <Anchor className="w-4 h-4" />
                                </div>
                            </div>
                            <div className={`text-xl font-bold ${summary.totalDemurrageRiskUsd > 0 ? 'text-rose-600' : 'text-slate-900 dark:text-slate-100'}`}>
                                ${summary.totalDemurrageRiskUsd.toLocaleString()} <span className="text-xs text-slate-400 font-sans">USD</span>
                            </div>
                            <div className="text-[11px] text-rose-600 font-medium">
                                {djiboutiContainers.length} {t('Containers at DCT')}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs hover:border-purple-300 transition-colors">
                        <CardContent className="p-4 space-y-2">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-medium text-slate-500">{t('ECX Coffee Exports')}</span>
                                <div className="p-1.5 rounded-md bg-purple-50 dark:bg-purple-950 text-purple-600">
                                    <Award className="w-4 h-4" />
                                </div>
                            </div>
                            <div className="text-xl font-bold text-slate-900 dark:text-slate-100">
                                ${summary.totalExportUsd.toLocaleString()} <span className="text-xs text-slate-400 font-sans">USD</span>
                            </div>
                            <div className="text-[11px] text-purple-600 font-medium">
                                {ecxContracts.length} {t('Export Contracts')}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs hover:border-emerald-300 transition-colors">
                        <CardContent className="p-4 space-y-2">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-medium text-slate-500">{t('Cargo in Sea Transit')}</span>
                                <div className="p-1.5 rounded-md bg-emerald-50 dark:bg-emerald-950 text-emerald-600">
                                    <TrendingUp className="w-4 h-4" />
                                </div>
                            </div>
                            <div className="text-xl font-bold text-slate-900 dark:text-slate-100">
                                {summary.activeShipmentsCount} <span className="text-xs text-slate-400 font-sans">{t('Vessels')}</span>
                            </div>
                            <div className="text-[11px] text-emerald-600 font-medium">
                                {t('En route to Mojo Dry Port')}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Operations Tabs */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
                    <TabsList className="bg-slate-100 dark:bg-slate-800/60 p-1 rounded-xl border border-slate-200/80 dark:border-slate-700/80 inline-flex flex-wrap gap-1">
                        <TabsTrigger
                            value="forex"
                            className="text-xs data-[state=active]:bg-white dark:data-[state=active]:bg-slate-900 data-[state=active]:text-emerald-700 dark:data-[state=active]:text-emerald-400 data-[state=active]:shadow-xs font-semibold px-3.5 py-1.5 rounded-lg gap-1.5 transition-all"
                        >
                            <Coins className="w-3.5 h-3.5" />
                            {t('NBE Forex Queue Tracker')}
                        </TabsTrigger>
                        <TabsTrigger
                            value="djibouti"
                            className="text-xs data-[state=active]:bg-white dark:data-[state=active]:bg-slate-900 data-[state=active]:text-emerald-700 dark:data-[state=active]:text-emerald-400 data-[state=active]:shadow-xs font-semibold px-3.5 py-1.5 rounded-lg gap-1.5 transition-all"
                        >
                            <Anchor className="w-3.5 h-3.5" />
                            {t('Djibouti Demurrage Meter')}
                        </TabsTrigger>
                        <TabsTrigger
                            value="ecc"
                            className="text-xs data-[state=active]:bg-white dark:data-[state=active]:bg-slate-900 data-[state=active]:text-emerald-700 dark:data-[state=active]:text-emerald-400 data-[state=active]:shadow-xs font-semibold px-3.5 py-1.5 rounded-lg gap-1.5 transition-all"
                        >
                            <Calculator className="w-3.5 h-3.5" />
                            {t('ECC Customs Duty Calculator')}
                        </TabsTrigger>
                        <TabsTrigger
                            value="ecx"
                            className="text-xs data-[state=active]:bg-white dark:data-[state=active]:bg-slate-900 data-[state=active]:text-emerald-700 dark:data-[state=active]:text-emerald-400 data-[state=active]:shadow-xs font-semibold px-3.5 py-1.5 rounded-lg gap-1.5 transition-all"
                        >
                            <Award className="w-3.5 h-3.5" />
                            {t('ECX Coffee Exports')}
                        </TabsTrigger>
                        <TabsTrigger
                            value="lcs"
                            className="text-xs data-[state=active]:bg-white dark:data-[state=active]:bg-slate-900 data-[state=active]:text-emerald-700 dark:data-[state=active]:text-emerald-400 data-[state=active]:shadow-xs font-semibold px-3.5 py-1.5 rounded-lg gap-1.5 transition-all"
                        >
                            <FileText className="w-3.5 h-3.5" />
                            {t('Letters of Credit (LC)')}
                        </TabsTrigger>
                    </TabsList>

                    {/* TAB 1: NBE Forex Queue Tracker */}
                    <TabsContent value="forex" className="space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <h3 className="text-base font-bold text-slate-900 dark:text-slate-100">{t('National Bank of Ethiopia (NBE) Foreign Currency Queue')}</h3>
                                <p className="text-xs text-slate-500 mt-0.5">{t('Tracks commercial bank L/C applications, FX waiting list numbers, and Franco Valuta permits.')}</p>
                            </div>
                            <Button
                                onClick={() => setForexModalOpen(true)}
                                className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9 shadow-xs gap-1.5"
                            >
                                <Plus className="w-4 h-4" />
                                {t('Add Forex Application')}
                            </Button>
                        </div>

                        <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs">
                            <CardContent className="p-0 overflow-x-auto">
                                <table className="w-full text-xs text-left">
                                    <thead className="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 uppercase font-semibold text-[11px]">
                                        <tr>
                                            <th className="p-3">LC Number</th>
                                            <th className="p-3">Commercial Bank</th>
                                            <th className="p-3">NBE Queue No</th>
                                            <th className="p-3 text-right">Amount (USD)</th>
                                            <th className="p-3 text-center">Permit Type</th>
                                            <th className="p-3 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800 font-mono">
                                        {forexQueues.length === 0 ? (
                                            <tr>
                                                <td colSpan={6} className="p-6 text-center text-slate-400 font-sans">
                                                    {t('No NBE forex queue records registered. Click "Add Forex Application" to track.')}
                                                </td>
                                            </tr>
                                        ) : (
                                            forexQueues.map((fq) => (
                                                <tr key={fq.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                                                    <td className="p-3 font-bold text-slate-900 dark:text-slate-100">{fq.lc_number}</td>
                                                    <td className="p-3 text-slate-700 dark:text-slate-300 font-sans">{fq.bank_name}</td>
                                                    <td className="p-3 text-emerald-700 dark:text-emerald-400 font-bold">{fq.nbe_queue_number || '—'}</td>
                                                    <td className="p-3 text-right font-bold text-slate-900 dark:text-slate-100">${parseFloat(fq.amount_usd).toLocaleString()} USD</td>
                                                    <td className="p-3 text-center font-sans">
                                                        {fq.is_franco_valuta ? (
                                                            <Badge className="bg-emerald-50 text-emerald-700 border-emerald-300">Franco Valuta</Badge>
                                                        ) : (
                                                            <Badge className="bg-slate-100 text-slate-600">Bank Allocation</Badge>
                                                        )}
                                                    </td>
                                                    <td className="p-3 text-center font-sans">
                                                        <Badge className={fq.queue_status === 'allocated' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}>
                                                            {fq.queue_status}
                                                        </Badge>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* TAB 2: Djibouti Port Storage & Demurrage */}
                    <TabsContent value="djibouti" className="space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <h3 className="text-base font-bold text-slate-900 dark:text-slate-100">{t('Port of Djibouti Container Free Days & Demurrage Risk Meter')}</h3>
                                <p className="text-xs text-slate-500 mt-0.5">{t('Monitors DCT Djibouti free storage clearance deadline (8 days) to prevent daily USD demurrage penalties.')}</p>
                            </div>
                            <Button
                                onClick={() => setDjiboutiModalOpen(true)}
                                className="bg-rose-600 hover:bg-rose-700 text-white text-xs h-9 shadow-xs gap-1.5"
                            >
                                <Plus className="w-4 h-4" />
                                {t('Add Container Record')}
                            </Button>
                        </div>

                        {/* Live Container GPS Radar Search Bar */}
                        <div className="flex flex-col sm:flex-row items-center justify-between gap-3 p-3.5 bg-gradient-to-r from-emerald-50 via-teal-50 to-slate-50 dark:from-emerald-950/30 dark:via-slate-900 dark:to-slate-900 border border-emerald-200/80 dark:border-emerald-800/40 rounded-xl shadow-2xs">
                            <div className="flex items-center gap-2.5">
                                <div className="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center shadow-xs">
                                    <Compass className="w-4 h-4" />
                                </div>
                                <div>
                                    <h4 className="text-xs font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                        {t('Live Ocean Container GPS Radar')}
                                        <span className="text-[10px] font-semibold text-emerald-700 bg-emerald-100 dark:bg-emerald-900/60 dark:text-emerald-300 px-2 py-0.2 rounded-full border border-emerald-300">
                                            {t('Multi-Carrier GPS')}
                                        </span>
                                    </h4>
                                    <p className="text-[11px] text-slate-500">{t('Track any 20FT/40FT container via MSC, Maersk, CMA CGM, COSCO, or ESLSE through Djibouti.')}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2 w-full sm:w-auto">
                                <Input
                                    type="text"
                                    value={quickContainerInput}
                                    onChange={(e) => setQuickContainerInput(e.target.value.toUpperCase())}
                                    placeholder={t('Enter Container # (e.g. MSCU9284710)')}
                                    className="h-8 text-xs font-mono w-full sm:w-56 bg-white dark:bg-slate-900 border-emerald-300"
                                />
                                <Button
                                    size="sm"
                                    onClick={() => handleLiveTrack(quickContainerInput)}
                                    disabled={!quickContainerInput.trim() || isTrackingLoading}
                                    className="h-8 text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-semibold gap-1.5 shrink-0"
                                >
                                    <Search className="w-3.5 h-3.5" />
                                    {t('Track Live')}
                                </Button>
                            </div>
                        </div>

                        <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs">
                            <CardContent className="p-0 overflow-x-auto">
                                <table className="w-full text-xs text-left">
                                    <thead className="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 uppercase font-semibold text-[11px]">
                                        <tr>
                                            <th className="p-3">Container No</th>
                                            <th className="p-3">Vessel & Bill of Lading</th>
                                            <th className="p-3">Discharge Date</th>
                                            <th className="p-3 text-center">Days Elapsed</th>
                                            <th className="p-3 text-right">Daily Rate</th>
                                            <th className="p-3 text-center">Demurrage Risk Status</th>
                                            <th className="p-3 text-center">{t('Live GPS')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800 font-mono">
                                        {djiboutiContainers.length === 0 ? (
                                            <tr>
                                                <td colSpan={7} className="p-6 text-center text-slate-400 font-sans">
                                                    {t('No containers currently logged at Port of Djibouti. Click "Add Container Record" or use the GPS radar above.')}
                                                </td>
                                            </tr>
                                        ) : (
                                            djiboutiContainers.map((c) => {
                                                const dischargeTime = new Date(c.discharge_date).getTime();
                                                const daysInPort = Math.max(0, Math.floor((new Date().getTime() - dischargeTime) / (1000 * 3600 * 24)));
                                                const freeDaysLimit = c.free_storage_days || 8;
                                                const isOverdue = daysInPort > freeDaysLimit && c.status === 'in_port';
                                                const overdueDays = isOverdue ? daysInPort - freeDaysLimit : 0;
                                                const penaltyTotal = overdueDays * (parseFloat(c.daily_demurrage_usd) || 50);

                                                return (
                                                    <tr key={c.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                                                        <td className="p-3 font-bold text-slate-900 dark:text-slate-100">{c.container_number}</td>
                                                        <td className="p-3 text-slate-700 dark:text-slate-300 font-sans">{c.vessel_name} ({c.bill_of_lading})</td>
                                                        <td className="p-3 text-slate-600 dark:text-slate-400">{c.discharge_date}</td>
                                                        <td className="p-3 text-center font-bold">{daysInPort} / {freeDaysLimit} {t('Days')}</td>
                                                        <td className="p-3 text-right font-bold">${parseFloat(c.daily_demurrage_usd).toFixed(2)}/day</td>
                                                        <td className="p-3 text-center font-sans">
                                                            {isOverdue ? (
                                                                <Badge className="bg-rose-100 text-rose-800 border-rose-300">
                                                                    ⚠️ {overdueDays} Days Overdue (${penaltyTotal.toFixed(2)} USD)
                                                                </Badge>
                                                            ) : (
                                                                <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300">
                                                                    ✓ {Math.max(0, freeDaysLimit - daysInPort)} Days Free Remaining
                                                                </Badge>
                                                            )}
                                                        </td>
                                                        <td className="p-3 text-center font-sans">
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() => handleLiveTrack(c.container_number, c.shipping_line || c.vessel_name)}
                                                                className="h-7 px-2 text-xs text-emerald-700 dark:text-emerald-300 border-emerald-300 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 gap-1 font-semibold"
                                                            >
                                                                <Compass className="w-3.5 h-3.5 text-emerald-600" />
                                                                {t('Track GPS')}
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                );
                                            })
                                        )}
                                    </tbody>
                                </table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* TAB 3: ECC Customs Duty Calculator */}
                    <TabsContent value="ecc" className="space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <h3 className="text-base font-bold text-slate-900 dark:text-slate-100">{t('Ethiopian Customs Commission (ECC) Duty & Tariff Calculator')}</h3>
                                <p className="text-xs text-slate-500 mt-0.5">{t('Computes statutory CIF valuation: Customs Duty (0-35%), Excise, 15% VAT, 10% Surtax, and 3% Withholding.')}</p>
                            </div>
                            <Button
                                onClick={() => setEccModalOpen(true)}
                                className="bg-blue-600 hover:bg-blue-700 text-white text-xs h-9 shadow-xs gap-1.5"
                            >
                                <Calculator className="w-4 h-4" />
                                {t('Calculate HS Code Duty')}
                            </Button>
                        </div>

                        <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs">
                            <CardContent className="p-0 overflow-x-auto">
                                <table className="w-full text-xs text-left">
                                    <thead className="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 uppercase font-semibold text-[11px]">
                                        <tr>
                                            <th className="p-3">HS Code & Description</th>
                                            <th className="p-3 text-right">CIF Value (ETB)</th>
                                            <th className="p-3 text-center">Duty / Excise %</th>
                                            <th className="p-3 text-center">VAT / SurTax %</th>
                                            <th className="p-3 text-right">Total Duty Payable (ETB)</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800 font-mono">
                                        {eccCustomsDuties.length === 0 ? (
                                            <tr>
                                                <td colSpan={5} className="p-6 text-center text-slate-400 font-sans">
                                                    {t('No ECC customs duty assessments calculated yet.')}
                                                </td>
                                            </tr>
                                        ) : (
                                            eccCustomsDuties.map((d) => (
                                                <tr key={d.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                                                    <td className="p-3">
                                                        <div className="font-bold text-slate-900 dark:text-slate-100">{d.hs_code}</div>
                                                        <div className="text-[10px] text-slate-500 font-sans">{d.description}</div>
                                                    </td>
                                                    <td className="p-3 text-right font-bold text-slate-900 dark:text-slate-100">{formatCurrency(d.cif_value_etb)}</td>
                                                    <td className="p-3 text-center">Duty {d.duty_rate_percent}% | Excise {d.excise_rate_percent}%</td>
                                                    <td className="p-3 text-center">VAT {d.vat_percent}% | SurTax {d.surtax_percent}%</td>
                                                    <td className="p-3 text-right font-extrabold text-blue-700 dark:text-blue-400">{formatCurrency(d.total_duty_payable_etb)}</td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* TAB 4: ECX & Coffee Export Contracts */}
                    <TabsContent value="ecx" className="space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <h3 className="text-base font-bold text-slate-900 dark:text-slate-100">{t('Ethiopian Commodity Exchange (ECX) & Coffee Export Contracts')}</h3>
                                <p className="text-xs text-slate-500 mt-0.5">{t('Registers coffee and commodity export contracts, ECX Quality Grades (1-5), and foreign currency values.')}</p>
                            </div>
                            <Button
                                onClick={() => setEcxModalOpen(true)}
                                className="bg-purple-600 hover:bg-purple-700 text-white text-xs h-9 shadow-xs gap-1.5"
                            >
                                <Plus className="w-4 h-4" />
                                {t('Record Export Contract')}
                            </Button>
                        </div>

                        <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs">
                            <CardContent className="p-0 overflow-x-auto">
                                <table className="w-full text-xs text-left">
                                    <thead className="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 uppercase font-semibold text-[11px]">
                                        <tr>
                                            <th className="p-3">Contract No</th>
                                            <th className="p-3">Commodity & ECX Grade</th>
                                            <th className="p-3 text-center">Quantity (Tons)</th>
                                            <th className="p-3 text-right">Value (USD)</th>
                                            <th className="p-3 text-center">Destination</th>
                                            <th className="p-3 text-center">L/C Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800 font-mono">
                                        {ecxContracts.length === 0 ? (
                                            <tr>
                                                <td colSpan={6} className="p-6 text-center text-slate-400 font-sans">
                                                    {t('No export contracts recorded yet.')}
                                                </td>
                                            </tr>
                                        ) : (
                                            ecxContracts.map((e) => (
                                                <tr key={e.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                                                    <td className="p-3 font-bold text-slate-900 dark:text-slate-100">{e.contract_number}</td>
                                                    <td className="p-3 font-sans">
                                                        <div className="font-bold text-slate-900 dark:text-slate-100">{e.commodity_type}</div>
                                                        <Badge className="bg-purple-50 text-purple-700 border-purple-200 text-[9px]">{e.ecx_grade}</Badge>
                                                    </td>
                                                    <td className="p-3 text-center font-bold">{parseFloat(e.quantity_metric_tons).toLocaleString()} MT</td>
                                                    <td className="p-3 text-right font-extrabold text-emerald-700 dark:text-emerald-400">${parseFloat(e.contract_value_usd).toLocaleString()} USD</td>
                                                    <td className="p-3 text-center font-sans font-medium">{e.destination_country}</td>
                                                    <td className="p-3 text-center font-sans">
                                                        <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300">{e.lc_status}</Badge>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* TAB 5: Letters of Credit & Landed Costs */}
                    <TabsContent value="lcs" className="space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <h3 className="text-base font-bold text-slate-900 dark:text-slate-100">{t('Letters of Credit (LC) & Landed Costs Registry')}</h3>
                                <p className="text-xs text-slate-500 mt-0.5">{t('Tracks international LCs, bank exchange rates, latest shipment deadlines, and freight landed costs.')}</p>
                            </div>
                            <Button
                                onClick={() => setLcModalOpen(true)}
                                className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9 shadow-xs gap-1.5"
                            >
                                <Plus className="w-4 h-4" />
                                {t('Open Letter of Credit (LC)')}
                            </Button>
                        </div>

                        <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs">
                            <CardContent className="p-0 overflow-x-auto">
                                <table className="w-full text-xs text-left">
                                    <thead className="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 font-semibold text-[11px] uppercase">
                                        <tr>
                                            <th className="p-3">LC Number</th>
                                            <th className="p-3">Issuing Bank</th>
                                            <th className="p-3 text-right">Amount</th>
                                            <th className="p-3 text-center">Exchange Rate</th>
                                            <th className="p-3 text-center">Expiry Date</th>
                                            <th className="p-3 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800 font-mono">
                                        {lcs.length === 0 ? (
                                            <tr>
                                                <td colSpan={6} className="p-6 text-center text-slate-400 font-sans">
                                                    {t('No Letters of Credit opened yet. Click "Open Letter of Credit" to create.')}
                                                </td>
                                            </tr>
                                        ) : (
                                            lcs.map((lc) => (
                                                <tr key={lc.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                                                    <td className="p-3 font-bold text-slate-900 dark:text-slate-100">{lc.lc_number}</td>
                                                    <td className="p-3 font-sans text-slate-700 dark:text-slate-300">{lc.issuing_bank}</td>
                                                    <td className="p-3 text-right font-bold text-slate-900 dark:text-slate-100">
                                                        ${parseFloat(lc.amount).toLocaleString()} {lc.currency}
                                                    </td>
                                                    <td className="p-3 text-center text-slate-500">{parseFloat(lc.exchange_rate).toFixed(2)} ETB</td>
                                                    <td className="p-3 text-center text-slate-600 dark:text-slate-400">{lc.expiry_date}</td>
                                                    <td className="p-3 text-center font-sans">
                                                        <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300">{lc.status}</Badge>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>

            {/* Forex Modal */}
            <Dialog open={forexModalOpen} onOpenChange={setForexModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Coins className="w-4 h-4 text-emerald-600" />
                            {t('Add NBE Forex Queue Entry')}
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            {t('Track foreign currency allocation queue with commercial banks.')}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleAddForex} className="space-y-3 pt-2">
                        <div className="space-y-1">
                            <Label className="text-xs">{t('LC Number')}</Label>
                            <Input value={lcNumber} onChange={(e) => setLcNumber(e.target.value)} placeholder="LC-CBE-2026-99" required className="h-9 text-xs" />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">{t('Commercial Bank Name')}</Label>
                            <Input value={bankName} onChange={(e) => setBankName(e.target.value)} required className="h-9 text-xs" />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">{t('NBE Queue Number')}</Label>
                            <Input value={nbeQueueNo} onChange={(e) => setNbeQueueNo(e.target.value)} placeholder="NBE-FX-9500" className="h-9 text-xs" />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">{t('Amount USD')}</Label>
                            <Input type="number" value={amountUsd} onChange={(e) => setAmountUsd(e.target.value)} placeholder="100000" required className="h-9 text-xs" />
                        </div>
                        <div className="flex items-center gap-2 pt-1">
                            <input
                                type="checkbox"
                                id="isFranco"
                                checked={isFrancoValuta}
                                onChange={(e) => setIsFrancoValuta(e.target.checked)}
                                className="rounded border-slate-300 text-emerald-600"
                            />
                            <Label htmlFor="isFranco" className="text-xs cursor-pointer">{t('Franco Valuta Permit (No Domestic FX Queue)')}</Label>
                        </div>
                        <DialogFooter className="pt-2">
                            <Button type="submit" disabled={submitting} className="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9">
                                {submitting ? t('Saving...') : t('Save Forex Record')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Djibouti Container Modal */}
            <Dialog open={djiboutiModalOpen} onOpenChange={setDjiboutiModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Anchor className="w-4 h-4 text-rose-600" />
                            {t('Track Djibouti Port Container')}
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            {t('Monitor Port of Doraleh free storage days (8 days limit).')}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleAddDjibouti} className="space-y-3 pt-2">
                        <div className="space-y-1">
                            <Label className="text-xs">{t('Container Number')}</Label>
                            <Input value={containerNo} onChange={(e) => setContainerNo(e.target.value)} placeholder="MSCU1234567" required className="h-9 text-xs" />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">{t('Vessel Name')}</Label>
                            <Input value={vesselName} onChange={(e) => setVesselName(e.target.value)} placeholder="MSC ALICE" required className="h-9 text-xs" />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">{t('Bill of Lading Number')}</Label>
                            <Input value={billOfLading} onChange={(e) => setBillOfLading(e.target.value)} placeholder="BL-99482" required className="h-9 text-xs" />
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <div className="space-y-1">
                                <Label className="text-xs">{t('Discharge Date')}</Label>
                                <Input type="date" value={dischargeDate} onChange={(e) => setDischargeDate(e.target.value)} required className="h-9 text-xs" />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs">{t('Daily Demurrage ($)')}</Label>
                                <Input type="number" value={dailyDemurrageUsd} onChange={(e) => setDailyDemurrageUsd(e.target.value)} placeholder="50.00" required className="h-9 text-xs" />
                            </div>
                        </div>
                        <DialogFooter className="pt-2">
                            <Button type="submit" disabled={submitting} className="w-full bg-rose-600 hover:bg-rose-700 text-white text-xs h-9">
                                {submitting ? t('Saving...') : t('Start Tracking Container')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* ECC Duty Modal */}
            <Dialog open={eccModalOpen} onOpenChange={setEccModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Calculator className="w-4 h-4 text-blue-600" />
                            {t('Calculate ECC Customs Duty')}
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            {t('Computes Customs Duty, 15% VAT, 10% Surtax, and 3% Withholding.')}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleCalculateEcc} className="space-y-3 pt-2">
                        <div className="space-y-1">
                            <Label className="text-xs">{t('HS Code')}</Label>
                            <Input value={hsCode} onChange={(e) => setHsCode(e.target.value)} placeholder="8471.3000" required className="h-9 text-xs" />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">{t('Goods Description')}</Label>
                            <Input value={hsDescription} onChange={(e) => setHsDescription(e.target.value)} placeholder="Computers & Electronics" required className="h-9 text-xs" />
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <div className="space-y-1">
                                <Label className="text-xs">{t('CIF Value (ETB)')}</Label>
                                <Input type="number" value={cifValueEtb} onChange={(e) => setCifValueEtb(e.target.value)} placeholder="1000000" required className="h-9 text-xs" />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs">{t('Customs Duty (%)')}</Label>
                                <Input type="number" value={dutyRate} onChange={(e) => setDutyRate(e.target.value)} placeholder="20" required className="h-9 text-xs" />
                            </div>
                        </div>
                        <DialogFooter className="pt-2">
                            <Button type="submit" disabled={submitting} className="w-full bg-blue-600 hover:bg-blue-700 text-white text-xs h-9">
                                {submitting ? t('Calculating...') : t('Calculate & Store Duty')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* ECX Export Modal */}
            <Dialog open={ecxModalOpen} onOpenChange={setEcxModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Award className="w-4 h-4 text-purple-600" />
                            {t('Record ECX Export Contract')}
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            {t('Register export contracts for coffee, sesame, or pulses.')}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleAddEcx} className="space-y-3 pt-2">
                        <div className="space-y-1">
                            <Label className="text-xs">{t('Contract Number')}</Label>
                            <Input value={contractNo} onChange={(e) => setContractNo(e.target.value)} placeholder="ECX-EXP-2026-99" required className="h-9 text-xs" />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">{t('Commodity Type')}</Label>
                            <Input value={commodityType} onChange={(e) => setCommodityType(e.target.value)} placeholder="Sidamo Arabica Coffee" required className="h-9 text-xs" />
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <div className="space-y-1">
                                <Label className="text-xs">{t('Quantity (Tons)')}</Label>
                                <Input type="number" value={quantityTons} onChange={(e) => setQuantityTons(e.target.value)} placeholder="20" required className="h-9 text-xs" />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs">{t('Contract Value (USD)')}</Label>
                                <Input type="number" value={contractValUsd} onChange={(e) => setContractValUsd(e.target.value)} placeholder="150000" required className="h-9 text-xs" />
                            </div>
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">{t('Destination Country')}</Label>
                            <Input value={destinationCountry} onChange={(e) => setDestinationCountry(e.target.value)} placeholder="Germany" required className="h-9 text-xs" />
                        </div>
                        <DialogFooter className="pt-2">
                            <Button type="submit" disabled={submitting} className="w-full bg-purple-600 hover:bg-purple-700 text-white text-xs h-9">
                                {submitting ? t('Saving...') : t('Record Export Contract')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Open LC Modal */}
            <Dialog open={lcModalOpen} onOpenChange={setLcModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <FileText className="w-4 h-4 text-emerald-600" />
                            {t('Open Letter of Credit (LC)')}
                        </DialogTitle>
                        <DialogDescription className="text-xs">
                            {t('Register an international LC governed by UCP 600 regulations.')}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleCreateLc} className="space-y-3 pt-2">
                        <div className="space-y-1">
                            <Label className="text-xs">{t('LC Number')}</Label>
                            <Input value={newLcCbeNumber} onChange={(e) => setNewLcCbeNumber(e.target.value)} placeholder="LC-2026-CBE-001" required className="h-9 text-xs" />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">{t('Issuing Bank')}</Label>
                            <Input value={newLcBank} onChange={(e) => setNewLcBank(e.target.value)} placeholder="Commercial Bank of Ethiopia" required className="h-9 text-xs" />
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <div className="space-y-1">
                                <Label className="text-xs">{t('Amount')}</Label>
                                <Input type="number" value={newLcAmount} onChange={(e) => setNewLcAmount(e.target.value)} placeholder="50000" required className="h-9 text-xs" />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs">{t('Currency')}</Label>
                                <select
                                    value={newLcCurrency}
                                    onChange={(e) => setNewLcCurrency(e.target.value)}
                                    className="w-full h-9 border border-slate-200 dark:border-slate-700 rounded-md text-xs px-2.5 bg-white dark:bg-slate-900"
                                >
                                    <option value="USD">USD ($)</option>
                                    <option value="EUR">EUR (€)</option>
                                    <option value="GBP">GBP (£)</option>
                                    <option value="CNY">CNY (¥)</option>
                                </select>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <div className="space-y-1">
                                <Label className="text-xs">{t('Exchange Rate (ETB)')}</Label>
                                <Input type="number" step="0.01" value={newLcRate} onChange={(e) => setNewLcRate(e.target.value)} placeholder="125.00" required className="h-9 text-xs" />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs">{t('Expiry Date')}</Label>
                                <Input type="date" value={newLcExpiry} onChange={(e) => setNewLcExpiry(e.target.value)} required className="h-9 text-xs" />
                            </div>
                        </div>
                        <DialogFooter className="pt-2">
                            <Button type="submit" disabled={submitting} className="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9">
                                {submitting ? t('Saving...') : t('Open Letter of Credit')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Live Container GPS Tracking Modal */}
            <Dialog open={trackingModalOpen} onOpenChange={setTrackingModalOpen}>
                <DialogContent className="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader className="border-b pb-3">
                        <div className="flex items-center justify-between">
                            <DialogTitle className="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100">
                                <Compass className="w-5 h-5 text-emerald-600 animate-spin-slow" />
                                {t('Ocean Container Real-Time Telemetry')}
                            </DialogTitle>
                            {trackingData && (
                                <Badge className="bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border-emerald-300 font-mono text-xs">
                                    {trackingData.container_number}
                                </Badge>
                            )}
                        </div>
                        <DialogDescription className="text-xs text-slate-500">
                            {t('Live milestone updates from origin port across the ocean route to Port of Djibouti (Doraleh) and Modjo dry port.')}
                        </DialogDescription>
                    </DialogHeader>

                    {isTrackingLoading ? (
                        <div className="py-12 flex flex-col items-center justify-center space-y-3">
                            <RefreshCw className="w-8 h-8 text-emerald-600 animate-spin" />
                            <p className="text-xs font-semibold text-slate-600">{t('Connecting to Ocean Carrier Satellite Telemetry...')}</p>
                            <span className="text-[11px] text-slate-400 font-mono">{t('Querying vessel IMO and container bay checkpoints...')}</span>
                        </div>
                    ) : trackingData ? (
                        <div className="space-y-4 pt-2">
                            {/* Vessel & Carrier Header Banner */}
                            <div className="p-3.5 rounded-xl bg-slate-900 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm border border-slate-800">
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <Ship className="w-4 h-4 text-emerald-400" />
                                        <h4 className="text-sm font-bold text-slate-100">
                                            {trackingData.vessel?.name || 'Vessel'}
                                        </h4>
                                        <span className="text-[11px] font-mono text-slate-400">
                                            (Voyage: {trackingData.vessel?.voyage || 'V-1'})
                                        </span>
                                    </div>
                                    <p className="text-xs text-slate-300">
                                        Carrier: <strong className="text-emerald-400">{trackingData.carrier?.name}</strong> • IMO: <span className="font-mono text-slate-400">{trackingData.vessel?.imo}</span>
                                    </p>
                                </div>
                                <div className="sm:text-right">
                                    <Badge className="bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 text-xs font-bold uppercase">
                                        {trackingData.status_label}
                                    </Badge>
                                    <span className="text-[10px] text-slate-400 block mt-1 font-mono">
                                        ETA Djibouti: {trackingData.timestamps?.eta_djibouti || 'In Transit'}
                                    </span>
                                </div>
                            </div>

                            {/* 4-Stage Visual Route Stepper */}
                            <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800">
                                <h5 className="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-3">{t('Ocean Corridor Journey Progress')}</h5>
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-2 text-center text-xs">
                                    <div className="p-2 rounded-lg bg-white dark:bg-slate-900 border border-emerald-300 shadow-2xs">
                                        <div className="w-2 h-2 rounded-full bg-emerald-500 mx-auto mb-1" />
                                        <span className="font-bold text-slate-900 dark:text-slate-100 block text-[11px]">{t('1. Origin Port')}</span>
                                        <span className="text-[10px] text-slate-500 truncate block">{trackingData.route?.origin}</span>
                                    </div>
                                    <div className="p-2 rounded-lg bg-white dark:bg-slate-900 border border-emerald-300 shadow-2xs">
                                        <div className="w-2 h-2 rounded-full bg-emerald-500 mx-auto mb-1" />
                                        <span className="font-bold text-slate-900 dark:text-slate-100 block text-[11px]">{t('2. Transshipment')}</span>
                                        <span className="text-[10px] text-slate-500 truncate block">{trackingData.route?.transshipment}</span>
                                    </div>
                                    <div className="p-2 rounded-lg bg-white dark:bg-slate-900 border border-emerald-400 ring-2 ring-emerald-500/20 shadow-2xs">
                                        <div className="w-2 h-2 rounded-full bg-emerald-600 mx-auto mb-1 animate-ping" />
                                        <span className="font-bold text-emerald-700 dark:text-emerald-400 block text-[11px]">{t('3. Port of Djibouti')}</span>
                                        <span className="text-[10px] text-slate-500 truncate block">Doraleh (DCT)</span>
                                    </div>
                                    <div className="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xs">
                                        <div className="w-2 h-2 rounded-full bg-slate-300 dark:bg-slate-600 mx-auto mb-1" />
                                        <span className="font-bold text-slate-700 dark:text-slate-300 block text-[11px]">{t('4. Inland Dry Port')}</span>
                                        <span className="text-[10px] text-slate-500 truncate block">{trackingData.tenant_record?.dry_port_destination || 'Modjo Dry Port'}</span>
                                    </div>
                                </div>
                            </div>

                            {/* Demurrage Watch Meter */}
                            <div className="p-3 rounded-xl bg-amber-50/70 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/40 flex items-center justify-between text-xs">
                                <div className="flex items-center gap-2">
                                    <Clock className="w-4 h-4 text-amber-600 shrink-0" />
                                    <div>
                                        <strong className="text-amber-900 dark:text-amber-300 block">{t('Djibouti Port Free Storage Window')}</strong>
                                        <span className="text-[11px] text-amber-700 dark:text-amber-400">
                                            {trackingData.demurrage?.free_days || 8} {t('days total free time')} • {trackingData.demurrage?.days_in_port || 0} {t('days elapsed')}
                                        </span>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <span className="font-bold text-sm text-emerald-700 dark:text-emerald-400 block">
                                        {trackingData.demurrage?.days_remaining} {t('Free Days Left')}
                                    </span>
                                    <span className="text-[10px] text-slate-500">
                                        ${trackingData.demurrage?.daily_rate_usd || 45} USD/day demurrage
                                    </span>
                                </div>
                            </div>

                            {/* Milestones Event History */}
                            {trackingData.milestones && trackingData.milestones.length > 0 && (
                                <div className="space-y-2">
                                    <h5 className="text-xs font-bold text-slate-900 dark:text-slate-100 flex items-center gap-1.5">
                                        <Radio className="w-3.5 h-3.5 text-emerald-600" />
                                        {t('Carrier Event Milestone Log')}
                                    </h5>
                                    <div className="space-y-2 max-h-52 overflow-y-auto pr-1">
                                        {trackingData.milestones.map((m: any, idx: number) => (
                                            <div key={idx} className="flex items-start gap-2.5 p-2 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs">
                                                <div className={`w-2 h-2 rounded-full mt-1.5 shrink-0 ${idx === 0 ? 'bg-emerald-500 ring-2 ring-emerald-300' : 'bg-slate-400'}`} />
                                                <div className="flex-1">
                                                    <div className="flex items-center justify-between">
                                                        <strong className="text-slate-800 dark:text-slate-200">{m.title}</strong>
                                                        <span className="text-slate-500 font-mono text-[10px]">{m.date}</span>
                                                    </div>
                                                    <p className="text-[11px] text-slate-600 dark:text-slate-400 mt-0.5">{m.description}</p>
                                                    <span className="text-[10px] font-semibold text-emerald-700 dark:text-emerald-400 block mt-0.5">
                                                        📍 {m.location}
                                                    </span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Provider Attribution & Close */}
                            <div className="pt-2 border-t flex items-center justify-between text-[11px] text-slate-400">
                                <span className="font-mono">
                                    Engine: <strong className="text-slate-600 dark:text-slate-300">{trackingData.provider}</strong>
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setTrackingModalOpen(false)}
                                    className="h-8 text-xs"
                                >
                                    {t('Close')}
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <div className="py-8 text-center text-xs text-slate-400">
                            {t('No telemetry records available for this container.')}
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
