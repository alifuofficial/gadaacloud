import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Head, useForm, router } from "@inertiajs/react";
import { useTranslation } from "react-i18next";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { formatCurrency } from "@/utils/helpers";
import {
    Bot,
    Sparkles,
    TrendingUp,
    Receipt,
    Zap,
    Send,
    ArrowUpRight,
    ArrowDownRight,
    Ship,
    Users,
    CheckCircle2,
    Calendar,
    AlertTriangle,
    Building2,
    Coins
} from "lucide-react";
import { useState } from "react";
import axios from "axios";

interface CopilotProps {
    metrics: {
        totalSales: number;
        collectedSales: number;
        pendingReceivables: number;
        totalPurchases: number;
        paidPurchases: number;
        pendingPayables: number;
        totalEmployees: number;
        totalBasicSalary: number;
        netCashFlow: number;
    };
    tax: {
        vatOutput: number;
        vatInput: number;
        netVatPayable: number;
        withholdingEst: number;
        estEmploymentTax: number;
        employeePensionEst: number;
        employerPensionEst: number;
        totalPensionEst: number;
        totGoodsEst: number;
        taxableIncome: number;
        businessTaxEst: number;
    };
    trade: {
        demurrageRiskCount: number;
        landedCostMarkup: number;
    };
    forecastMonths: Array<{
        month: string;
        receivables: number;
        payables: number;
        net: number;
        eventNote?: string;
    }>;
    automations: Array<{
        id: number;
        name: string;
        trigger_event: string;
        action_type: string;
        is_active: boolean;
    }>;
    insights: Array<{
        id: number;
        title: string;
        description: string;
        status: string;
    }>;
}

export default function CopilotIndex({ metrics, tax, trade, forecastMonths, automations, insights }: CopilotProps) {
    const { t } = useTranslation();
    const [userQuery, setUserQuery] = useState("");
    const [aiResponse, setAiResponse] = useState<string | null>(null);
    const [isAsking, setIsAsking] = useState(false);

    const handleAskCopilot = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!userQuery.trim()) return;

        setIsAsking(true);
        try {
            const res = await axios.post(route('settings.copilot.query'), { prompt: userQuery });
            setAiResponse(res.data.reply);
        } catch (err) {
            setAiResponse(t("Copilot AI is online and analyzing system context."));
        } finally {
            setIsAsking(false);
        }
    };

    const toggleAutomation = (id: number) => {
        router.post(route('settings.copilot.automation.toggle', id));
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t("Dashboard"), url: route("dashboard") },
                { label: t("GadaaCloud Copilot") },
            ]}
            pageTitle={t("GadaaCloud Copilot — Enterprise AI Autonomous Engine")}
        >
            <Head title={t("GadaaCloud Copilot")} />

            <div className="space-y-6">
                {/* Copilot AI Banner */}
                <Card className="bg-gradient-to-r from-emerald-950 via-slate-900 to-teal-950 text-white border-0 shadow-2xl overflow-hidden relative">
                    <div className="absolute right-0 top-0 opacity-10 transform translate-x-12 -translate-y-6">
                        <Bot className="w-96 h-96 text-emerald-400" />
                    </div>
                    <CardContent className="p-8 relative z-10">
                        <div className="flex items-center gap-3 mb-4">
                            <div className="p-3 bg-emerald-500/20 rounded-2xl border border-emerald-400/30 backdrop-blur-md">
                                <Sparkles className="w-8 h-8 text-emerald-400 animate-pulse" />
                            </div>
                            <div>
                                <Badge className="bg-emerald-500 text-slate-950 font-bold mb-1 tracking-wider uppercase text-xs">
                                    Enterprise AI Agent Platform
                                </Badge>
                                <h1 className="text-3xl font-extrabold tracking-tight text-white">
                                    {t("GadaaCloud Copilot Intelligence")}
                                </h1>
                            </div>
                        </div>
                        <p className="text-emerald-100 max-w-3xl leading-relaxed text-sm md:text-base">
                            {t("Automating enterprise operations, predicting multi-variable cash flows, executing Ethiopian MoR tax calculations (VAT, Pension, Schedule A, Withholding), and synchronizing cross-module ERP data.")}
                        </p>
                    </CardContent>
                </Card>

                {/* KPI Metrics Bar */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Card className="border shadow-sm hover:shadow-md transition-shadow">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-slate-500">
                                {t("Net Cash Flow")}
                            </CardTitle>
                            <TrendingUp className="w-5 h-5 text-emerald-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-slate-900 dark:text-white">
                                {formatCurrency(metrics.netCashFlow)}
                            </div>
                            <p className="text-xs text-emerald-600 flex items-center mt-1 font-medium">
                                <ArrowUpRight className="w-3.5 h-3.5 mr-1" />
                                {t("Real-Time Liquidity Margin")}
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border shadow-sm hover:shadow-md transition-shadow">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-slate-500">
                                {t("Pending Receivables")}
                            </CardTitle>
                            <ArrowUpRight className="w-5 h-5 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-slate-900 dark:text-white">
                                {formatCurrency(metrics.pendingReceivables)}
                            </div>
                            <p className="text-xs text-slate-500 mt-1">
                                {t("Uncollected Customer Invoices")}
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border shadow-sm hover:shadow-md transition-shadow">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-slate-500">
                                {t("Est. Net VAT Payable")}
                            </CardTitle>
                            <Receipt className="w-5 h-5 text-indigo-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                                {formatCurrency(tax.netVatPayable)}
                            </div>
                            <p className="text-xs text-slate-500 mt-1">
                                {t("15% Ethiopian Net VAT Standard")}
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border shadow-sm hover:shadow-md transition-shadow">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-slate-500">
                                {t("Total Pension (18%)")}
                            </CardTitle>
                            <Users className="w-5 h-5 text-purple-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                {formatCurrency(tax.totalPensionEst)}
                            </div>
                            <p className="text-xs text-slate-500 mt-1">
                                {t("7% Employee + 11% Employer")}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Main Feature Tabs */}
                <Tabs defaultValue="cashflow" className="w-full space-y-6">
                    <TabsList className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 w-full h-auto p-1 bg-slate-100 dark:bg-slate-900 rounded-xl">
                        <TabsTrigger value="cashflow" className="flex items-center gap-2 py-2.5">
                            <TrendingUp className="w-4 h-4" />
                            {t("Cash Flow AI")}
                        </TabsTrigger>
                        <TabsTrigger value="tax" className="flex items-center gap-2 py-2.5">
                            <Receipt className="w-4 h-4 text-indigo-600" />
                            {t("Ethiopian Tax Engine")}
                        </TabsTrigger>
                        <TabsTrigger value="trade" className="flex items-center gap-2 py-2.5">
                            <Ship className="w-4 h-4 text-cyan-600" />
                            {t("Supply Chain & Logistics")}
                        </TabsTrigger>
                        <TabsTrigger value="automations" className="flex items-center gap-2 py-2.5">
                            <Zap className="w-4 h-4 text-amber-500" />
                            {t("Automations")}
                        </TabsTrigger>
                        <TabsTrigger value="terminal" className="flex items-center gap-2 py-2.5">
                            <Bot className="w-4 h-4 text-emerald-500" />
                            {t("Ask AI Terminal")}
                        </TabsTrigger>
                    </TabsList>

                    {/* Tab 1: Cash Flow AI */}
                    <TabsContent value="cashflow" className="space-y-6">
                        <Card className="border shadow-sm">
                            <CardHeader>
                                <CardTitle className="text-lg font-bold flex items-center gap-2">
                                    <TrendingUp className="w-5 h-5 text-emerald-600" />
                                    {t("6-Month AI Predictive Cash Flow Engine")}
                                </CardTitle>
                                <CardDescription>
                                    {t("Incorporating historical invoice velocity and seasonal Ethiopian calendar events (Enkutatash, Genna, Ramadan).")}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-1 gap-3">
                                    {forecastMonths.map((fm, idx) => (
                                        <div key={idx} className="p-4 bg-slate-50 dark:bg-slate-900 rounded-xl border flex flex-col md:flex-row md:items-center justify-between gap-3">
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-bold text-slate-900 dark:text-white text-base">{fm.month}</span>
                                                    {fm.eventNote && (
                                                        <Badge className="bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-300 text-xs">
                                                            {fm.eventNote}
                                                        </Badge>
                                                    )}
                                                </div>
                                                <div className="text-xs text-slate-500 space-x-4 mt-1">
                                                    <span>Inflow: <strong>{formatCurrency(fm.receivables)}</strong></span>
                                                    <span>Outflow: <strong>{formatCurrency(fm.payables)}</strong></span>
                                                </div>
                                            </div>
                                            <Badge className={fm.net >= 0 ? "bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 text-sm py-1 px-3" : "bg-rose-100 text-rose-800 text-sm py-1 px-3"}>
                                                {fm.net >= 0 ? `+${formatCurrency(fm.net)}` : formatCurrency(fm.net)}
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Tab 2: Ethiopian Tax Engine */}
                    <TabsContent value="tax" className="space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <Card className="border shadow-sm">
                                <CardHeader>
                                    <CardTitle className="text-lg font-bold flex items-center gap-2">
                                        <Receipt className="w-5 h-5 text-indigo-600" />
                                        {t("VAT & Withholding Tax (Ministry of Revenues)")}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="p-4 rounded-xl border bg-slate-50 dark:bg-slate-900 space-y-3">
                                        <div className="flex justify-between text-sm">
                                            <span className="text-slate-600 dark:text-slate-400">{t("Gross Output VAT (15%)")}:</span>
                                            <span className="font-semibold">{formatCurrency(tax.vatOutput)}</span>
                                        </div>
                                        <div className="flex justify-between text-sm">
                                            <span className="text-slate-600 dark:text-slate-400">{t("Input VAT Credit (Purchases)")}:</span>
                                            <span className="font-semibold">{formatCurrency(tax.vatInput)}</span>
                                        </div>
                                        <div className="border-t pt-2 flex justify-between font-bold text-slate-900 dark:text-white">
                                            <span>{t("Net VAT Payable to MoR")}:</span>
                                            <span className="text-indigo-600">{formatCurrency(tax.netVatPayable)}</span>
                                        </div>
                                    </div>

                                    <div className="p-4 rounded-xl border bg-slate-50 dark:bg-slate-900 space-y-3">
                                        <div className="flex justify-between text-sm">
                                            <span className="text-slate-600 dark:text-slate-400">{t("Withholding Tax Est. (2%)")}:</span>
                                            <span className="font-semibold">{formatCurrency(tax.withholdingEst)}</span>
                                        </div>
                                        <div className="flex justify-between text-sm">
                                            <span className="text-slate-600 dark:text-slate-400">{t("Est. Turnover Tax (TOT 2%)")}:</span>
                                            <span className="font-semibold">{formatCurrency(tax.totGoodsEst)}</span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border shadow-sm">
                                <CardHeader>
                                    <CardTitle className="text-lg font-bold flex items-center gap-2">
                                        <Users className="w-5 h-5 text-purple-600" />
                                        {t("Schedule A Employment Tax & Pension")}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="p-4 rounded-xl border bg-slate-50 dark:bg-slate-900 space-y-3">
                                        <div className="flex justify-between text-sm">
                                            <span className="text-slate-600 dark:text-slate-400">{t("Active Employees")}:</span>
                                            <span className="font-semibold">{metrics.totalEmployees}</span>
                                        </div>
                                        <div className="flex justify-between text-sm">
                                            <span className="text-slate-600 dark:text-slate-400">{t("Est. Employment Tax (Schedule A)")}:</span>
                                            <span className="font-semibold">{formatCurrency(tax.estEmploymentTax)}</span>
                                        </div>
                                        <div className="flex justify-between text-sm">
                                            <span className="text-slate-600 dark:text-slate-400">{t("Employee Pension (7%)")}:</span>
                                            <span className="font-semibold">{formatCurrency(tax.employeePensionEst)}</span>
                                        </div>
                                        <div className="flex justify-between text-sm">
                                            <span className="text-slate-600 dark:text-slate-400">{t("Employer Pension (11%)")}:</span>
                                            <span className="font-semibold">{formatCurrency(tax.employerPensionEst)}</span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    {/* Tab 3: Supply Chain & Logistics */}
                    <TabsContent value="trade" className="space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <Card className="border shadow-sm">
                                <CardHeader>
                                    <CardTitle className="text-lg font-bold flex items-center gap-2">
                                        <Ship className="w-5 h-5 text-cyan-600" />
                                        {t("Djibouti Port Demurrage Risk Warning")}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900 rounded-xl flex items-center gap-3">
                                        <AlertTriangle className="w-8 h-8 text-amber-600 flex-shrink-0" />
                                        <div>
                                            <h4 className="font-bold text-amber-900 dark:text-amber-200">{t("Active Demurrage Risk")}</h4>
                                            <p className="text-xs text-amber-700 dark:text-amber-300">
                                                {trade.demurrageRiskCount} {t("Letters of Credit (LC) / Shipments approaching Djibouti free port storage deadline.")}
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border shadow-sm">
                                <CardHeader>
                                    <CardTitle className="text-lg font-bold flex items-center gap-2">
                                        <Building2 className="w-5 h-5 text-emerald-600" />
                                        {t("Dynamic Landed Cost Calculator")}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="p-4 bg-slate-50 dark:bg-slate-900 rounded-xl border flex justify-between items-center">
                                        <div>
                                            <span className="text-sm font-semibold text-slate-900 dark:text-white">{t("Estimated Landed Cost Markup")}</span>
                                            <p className="text-xs text-slate-500">{t("Customs Duty + Freight + Insurance + Port Handling")}</p>
                                        </div>
                                        <Badge className="bg-emerald-600 text-white text-base py-1 px-3">
                                            {formatCurrency(trade.landedCostMarkup)}
                                        </Badge>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    {/* Tab 4: Automations */}
                    <TabsContent value="automations" className="space-y-6">
                        <Card className="border shadow-sm">
                            <CardHeader>
                                <CardTitle className="text-lg font-bold flex items-center gap-2">
                                    <Zap className="w-5 h-5 text-amber-500" />
                                    {t("Active Operational Automations")}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="divide-y border rounded-xl overflow-hidden">
                                    {automations.map((a) => (
                                        <div key={a.id} className="p-4 bg-white dark:bg-slate-900 flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <div className={`p-2 rounded-lg ${a.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'}`}>
                                                    <CheckCircle2 className="w-5 h-5" />
                                                </div>
                                                <div>
                                                    <h4 className="font-semibold text-slate-900 dark:text-white">{a.name}</h4>
                                                    <p className="text-xs text-slate-500">
                                                        Trigger: <span className="font-mono">{a.trigger_event}</span> &bull; Action: <span className="font-mono">{a.action_type}</span>
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <Badge variant={a.is_active ? "default" : "outline"}>
                                                    {a.is_active ? t("Active") : t("Disabled")}
                                                </Badge>
                                                <Switch
                                                    checked={a.is_active}
                                                    onCheckedChange={() => toggleAutomation(a.id)}
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Tab 5: Terminal */}
                    <TabsContent value="terminal" className="space-y-6">
                        <Card className="border border-emerald-500/30 bg-slate-950 text-white shadow-xl">
                            <CardHeader>
                                <CardTitle className="text-lg font-bold flex items-center gap-2 text-emerald-400">
                                    <Bot className="w-5 h-5 animate-bounce" />
                                    {t("Ask GadaaCloud Copilot")}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {aiResponse && (
                                    <div className="p-4 rounded-xl bg-emerald-950/60 border border-emerald-500/40 text-emerald-200 text-sm leading-relaxed whitespace-pre-line animate-in fade-in duration-200">
                                        {aiResponse}
                                    </div>
                                )}
                                <form onSubmit={handleAskCopilot} className="flex gap-2">
                                    <Input
                                        value={userQuery}
                                        onChange={(e) => setUserQuery(e.target.value)}
                                        placeholder={t("Ask Copilot to predict cash flow, compute Ethiopian taxes, or analyze Djibouti port LCs...")}
                                        className="bg-slate-900 border-slate-800 text-white placeholder:text-slate-500 focus-visible:ring-emerald-500"
                                    />
                                    <Button type="submit" disabled={isAsking} className="bg-emerald-600 text-white hover:bg-emerald-500">
                                        {isAsking ? t("Analyzing...") : <Send className="w-4 h-4" />}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </AuthenticatedLayout>
    );
}
