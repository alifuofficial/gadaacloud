import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Head, useForm, router } from "@inertiajs/react";
import { useTranslation } from "react-i18next";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { formatCurrency } from "@/utils/helpers";
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
    RefreshCw
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
    summary: {
        totalLcValueUsd: number;
        activeShipmentsCount: number;
        pendingForexUsd: number;
        totalDemurrageRiskUsd: number;
        totalExportUsd: number;
    };
}

export default function IEDashboard({
    lcs,
    shipments,
    landedCosts,
    forexQueues,
    djiboutiContainers,
    eccCustomsDuties,
    ecxContracts,
    summary
}: IEDashboardProps) {
    const { t } = useTranslation();
    const urlParams = new URLSearchParams(window.location.search);
    const initialTab = urlParams.get('tab') || 'forex';

    const [activeTab, setActiveTab] = useState(initialTab);

    // Modal States
    const [forexModalOpen, setForexModalOpen] = useState(false);
    const [djiboutiModalOpen, setDjiboutiModalOpen] = useState(false);
    const [eccModalOpen, setEccModalOpen] = useState(false);
    const [ecxModalOpen, setEcxModalOpen] = useState(false);

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

    const handleAddForex = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            await axios.post(route('settings.import-export.forex.store'), {
                lc_number: lcNumber,
                bank_name: bankName,
                nbe_queue_number: nbeQueueNo,
                amount_usd: parseFloat(amountUsd) || 0,
                is_franco_valuta: isFrancoValuta,
                application_date: applicationDate || new Date().toISOString().split('T')[0],
            });
            setForexModalOpen(false);
            router.reload();
        } catch (err) {
            console.error(err);
        }
    };

    const handleAddDjibouti = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            await axios.post(route('settings.import-export.djibouti.store'), {
                container_number: containerNo,
                vessel_name: vesselName,
                bill_of_lading: billOfLading,
                discharge_date: dischargeDate,
                free_storage_days: parseInt(freeDays) || 8,
                daily_demurrage_usd: parseFloat(dailyDemurrageUsd) || 50.00,
            });
            setDjiboutiModalOpen(false);
            router.reload();
        } catch (err) {
            console.error(err);
        }
    };

    const handleCalculateEcc = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
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
            router.reload();
        } catch (err) {
            console.error(err);
        }
    };

    const handleAddEcx = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            await axios.post(route('settings.import-export.ecx.store'), {
                contract_number: contractNo,
                commodity_type: commodityType,
                ecx_grade: ecxGrade,
                quantity_metric_tons: parseFloat(quantityTons) || 0,
                contract_value_usd: parseFloat(contractValUsd) || 0,
                destination_country: destinationCountry,
            });
            setEcxModalOpen(false);
            router.reload();
        } catch (err) {
            console.error(err);
        }
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Dashboard') }, { label: t('IE Dashboard') }]}
            pageTitle={t('Import & Export Operations Dashboard (Ethiopia Trade)')}
        >
            <Head title={t('IE Dashboard')} />

            <div className="space-y-6 max-w-7xl mx-auto">
                {/* Hero Summary Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <Card className="bg-slate-900 text-white border-slate-800">
                        <CardContent className="p-4 space-y-1">
                            <div className="text-xs text-slate-400 font-semibold flex items-center gap-1">
                                <Ship className="w-4 h-4 text-blue-400" />
                                {t('Active LCs Total Value')}
                            </div>
                            <div className="text-xl font-black text-white">${summary.totalLcValueUsd.toLocaleString()} USD</div>
                            <div className="text-[10px] text-slate-400 font-mono">{lcs.length} Opened LCs</div>
                        </CardContent>
                    </Card>

                    <Card className="bg-gradient-to-br from-amber-500/10 to-amber-600/5 border-amber-300">
                        <CardContent className="p-4 space-y-1">
                            <div className="text-xs text-amber-800 font-semibold flex items-center gap-1">
                                <Coins className="w-4 h-4 text-amber-600" />
                                {t('NBE Forex Queue')}
                            </div>
                            <div className="text-xl font-black text-amber-950">${summary.pendingForexUsd.toLocaleString()} USD</div>
                            <div className="text-[10px] text-amber-700">Pending Bank Allocation</div>
                        </CardContent>
                    </Card>

                    <Card className="bg-gradient-to-br from-rose-500/10 to-rose-600/5 border-rose-300">
                        <CardContent className="p-4 space-y-1">
                            <div className="text-xs text-rose-800 font-semibold flex items-center gap-1">
                                <Anchor className="w-4 h-4 text-rose-600" />
                                {t('Djibouti Demurrage Risk')}
                            </div>
                            <div className="text-xl font-black text-rose-950">${summary.totalDemurrageRiskUsd.toLocaleString()} USD</div>
                            <div className="text-[10px] text-rose-700">{djiboutiContainers.length} Containers at DCT</div>
                        </CardContent>
                    </Card>

                    <Card className="bg-gradient-to-br from-purple-500/10 to-purple-600/5 border-purple-300">
                        <CardContent className="p-4 space-y-1">
                            <div className="text-xs text-purple-800 font-semibold flex items-center gap-1">
                                <Award className="w-4 h-4 text-purple-600" />
                                {t('ECX Coffee Exports')}
                            </div>
                            <div className="text-xl font-black text-purple-950">${summary.totalExportUsd.toLocaleString()} USD</div>
                            <div className="text-[10px] text-purple-700">{ecxContracts.length} Export Contracts</div>
                        </CardContent>
                    </Card>

                    <Card className="bg-gradient-to-br from-emerald-500/10 to-emerald-600/5 border-emerald-300">
                        <CardContent className="p-4 space-y-1">
                            <div className="text-xs text-emerald-800 font-semibold flex items-center gap-1">
                                <TrendingUp className="w-4 h-4 text-emerald-600" />
                                {t('Sea Cargo in Transit')}
                            </div>
                            <div className="text-xl font-black text-emerald-950">{summary.activeShipmentsCount} Vessels</div>
                            <div className="text-[10px] text-emerald-700">En route to Mojo Dry Port</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Operations Tabs */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
                    <TabsList className="bg-slate-900 p-1.5 rounded-2xl border border-slate-800 flex items-center gap-1 overflow-x-auto no-scrollbar">
                        <TabsTrigger value="forex" className="text-xs data-[state=active]:bg-purple-600 data-[state=active]:text-white gap-1.5">
                            <Coins className="w-3.5 h-3.5" />
                            {t('NBE Forex Queue Tracker')}
                        </TabsTrigger>
                        <TabsTrigger value="djibouti" className="text-xs data-[state=active]:bg-purple-600 data-[state=active]:text-white gap-1.5">
                            <Anchor className="w-3.5 h-3.5" />
                            {t('Djibouti Demurrage Meter')}
                        </TabsTrigger>
                        <TabsTrigger value="ecc" className="text-xs data-[state=active]:bg-purple-600 data-[state=active]:text-white gap-1.5">
                            <Calculator className="w-3.5 h-3.5" />
                            {t('ECC Customs Duty Calculator')}
                        </TabsTrigger>
                        <TabsTrigger value="ecx" className="text-xs data-[state=active]:bg-purple-600 data-[state=active]:text-white gap-1.5">
                            <Award className="w-3.5 h-3.5" />
                            {t('ECX Coffee Exports')}
                        </TabsTrigger>
                        <TabsTrigger value="lcs" className="text-xs data-[state=active]:bg-purple-600 data-[state=active]:text-white gap-1.5">
                            <FileText className="w-3.5 h-3.5" />
                            {t('Letters of Credit (LC)')}
                        </TabsTrigger>
                    </TabsList>

                    {/* TAB 1: NBE Forex Queue Tracker */}
                    <TabsContent value="forex" className="space-y-4">
                        <div className="flex justify-between items-center">
                            <div>
                                <h3 className="text-base font-bold text-gray-900">{t('National Bank of Ethiopia (NBE) Foreign Currency Queue')}</h3>
                                <p className="text-xs text-gray-500">{t('Tracks commercial bank L/C applications, FX waiting list numbers, and Franco Valuta permits.')}</p>
                            </div>
                            <Button onClick={() => setForexModalOpen(true)} className="bg-amber-600 hover:bg-amber-700 text-white">
                                <Plus className="w-4 h-4 mr-1.5" />
                                {t('Add Forex Application')}
                            </Button>
                        </div>

                        <Card className="border">
                            <CardContent className="p-0 overflow-x-auto">
                                <table className="w-full text-xs text-left">
                                    <thead className="bg-slate-50 border-b text-gray-600 uppercase font-semibold text-[11px]">
                                        <tr>
                                            <th className="p-3">LC Number</th>
                                            <th className="p-3">Commercial Bank</th>
                                            <th className="p-3">NBE Queue No</th>
                                            <th className="p-3 text-right">Amount (USD)</th>
                                            <th className="p-3 text-center">Franco Valuta</th>
                                            <th className="p-3 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 font-mono">
                                        {forexQueues.map((fq) => (
                                            <tr key={fq.id} className="hover:bg-slate-50">
                                                <td className="p-3 font-bold text-slate-900">{fq.lc_number}</td>
                                                <td className="p-3 text-slate-700">{fq.bank_name}</td>
                                                <td className="p-3 text-purple-700 font-bold">{fq.nbe_queue_number}</td>
                                                <td className="p-3 text-right font-bold text-slate-900">${parseFloat(fq.amount_usd).toLocaleString()} USD</td>
                                                <td className="p-3 text-center font-sans">
                                                    {fq.is_franco_valuta ? (
                                                        <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300">Yes (Franco Valuta)</Badge>
                                                    ) : (
                                                        <span className="text-gray-400">—</span>
                                                    )}
                                                </td>
                                                <td className="p-3 text-center font-sans">
                                                    <Badge className={fq.queue_status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}>
                                                        {fq.queue_status}
                                                    </Badge>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* TAB 2: Djibouti Port Storage & Demurrage */}
                    <TabsContent value="djibouti" className="space-y-4">
                        <div className="flex justify-between items-center">
                            <div>
                                <h3 className="text-base font-bold text-gray-900">{t('Port of Djibouti Container Free Days & Demurrage Risk Meter')}</h3>
                                <p className="text-xs text-gray-500">{t('Monitors DCT Djibouti free storage clearance deadline to prevent daily USD demurrage fines.')}</p>
                            </div>
                            <Button onClick={() => setDjiboutiModalOpen(true)} className="bg-rose-600 hover:bg-rose-700 text-white">
                                <Plus className="w-4 h-4 mr-1.5" />
                                {t('Track Container')}
                            </Button>
                        </div>

                        <Card className="border">
                            <CardContent className="p-0 overflow-x-auto">
                                <table className="w-full text-xs text-left">
                                    <thead className="bg-slate-50 border-b text-gray-600 uppercase font-semibold text-[11px]">
                                        <tr>
                                            <th className="p-3">Container No</th>
                                            <th className="p-3">Vessel & Bill of Lading</th>
                                            <th className="p-3">Discharge Date</th>
                                            <th className="p-3 text-center">Days Elapsed</th>
                                            <th className="p-3 text-right">Daily Demurrage Rate</th>
                                            <th className="p-3 text-center">Demurrage Risk Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 font-mono">
                                        {djiboutiContainers.map((c) => {
                                            const daysInPort = Math.max(0, Math.floor((new Date().getTime() - new Date(c.discharge_date).getTime()) / (1000 * 3600 * 24)));
                                            const isOverdue = daysInPort > c.free_storage_days;
                                            return (
                                                <tr key={c.id} className="hover:bg-slate-50">
                                                    <td className="p-3 font-bold text-slate-900">{c.container_number}</td>
                                                    <td className="p-3 text-slate-700">{c.vessel_name} ({c.bill_of_lading})</td>
                                                    <td className="p-3 text-slate-600">{c.discharge_date}</td>
                                                    <td className="p-3 text-center font-bold">{daysInPort} / {c.free_storage_days} Free Days</td>
                                                    <td className="p-3 text-right font-bold">${c.daily_demurrage_usd}/day</td>
                                                    <td className="p-3 text-center font-sans">
                                                        {isOverdue ? (
                                                            <Badge className="bg-rose-100 text-rose-800 border-rose-300">
                                                                ⚠️ {daysInPort - c.free_storage_days} Days Overdue (${(daysInPort - c.free_storage_days) * c.daily_demurrage_usd} Penalty)
                                                            </Badge>
                                                        ) : (
                                                            <Badge className="bg-emerald-100 text-emerald-800 border-emerald-300">
                                                                ✓ {c.free_storage_days - daysInPort} Days Remaining
                                                            </Badge>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* TAB 3: ECC Customs Duty Calculator */}
                    <TabsContent value="ecc" className="space-y-4">
                        <div className="flex justify-between items-center">
                            <div>
                                <h3 className="text-base font-bold text-gray-900">{t('Ethiopian Customs Commission (ECC) Duty & Tariff Calculator')}</h3>
                                <p className="text-xs text-gray-500">{t('Computes CIF Djibouti valuation, Customs Duty (0-35%), Excise Tax, 15% VAT, SurTax 10%, and 3% Withholding.')}</p>
                            </div>
                            <Button onClick={() => setEccModalOpen(true)} className="bg-blue-600 hover:bg-blue-700 text-white">
                                <Calculator className="w-4 h-4 mr-1.5" />
                                {t('Calculate HS Code Duty')}
                            </Button>
                        </div>

                        <Card className="border">
                            <CardContent className="p-0 overflow-x-auto">
                                <table className="w-full text-xs text-left">
                                    <thead className="bg-slate-50 border-b text-gray-600 uppercase font-semibold text-[11px]">
                                        <tr>
                                            <th className="p-3">HS Code & Description</th>
                                            <th className="p-3 text-right">CIF Value (ETB)</th>
                                            <th className="p-3 text-center">Duty / Excise %</th>
                                            <th className="p-3 text-center">VAT / SurTax %</th>
                                            <th className="p-3 text-right">Total Duty Payable (ETB)</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 font-mono">
                                        {eccCustomsDuties.map((d) => (
                                            <tr key={d.id} className="hover:bg-slate-50">
                                                <td className="p-3">
                                                    <div className="font-bold text-slate-900">{d.hs_code}</div>
                                                    <div className="text-[10px] text-slate-500 font-sans">{d.description}</div>
                                                </td>
                                                <td className="p-3 text-right font-bold">{formatCurrency(d.cif_value_etb)}</td>
                                                <td className="p-3 text-center">Duty {d.duty_rate_percent}% | Excise {d.excise_rate_percent}%</td>
                                                <td className="p-3 text-center">VAT {d.vat_percent}% | SurTax {d.surtax_percent}%</td>
                                                <td className="p-3 text-right font-extrabold text-blue-900">{formatCurrency(d.total_duty_payable_etb)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* TAB 4: ECX & Coffee Export Contracts */}
                    <TabsContent value="ecx" className="space-y-4">
                        <div className="flex justify-between items-center">
                            <div>
                                <h3 className="text-base font-bold text-gray-900">{t('Ethiopian Commodity Exchange (ECX) & Coffee Export Contracts')}</h3>
                                <p className="text-xs text-gray-500">{t('Registers coffee, sesame, and pulse export contracts, ECX Quality Grades (1-5), and Buyer L/Cs.')}</p>
                            </div>
                            <Button onClick={() => setEcxModalOpen(true)} className="bg-purple-600 hover:bg-purple-700 text-white">
                                <Plus className="w-4 h-4 mr-1.5" />
                                {t('Record Export Contract')}
                            </Button>
                        </div>

                        <Card className="border">
                            <CardContent className="p-0 overflow-x-auto">
                                <table className="w-full text-xs text-left">
                                    <thead className="bg-slate-50 border-b text-gray-600 uppercase font-semibold text-[11px]">
                                        <tr>
                                            <th className="p-3">Contract No</th>
                                            <th className="p-3">Commodity & ECX Grade</th>
                                            <th className="p-3 text-center">Quantity (Tons)</th>
                                            <th className="p-3 text-right">Value (USD)</th>
                                            <th className="p-3 text-center">Destination</th>
                                            <th className="p-3 text-center">L/C Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 font-mono">
                                        {ecxContracts.map((e) => (
                                            <tr key={e.id} className="hover:bg-slate-50">
                                                <td className="p-3 font-bold text-slate-900">{e.contract_number}</td>
                                                <td className="p-3 font-sans">
                                                    <div className="font-bold text-slate-900">{e.commodity_type}</div>
                                                    <Badge className="bg-purple-100 text-purple-800 text-[9px]">{e.ecx_grade}</Badge>
                                                </td>
                                                <td className="p-3 text-center font-bold">{e.quantity_metric_tons} MT</td>
                                                <td className="p-3 text-right font-extrabold text-emerald-900">${parseFloat(e.contract_value_usd).toLocaleString()} USD</td>
                                                <td className="p-3 text-center font-sans font-medium">{e.destination_country}</td>
                                                <td className="p-3 text-center font-sans">
                                                    <Badge className="bg-emerald-100 text-emerald-800">{e.lc_status}</Badge>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* TAB 5: Letters of Credit */}
                    <TabsContent value="lcs" className="space-y-4">
                        <Card className="border">
                            <CardHeader>
                                <CardTitle className="text-sm font-bold">{t('Letters of Credit (LC) Registry')}</CardTitle>
                            </CardHeader>
                            <CardContent className="p-0 overflow-x-auto">
                                <table className="w-full text-xs text-left">
                                    <thead className="bg-slate-50 border-b text-gray-600 font-semibold">
                                        <tr>
                                            <th className="p-3">LC Number</th>
                                            <th className="p-3">Issuing Bank</th>
                                            <th className="p-3 text-right">Amount</th>
                                            <th className="p-3 text-center">Expiry Date</th>
                                            <th className="p-3 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 font-mono">
                                        {lcs.map((lc) => (
                                            <tr key={lc.id}>
                                                <td className="p-3 font-bold">{lc.lc_number}</td>
                                                <td className="p-3 font-sans">{lc.issuing_bank}</td>
                                                <td className="p-3 text-right font-bold">${parseFloat(lc.amount).toLocaleString()} {lc.currency}</td>
                                                <td className="p-3 text-center">{lc.expiry_date}</td>
                                                <td className="p-3 text-center font-sans"><Badge className="bg-emerald-100 text-emerald-800">{lc.status}</Badge></td>
                                            </tr>
                                        ))}
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
                    <DialogHeader><DialogTitle>{t('Add NBE Forex Queue Entry')}</DialogTitle></DialogHeader>
                    <form onSubmit={handleAddForex} className="space-y-3">
                        <div>
                            <Label className="text-xs">{t('LC Number')}</Label>
                            <Input value={lcNumber} onChange={(e) => setLcNumber(e.target.value)} placeholder="LC-CBE-2026-99" required className="h-8 text-xs" />
                        </div>
                        <div>
                            <Label className="text-xs">{t('Commercial Bank Name')}</Label>
                            <Input value={bankName} onChange={(e) => setBankName(e.target.value)} required className="h-8 text-xs" />
                        </div>
                        <div>
                            <Label className="text-xs">{t('NBE Queue Number')}</Label>
                            <Input value={nbeQueueNo} onChange={(e) => setNbeQueueNo(e.target.value)} placeholder="NBE-FX-9500" className="h-8 text-xs" />
                        </div>
                        <div>
                            <Label className="text-xs">{t('Amount USD')}</Label>
                            <Input type="number" value={amountUsd} onChange={(e) => setAmountUsd(e.target.value)} placeholder="100000" required className="h-8 text-xs" />
                        </div>
                        <DialogFooter><Button type="submit" className="w-full bg-amber-600 text-white">{t('Save Forex Record')}</Button></DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Djibouti Container Modal */}
            <Dialog open={djiboutiModalOpen} onOpenChange={setDjiboutiModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader><DialogTitle>{t('Track Djibouti Port Container')}</DialogTitle></DialogHeader>
                    <form onSubmit={handleAddDjibouti} className="space-y-3">
                        <div>
                            <Label className="text-xs">{t('Container Number')}</Label>
                            <Input value={containerNo} onChange={(e) => setContainerNo(e.target.value)} placeholder="MSCU1234567" required className="h-8 text-xs" />
                        </div>
                        <div>
                            <Label className="text-xs">{t('Vessel Name')}</Label>
                            <Input value={vesselName} onChange={(e) => setVesselName(e.target.value)} placeholder="MSC ALICE" required className="h-8 text-xs" />
                        </div>
                        <div>
                            <Label className="text-xs">{t('Bill of Lading Number')}</Label>
                            <Input value={billOfLading} onChange={(e) => setBillOfLading(e.target.value)} placeholder="BL-99482" required className="h-8 text-xs" />
                        </div>
                        <div>
                            <Label className="text-xs">{t('Discharge Date')}</Label>
                            <Input type="date" value={dischargeDate} onChange={(e) => setDischargeDate(e.target.value)} required className="h-8 text-xs" />
                        </div>
                        <DialogFooter><Button type="submit" className="w-full bg-rose-600 text-white">{t('Start Tracking Container')}</Button></DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* ECC Duty Modal */}
            <Dialog open={eccModalOpen} onOpenChange={setEccModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader><DialogTitle>{t('Calculate ECC Customs Duty')}</DialogTitle></DialogHeader>
                    <form onSubmit={handleCalculateEcc} className="space-y-3">
                        <div>
                            <Label className="text-xs">{t('HS Code')}</Label>
                            <Input value={hsCode} onChange={(e) => setHsCode(e.target.value)} placeholder="8471.3000" required className="h-8 text-xs" />
                        </div>
                        <div>
                            <Label className="text-xs">{t('Goods Description')}</Label>
                            <Input value={hsDescription} onChange={(e) => setHsDescription(e.target.value)} placeholder="Computers & Electronics" required className="h-8 text-xs" />
                        </div>
                        <div>
                            <Label className="text-xs">{t('CIF Value (ETB)')}</Label>
                            <Input type="number" value={cifValueEtb} onChange={(e) => setCifValueEtb(e.target.value)} placeholder="1000000" required className="h-8 text-xs" />
                        </div>
                        <div>
                            <Label className="text-xs">{t('Customs Duty Rate (%)')}</Label>
                            <Input type="number" value={dutyRate} onChange={(e) => setDutyRate(e.target.value)} placeholder="20" required className="h-8 text-xs" />
                        </div>
                        <DialogFooter><Button type="submit" className="w-full bg-blue-600 text-white">{t('Calculate & Store Duty')}</Button></DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* ECX Export Modal */}
            <Dialog open={ecxModalOpen} onOpenChange={setEcxModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader><DialogTitle>{t('Record ECX Export Contract')}</DialogTitle></DialogHeader>
                    <form onSubmit={handleAddEcx} className="space-y-3">
                        <div>
                            <Label className="text-xs">{t('Contract Number')}</Label>
                            <Input value={contractNo} onChange={(e) => setContractNo(e.target.value)} placeholder="ECX-EXP-2026-99" required className="h-8 text-xs" />
                        </div>
                        <div>
                            <Label className="text-xs">{t('Commodity Type')}</Label>
                            <Input value={commodityType} onChange={(e) => setCommodityType(e.target.value)} placeholder="Sidamo Arabica Coffee" required className="h-8 text-xs" />
                        </div>
                        <div>
                            <Label className="text-xs">{t('Quantity (Metric Tons)')}</Label>
                            <Input type="number" value={quantityTons} onChange={(e) => setQuantityTons(e.target.value)} placeholder="20" required className="h-8 text-xs" />
                        </div>
                        <div>
                            <Label className="text-xs">{t('Contract Value (USD)')}</Label>
                            <Input type="number" value={contractValUsd} onChange={(e) => setContractValUsd(e.target.value)} placeholder="150000" required className="h-8 text-xs" />
                        </div>
                        <DialogFooter><Button type="submit" className="w-full bg-purple-600 text-white">{t('Record Export Contract')}</Button></DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
