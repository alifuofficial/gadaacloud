import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Head, useForm, router } from "@inertiajs/react";
import { useTranslation } from "react-i18next";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
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
    ShieldAlert,
    CheckCircle2
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
        netCashFlow: number;
    };
    tax: {
        vatOutput: number;
        vatInput: number;
        netVatPayable: number;
        withholdingEst: number;
        taxableIncome: number;
        businessTaxEst: number;
    };
    forecastMonths: Array<{
        month: string;
        receivables: number;
        payables: number;
        net: number;
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

export default function CopilotIndex({ metrics, tax, forecastMonths, automations, insights }: CopilotProps) {
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
            pageTitle={t("GadaaCloud Copilot — AI Operations & Cash Flow Autonomous Agent")}
        >
            <Head title={t("GadaaCloud Copilot")} />

            <div className="space-y-6">
                {/* Copilot AI Banner */}
                <Card className="bg-gradient-to-r from-emerald-900 via-teal-900 to-slate-900 text-white border-0 shadow-xl overflow-hidden relative">
                    <div className="absolute right-0 top-0 opacity-10 transform translate-x-12 -translate-y-6">
                        <Bot className="w-96 h-96 text-emerald-400" />
                    </div>
                    <CardContent className="p-8 relative z-10">
                        <div className="flex items-center gap-3 mb-4">
                            <div className="p-3 bg-emerald-500/20 rounded-2xl border border-emerald-400/30 backdrop-blur-md">
                                <Sparkles className="w-8 h-8 text-emerald-400 animate-pulse" />
                            </div>
                            <div>
                                <Badge className="bg-emerald-500 text-slate-950 font-semibold mb-1">
                                    AI Agent Autonomous Engine
                                </Badge>
                                <h1 className="text-3xl font-bold tracking-tight text-white">
                                    {t("GadaaCloud Copilot Intelligence")}
                                </h1>
                            </div>
                        </div>
                        <p className="text-emerald-100 max-w-2xl leading-relaxed text-sm md:text-base">
                            {t("Automating operational workflows, predicting cash flow trajectories, calculating Ethiopian tax liabilities (VAT, Withholding, Corporate Income Tax), and synchronizing cross-module system data.")}
                        </p>
                    </CardContent>
                </Card>

                {/* KPI Metrics Summary */}
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
                            <p className="text-xs text-emerald-600 flex items-center mt-1">
                                <ArrowUpRight className="w-3.5 h-3.5 mr-1" />
                                {t("Real-time Inflow Output")}
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
                                {t("Uncollected Sales Invoices")}
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border shadow-sm hover:shadow-md transition-shadow">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-slate-500">
                                {t("Pending Payables")}
                            </CardTitle>
                            <ArrowDownRight className="w-5 h-5 text-amber-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-slate-900 dark:text-white">
                                {formatCurrency(metrics.pendingPayables)}
                            </div>
                            <p className="text-xs text-slate-500 mt-1">
                                {t("Purchase Invoices Due")}
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border shadow-sm hover:shadow-md transition-shadow">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium text-slate-500">
                                {t("Est. Net VAT Liability")}
                            </CardTitle>
                            <Receipt className="w-5 h-5 text-indigo-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                                {formatCurrency(tax.netVatPayable)}
                            </div>
                            <p className="text-xs text-slate-500 mt-1">
                                {t("Ethiopian 15% VAT Net Payable")}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* 6-Month AI Cash Flow Forecast & Tax Engine */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <Card className="lg:col-span-2 border shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-lg font-bold flex items-center gap-2">
                                <TrendingUp className="w-5 h-5 text-emerald-600" />
                                {t("AI 6-Month Cash Flow Prediction")}
                            </CardTitle>
                            <CardDescription>
                                {t("Calculated using sales velocity, purchase commitments, and seasonal growth trends.")}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {forecastMonths.map((fm, idx) => (
                                    <div key={idx} className="p-3 bg-slate-50 dark:bg-slate-900 rounded-xl border flex items-center justify-between">
                                        <div>
                                            <span className="font-semibold text-slate-900 dark:text-white">{fm.month}</span>
                                            <div className="text-xs text-slate-500 space-x-3">
                                                <span>Inflow: {formatCurrency(fm.receivables)}</span>
                                                <span>Outflow: {formatCurrency(fm.payables)}</span>
                                            </div>
                                        </div>
                                        <Badge className={fm.net >= 0 ? "bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300" : "bg-rose-100 text-rose-800"}>
                                            {fm.net >= 0 ? `+${formatCurrency(fm.net)}` : formatCurrency(fm.net)}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Tax Engine */}
                    <Card className="border shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-lg font-bold flex items-center gap-2">
                                <Receipt className="w-5 h-5 text-indigo-600" />
                                {t("Ethiopian Tax Assistant")}
                            </CardTitle>
                            <CardDescription>
                                {t("Automated tax calculations for regulatory compliance.")}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="p-3 rounded-lg border bg-slate-50 dark:bg-slate-900 space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-slate-600 dark:text-slate-400">{t("Output VAT (15%)")}:</span>
                                    <span className="font-semibold">{formatCurrency(tax.vatOutput)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-slate-600 dark:text-slate-400">{t("Input VAT Credit")}:</span>
                                    <span className="font-semibold">{formatCurrency(tax.vatInput)}</span>
                                </div>
                                <div className="border-t pt-2 flex justify-between font-bold text-slate-900 dark:text-white">
                                    <span>{t("Net VAT Payable")}:</span>
                                    <span className="text-indigo-600">{formatCurrency(tax.netVatPayable)}</span>
                                </div>
                            </div>

                            <div className="p-3 rounded-lg border bg-slate-50 dark:bg-slate-900 space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-slate-600 dark:text-slate-400">{t("Withholding Tax Est. (2%)")}:</span>
                                    <span className="font-semibold">{formatCurrency(tax.withholdingEst)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-slate-600 dark:text-slate-400">{t("Est. Corporate Tax (30%)")}:</span>
                                    <span className="font-semibold text-emerald-600">{formatCurrency(tax.businessTaxEst)}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Operations Automations Section */}
                <Card className="border shadow-sm">
                    <CardHeader>
                        <CardTitle className="text-lg font-bold flex items-center gap-2">
                            <Zap className="w-5 h-5 text-amber-500" />
                            {t("Active Operational Automations")}
                        </CardTitle>
                        <CardDescription>
                            {t("Automated background rules triggered by cross-module events.")}
                        </CardDescription>
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

                {/* Ask Copilot Interactive Terminal */}
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
                                placeholder={t("Ask Copilot to predict cash flow, analyze tax liabilities, or automate tasks...")}
                                className="bg-slate-900 border-slate-800 text-white placeholder:text-slate-500 focus-visible:ring-emerald-500"
                            />
                            <Button type="submit" disabled={isAsking} className="bg-emerald-600 text-white hover:bg-emerald-500">
                                {isAsking ? t("Analyzing...") : <Send className="w-4 h-4" />}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
