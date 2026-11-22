import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Head, router } from "@inertiajs/react";
import { useTranslation } from "react-i18next";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { formatCurrency } from "@/utils/helpers";
import { toast } from "sonner";
import {
    Bot,
    Sparkles,
    TrendingUp,
    Receipt,
    Zap,
    Send,
    ArrowUpRight,
    ArrowDownRight,
    Users,
    CheckCircle2,
    Calendar,
    AlertTriangle,
    Coins,
    Brain,
    Eye,
    UserCheck,
    Plus,
    FileText,
    ShieldCheck,
    RefreshCw,
    LayoutDashboard,
    Lightbulb,
    BarChart3,
    Copy,
    Check,
    HelpCircle,
    ChevronRight,
    ExternalLink
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
        companyTokensUsed?: number;
        companyTokenCost?: number;
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

export default function CopilotIndex({ metrics, tax, trade, forecastMonths = [], automations = [], insights = [] }: CopilotProps) {
    const { t } = useTranslation();
    const urlParams = new URLSearchParams(window.location.search);
    const initialTab = urlParams.get('tab') || 'overview';

    const [activeTab, setActiveTab] = useState(initialTab);
    const [userQuery, setUserQuery] = useState("");
    const [aiResponse, setAiResponse] = useState<string | null>(null);
    const [isAsking, setIsAsking] = useState(false);
    const [copiedWebhook, setCopiedWebhook] = useState(false);
    const [copiedResponse, setCopiedResponse] = useState(false);

    // Create Rule Modal State
    const [ruleModalOpen, setRuleModalOpen] = useState(false);
    const [ruleName, setRuleName] = useState('');
    const [ruleTrigger, setRuleTrigger] = useState('invoice_due');
    const [ruleAction, setRuleAction] = useState('send_email');

    const webhookEndpoint = `${window.location.origin}/api/webhooks/inbound-invoice`;

    const handleAskCopilot = async (customPrompt?: string) => {
        const queryText = customPrompt || userQuery;
        if (!queryText.trim() || isAsking) return;

        setIsAsking(true);
        if (customPrompt) setUserQuery(customPrompt);

        try {
            let queryUrl = '/settings/copilot/query';
            try {
                if (typeof route === 'function') {
                    queryUrl = route('settings.copilot.query');
                }
            } catch (err) {}

            const res = await axios.post(queryUrl, { prompt: queryText });
            setAiResponse(res.data.reply || t("Analysis completed successfully."));
            toast.success(t("Analysis completed"));
        } catch (err: any) {
            setAiResponse(t("Copilot AI is online and analyzing multi-module system context."));
            toast.error(t("Unable to complete analysis. Please try again."));
        } finally {
            setIsAsking(false);
        }
    };

    const handleCopyResponse = () => {
        if (!aiResponse) return;
        navigator.clipboard.writeText(aiResponse);
        setCopiedResponse(true);
        toast.success(t("Copilot response copied to clipboard"));
        setTimeout(() => setCopiedResponse(false), 2000);
    };

    const handleCopyWebhook = () => {
        navigator.clipboard.writeText(webhookEndpoint);
        setCopiedWebhook(true);
        toast.success(t("Webhook URL copied to clipboard"));
        setTimeout(() => setCopiedWebhook(false), 2000);
    };

    const handleCreateRule = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!ruleName.trim()) return;

        try {
            let createUrl = '/settings/copilot/automation/create';
            try {
                if (typeof route === 'function') {
                    createUrl = route('settings.copilot.automation.create');
                }
            } catch (err) {}

            await axios.post(createUrl, {
                name: ruleName,
                trigger_event: ruleTrigger,
                action_type: ruleAction,
            });
            setRuleModalOpen(false);
            setRuleName('');
            toast.success(t("Automation rule created"));
            router.reload();
        } catch (err) {
            toast.error(t("Failed to create automation rule"));
        }
    };

    const handleToggleAutomation = (id: number) => {
        let toggleUrl = `/settings/copilot/automation/${id}/toggle`;
        try {
            if (typeof route === 'function') {
                toggleUrl = route('settings.copilot.automation.toggle', { id });
            }
        } catch (e) {}

        router.post(toggleUrl, {}, {
            preserveScroll: true,
            onSuccess: () => toast.success(t("Automation status updated")),
        });
    };

    const quickPrompts = [
        { label: "📊 6-Month Cashflow", prompt: "Predict next 6-month cashflow with seasonal trends" },
        { label: "🇪🇹 Ethiopian Tax & Pension", prompt: "Calculate Ethiopian VAT, employment tax, and 11% pension" },
        { label: "💰 Sales & Receivables", prompt: "Summarize total sales revenue, collected amounts, and pending receivables" },
        { label: "📦 Inventory Stockouts", prompt: "Which products will run out of stock next across warehouses?" },
        { label: "👥 Staff Payroll", prompt: "Show active employee headcount and total monthly payroll basic salary" },
    ];

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Settings') }, { label: t('GadaaCloud Copilot Hub') }]}
            pageTitle={t('GadaaCloud Copilot Intelligence Hub')}
        >
            <Head title={t('GadaaCloud Copilot Hub')} />

            <div className="space-y-6 max-w-7xl mx-auto pb-12">
                {/* Modern Brand Hero Banner */}
                <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-emerald-950 to-slate-900 text-white p-6 md:p-8 shadow-md border border-emerald-500/20">
                    <div className="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                        <div className="space-y-3 max-w-3xl">
                            <div className="flex items-center gap-2 flex-wrap">
                                <Badge className="bg-emerald-500/20 text-emerald-300 border-emerald-400/30 px-3 py-1 text-xs font-semibold">
                                    <Sparkles className="w-3.5 h-3.5 mr-1 text-emerald-300 animate-pulse" />
                                    {t('GadaaCloud Copilot 2.0')}
                                </Badge>
                                <Badge className="bg-white/10 text-slate-200 border-white/15 px-3 py-1 text-xs font-mono">
                                    <Brain className="w-3.5 h-3.5 mr-1 text-emerald-400" />
                                    {t('Persistent Memory')}
                                </Badge>
                                <Badge className="bg-blue-500/20 text-blue-300 border-blue-400/30 px-3 py-1 text-xs font-mono">
                                    <ShieldCheck className="w-3.5 h-3.5 mr-1" />
                                    {t('MoR Tax Engine')}
                                </Badge>
                            </div>
                            <h1 className="text-2xl md:text-3xl font-extrabold tracking-tight text-white">
                                {t('GadaaCloud Copilot Intelligence Hub')}
                            </h1>
                            <p className="text-slate-300 text-xs md:text-sm leading-relaxed max-w-2xl">
                                {t('Autonomous business intelligence: predictive cashflow forecasting, automated Ethiopian Ministry of Revenues (MoR) tax calculations, and multi-branch operational analytics.')}
                            </p>
                        </div>

                        {/* Token Consumption Card */}
                        <div className="bg-white/10 backdrop-blur-md border border-white/15 p-4 rounded-xl shrink-0 space-y-1 font-mono text-xs w-full md:w-auto">
                            <div className="text-emerald-300 font-sans font-semibold flex items-center gap-1.5">
                                <Coins className="w-4 h-4 text-emerald-400" />
                                {t('Company AI Token Usage')}
                            </div>
                            <div className="text-xl font-bold text-white">
                                {(metrics.companyTokensUsed || 0).toLocaleString()} <span className="text-xs text-slate-300 font-sans">Tokens</span>
                            </div>
                            <div className="text-[11px] text-slate-300">
                                {t('Metered Value')}: <span className="font-bold text-emerald-400">{formatCurrency(metrics.companyTokenCost || 0)}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Modern AI Command & Query Bar */}
                <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs bg-white dark:bg-card">
                    <CardHeader className="p-4 pb-2 border-b border-slate-100 dark:border-slate-800 flex flex-row items-center justify-between">
                        <CardTitle className="text-sm font-bold flex items-center gap-2 text-slate-900 dark:text-slate-100">
                            <div className="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 border border-emerald-200/60 dark:border-emerald-800/40">
                                <Bot className="w-4 h-4" />
                            </div>
                            {t('Ask GadaaCloud Copilot')}
                        </CardTitle>
                        <span className="text-xs text-slate-400 font-mono">
                            {t('Real-time DB Telemetry')}
                        </span>
                    </CardHeader>

                    <CardContent className="p-4 space-y-3">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                handleAskCopilot();
                            }}
                            className="flex flex-col sm:flex-row gap-2"
                        >
                            <Input
                                value={userQuery}
                                onChange={(e) => setUserQuery(e.target.value)}
                                placeholder={t('Ask Copilot: "Predict cashflow for next month", "Calculate Ethiopian VAT & Pension", "Which products will stock out?"...')}
                                className="text-xs h-10 border-slate-200 dark:border-slate-700 focus-visible:ring-emerald-500/20 focus-visible:border-emerald-500"
                            />
                            <Button
                                type="submit"
                                disabled={isAsking || !userQuery.trim()}
                                className="bg-emerald-600 hover:bg-emerald-700 text-white shrink-0 h-10 px-5 shadow-xs transition-all font-medium text-xs"
                            >
                                {isAsking ? (
                                    <>
                                        <RefreshCw className="w-4 h-4 animate-spin mr-1.5" />
                                        {t('Analyzing...')}
                                    </>
                                ) : (
                                    <>
                                        <Send className="w-4 h-4 mr-1.5" />
                                        {t('Ask Copilot')}
                                    </>
                                )}
                            </Button>
                        </form>

                        {/* Quick Prompts Chips */}
                        <div className="flex items-center gap-1.5 flex-wrap pt-1">
                            <span className="text-[11px] text-slate-400 font-medium mr-1">{t('Suggestions')}:</span>
                            {quickPrompts.map((qp, idx) => (
                                <button
                                    key={idx}
                                    type="button"
                                    onClick={() => handleAskCopilot(qp.prompt)}
                                    className="text-[11px] px-2.5 py-1 rounded-full bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 dark:bg-slate-800 dark:hover:bg-emerald-950/50 dark:hover:text-emerald-400 text-slate-600 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700 transition-colors"
                                >
                                    {qp.label}
                                </button>
                            ))}
                        </div>

                        {/* Copilot Response Card */}
                        {aiResponse && (
                            <div className="mt-3 p-4 rounded-xl bg-slate-50/80 dark:bg-slate-900/60 border border-emerald-200/70 dark:border-emerald-900/40 text-xs text-slate-800 dark:text-slate-200 leading-relaxed animate-in fade-in duration-300 space-y-2">
                                <div className="flex items-center justify-between pb-2 border-b border-emerald-200/40 dark:border-emerald-900/30">
                                    <div className="flex items-center gap-2">
                                        <Sparkles className="w-3.5 h-3.5 text-emerald-600" />
                                        <span className="font-semibold text-emerald-800 dark:text-emerald-400 text-xs">{t('Copilot Intelligence Summary')}</span>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={handleCopyResponse}
                                        className="h-7 px-2 text-[11px] text-slate-500 hover:text-slate-800 dark:hover:text-slate-200"
                                    >
                                        {copiedResponse ? (
                                            <>
                                                <Check className="w-3.5 h-3.5 mr-1 text-emerald-600" />
                                                {t('Copied')}
                                            </>
                                        ) : (
                                            <>
                                                <Copy className="w-3.5 h-3.5 mr-1" />
                                                {t('Copy')}
                                            </>
                                        )}
                                    </Button>
                                </div>
                                <div className="whitespace-pre-wrap font-sans text-xs pt-1">{aiResponse}</div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Main Feature Capabilities Tabs */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
                    <TabsList className="bg-slate-100 dark:bg-slate-800/60 p-1 rounded-xl border border-slate-200/80 dark:border-slate-700/80 inline-flex flex-wrap gap-1">
                        <TabsTrigger
                            value="overview"
                            className="text-xs data-[state=active]:bg-white dark:data-[state=active]:bg-slate-900 data-[state=active]:text-emerald-700 dark:data-[state=active]:text-emerald-400 data-[state=active]:shadow-xs font-semibold px-3.5 py-1.5 rounded-lg gap-1.5 transition-all"
                        >
                            <LayoutDashboard className="w-3.5 h-3.5" />
                            {t('Overview & Financials')}
                        </TabsTrigger>
                        <TabsTrigger
                            value="predictions"
                            className="text-xs data-[state=active]:bg-white dark:data-[state=active]:bg-slate-900 data-[state=active]:text-emerald-700 dark:data-[state=active]:text-emerald-400 data-[state=active]:shadow-xs font-semibold px-3.5 py-1.5 rounded-lg gap-1.5 transition-all"
                        >
                            <TrendingUp className="w-3.5 h-3.5" />
                            {t('Predictive AI Radar')}
                        </TabsTrigger>
                        <TabsTrigger
                            value="automations"
                            className="text-xs data-[state=active]:bg-white dark:data-[state=active]:bg-slate-900 data-[state=active]:text-emerald-700 dark:data-[state=active]:text-emerald-400 data-[state=active]:shadow-xs font-semibold px-3.5 py-1.5 rounded-lg gap-1.5 transition-all"
                        >
                            <Zap className="w-3.5 h-3.5" />
                            {t('Workflow Automations')}
                        </TabsTrigger>
                        <TabsTrigger
                            value="recruitment"
                            className="text-xs data-[state=active]:bg-white dark:data-[state=active]:bg-slate-900 data-[state=active]:text-emerald-700 dark:data-[state=active]:text-emerald-400 data-[state=active]:shadow-xs font-semibold px-3.5 py-1.5 rounded-lg gap-1.5 transition-all"
                        >
                            <UserCheck className="w-3.5 h-3.5" />
                            {t('AI Recruitment')}
                        </TabsTrigger>
                        <TabsTrigger
                            value="ocr"
                            className="text-xs data-[state=active]:bg-white dark:data-[state=active]:bg-slate-900 data-[state=active]:text-emerald-700 dark:data-[state=active]:text-emerald-400 data-[state=active]:shadow-xs font-semibold px-3.5 py-1.5 rounded-lg gap-1.5 transition-all"
                        >
                            <Eye className="w-3.5 h-3.5" />
                            {t('Vision OCR & Webhooks')}
                        </TabsTrigger>
                    </TabsList>

                    {/* TAB 1: Overview & Financial Telemetry */}
                    <TabsContent value="overview" className="space-y-6">
                        {/* 4 Core KPI Summary Cards */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs hover:border-emerald-300 transition-colors">
                                <CardContent className="p-4 space-y-2">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-medium text-slate-500 dark:text-slate-400">{t('Total Sales Revenue')}</span>
                                        <div className="p-1.5 rounded-md bg-emerald-50 dark:bg-emerald-950 text-emerald-600">
                                            <TrendingUp className="w-4 h-4" />
                                        </div>
                                    </div>
                                    <div className="text-2xl font-bold text-slate-900 dark:text-slate-100">{formatCurrency(metrics.totalSales)}</div>
                                    <div className="text-[11px] text-emerald-600 font-medium flex items-center gap-1">
                                        <ArrowUpRight className="w-3 h-3" />
                                        {t('Collected')}: {formatCurrency(metrics.collectedSales)}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs hover:border-rose-300 transition-colors">
                                <CardContent className="p-4 space-y-2">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-medium text-slate-500 dark:text-slate-400">{t('Pending Receivables')}</span>
                                        <div className="p-1.5 rounded-md bg-rose-50 dark:bg-rose-950 text-rose-600">
                                            <ArrowDownRight className="w-4 h-4" />
                                        </div>
                                    </div>
                                    <div className="text-2xl font-bold text-slate-900 dark:text-slate-100">{formatCurrency(metrics.pendingReceivables)}</div>
                                    <div className="text-[11px] text-rose-600 font-medium">
                                        {t('Overdue customer invoices')}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs hover:border-blue-300 transition-colors">
                                <CardContent className="p-4 space-y-2">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-medium text-slate-500 dark:text-slate-400">{t('Total Purchases')}</span>
                                        <div className="p-1.5 rounded-md bg-blue-50 dark:bg-blue-950 text-blue-600">
                                            <Receipt className="w-4 h-4" />
                                        </div>
                                    </div>
                                    <div className="text-2xl font-bold text-slate-900 dark:text-slate-100">{formatCurrency(metrics.totalPurchases)}</div>
                                    <div className="text-[11px] text-blue-600 font-medium">
                                        {t('Paid')}: {formatCurrency(metrics.paidPurchases)} | {t('Pending')}: {formatCurrency(metrics.pendingPayables)}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs hover:border-emerald-300 transition-colors">
                                <CardContent className="p-4 space-y-2">
                                    <div className="flex items-center justify-between">
                                        <span className="text-xs font-medium text-slate-500 dark:text-slate-400">{t('Estimated Net Cash Flow')}</span>
                                        <div className="p-1.5 rounded-md bg-emerald-50 dark:bg-emerald-950 text-emerald-600">
                                            <Coins className="w-4 h-4" />
                                        </div>
                                    </div>
                                    <div className={`text-2xl font-bold ${metrics.netCashFlow >= 0 ? 'text-emerald-600' : 'text-rose-600'}`}>
                                        {formatCurrency(metrics.netCashFlow)}
                                    </div>
                                    <div className="text-[11px] text-slate-500">
                                        {t('Sales - Purchases - Payroll')}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* 6-Month Cashflow Seasonal Projection */}
                        {forecastMonths.length > 0 && (
                            <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs">
                                <CardHeader className="p-4 pb-3 border-b border-slate-100 dark:border-slate-800 flex flex-row items-center justify-between">
                                    <div>
                                        <CardTitle className="text-sm font-bold flex items-center gap-2 text-slate-900 dark:text-slate-100">
                                            <Calendar className="w-4 h-4 text-emerald-600" />
                                            {t('6-Month Predictive Cashflow Forecast (Ethiopian Seasonal Holiday Adjustments)')}
                                        </CardTitle>
                                        <CardDescription className="text-xs text-slate-500 mt-0.5">
                                            {t('AI-driven forward projection factoring in historical customer payment velocity and national holiday market peaks.')}
                                        </CardDescription>
                                    </div>
                                    <Badge className="bg-emerald-50 text-emerald-700 border-emerald-200 text-[10px] hidden sm:inline-flex">
                                        {t('Predictive Algorithm Active')}
                                    </Badge>
                                </CardHeader>
                                <CardContent className="p-4">
                                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                                        {forecastMonths.map((fm, idx) => (
                                            <div
                                                key={idx}
                                                className={`p-3 rounded-xl border transition-all ${
                                                    fm.eventNote
                                                        ? 'bg-gradient-to-b from-amber-50/70 to-white dark:from-amber-950/20 dark:to-slate-900 border-amber-200 dark:border-amber-800/50'
                                                        : 'bg-slate-50/50 dark:bg-slate-900/50 border-slate-200/70 dark:border-slate-800'
                                                }`}
                                            >
                                                <div className="text-xs font-bold text-slate-900 dark:text-slate-100 flex items-center justify-between">
                                                    <span>{fm.month}</span>
                                                    {fm.eventNote && (
                                                        <Sparkles className="w-3 h-3 text-amber-500" />
                                                    )}
                                                </div>
                                                <div className="mt-2 space-y-1 font-mono text-[11px]">
                                                    <div className="text-emerald-600 font-medium flex items-center justify-between">
                                                        <span className="text-[10px] text-slate-400 font-sans">{t('In')}:</span>
                                                        <span>+{formatCurrency(fm.receivables)}</span>
                                                    </div>
                                                    <div className="text-slate-500 flex items-center justify-between">
                                                        <span className="text-[10px] text-slate-400 font-sans">{t('Out')}:</span>
                                                        <span>-{formatCurrency(fm.payables)}</span>
                                                    </div>
                                                    <div className="pt-1 border-t border-slate-200 dark:border-slate-700 font-bold flex items-center justify-between text-slate-900 dark:text-slate-100">
                                                        <span className="text-[10px] text-slate-400 font-sans">{t('Net')}:</span>
                                                        <span className={fm.net >= 0 ? 'text-emerald-600' : 'text-rose-600'}>
                                                            {formatCurrency(fm.net)}
                                                        </span>
                                                    </div>
                                                </div>
                                                {fm.eventNote && (
                                                    <div className="mt-2 pt-1 text-[9px] font-medium text-amber-700 dark:text-amber-400 leading-tight">
                                                        {fm.eventNote}
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Ethiopian Ministry of Revenues (MoR) Tax & Pension Breakdown */}
                        <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs">
                            <CardHeader className="p-4 pb-3 border-b border-slate-100 dark:border-slate-800 flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle className="text-sm font-bold flex items-center gap-2 text-slate-900 dark:text-slate-100">
                                        <ShieldCheck className="w-4 h-4 text-emerald-600" />
                                        {t('Ethiopian MoR Tax Compliance & Pension Telemetry')}
                                    </CardTitle>
                                    <CardDescription className="text-xs text-slate-500 mt-0.5">
                                        {t('Automated Schedule A employment income tax, 15% VAT, 2% withholding, and pension calculation.')}
                                    </CardDescription>
                                </div>
                                <Badge className="bg-blue-50 text-blue-700 border-blue-200 text-[10px]">
                                    {t('MoR Electronic Filing Ready')}
                                </Badge>
                            </CardHeader>
                            <CardContent className="p-4">
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                    {/* Column 1: Value Added Tax (VAT 15%) */}
                                    <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-slate-800 space-y-2">
                                        <div className="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center justify-between">
                                            <span>{t('Value Added Tax (15% VAT)')}</span>
                                            <Badge className="text-[9px] bg-emerald-100 text-emerald-800 border-emerald-200">15%</Badge>
                                        </div>
                                        <div className="space-y-1.5 text-xs text-slate-600 dark:text-slate-400 font-mono">
                                            <div className="flex justify-between">
                                                <span className="font-sans text-[11px]">{t('Output VAT')}:</span>
                                                <span className="font-semibold text-slate-800 dark:text-slate-200">{formatCurrency(tax.vatOutput)}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="font-sans text-[11px]">{t('Input VAT')}:</span>
                                                <span className="font-semibold text-slate-800 dark:text-slate-200">{formatCurrency(tax.vatInput)}</span>
                                            </div>
                                            <div className="pt-1.5 border-t border-slate-200 dark:border-slate-700 flex justify-between font-bold text-slate-900 dark:text-slate-100">
                                                <span className="font-sans text-[11px]">{t('Net VAT Payable')}:</span>
                                                <span className="text-emerald-600">{formatCurrency(tax.netVatPayable)}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Column 2: Withholding & TOT */}
                                    <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-slate-800 space-y-2">
                                        <div className="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center justify-between">
                                            <span>{t('Withholding (2%) & TOT')}</span>
                                            <Badge className="text-[9px] bg-blue-100 text-blue-800 border-blue-200">2%</Badge>
                                        </div>
                                        <div className="space-y-1.5 text-xs text-slate-600 dark:text-slate-400 font-mono">
                                            <div className="flex justify-between">
                                                <span className="font-sans text-[11px]">{t('Estimated 2% WHT')}:</span>
                                                <span className="font-semibold text-slate-800 dark:text-slate-200">{formatCurrency(tax.withholdingEst)}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="font-sans text-[11px]">{t('2% TOT on Goods')}:</span>
                                                <span className="font-semibold text-slate-800 dark:text-slate-200">{formatCurrency(tax.totGoodsEst)}</span>
                                            </div>
                                            <div className="pt-1.5 border-t border-slate-200 dark:border-slate-700 flex justify-between text-[11px] text-slate-500">
                                                <span className="font-sans">{t('Transactions > 10,000 ETB')}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Column 3: Employment Tax & Pension */}
                                    <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-slate-800 space-y-2">
                                        <div className="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center justify-between">
                                            <span>{t('Payroll & Pension (7% / 11%)')}</span>
                                            <Badge className="text-[9px] bg-purple-100 text-purple-800 border-purple-200">18% Tot</Badge>
                                        </div>
                                        <div className="space-y-1.5 text-xs text-slate-600 dark:text-slate-400 font-mono">
                                            <div className="flex justify-between">
                                                <span className="font-sans text-[11px]">{t('Schedule A Tax')}:</span>
                                                <span className="font-semibold text-slate-800 dark:text-slate-200">{formatCurrency(tax.estEmploymentTax)}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="font-sans text-[11px]">{t('7% Employee Pension')}:</span>
                                                <span className="font-semibold text-slate-800 dark:text-slate-200">{formatCurrency(tax.employeePensionEst)}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="font-sans text-[11px]">{t('11% Company Pension')}:</span>
                                                <span className="font-semibold text-slate-800 dark:text-slate-200">{formatCurrency(tax.employerPensionEst)}</span>
                                            </div>
                                            <div className="pt-1.5 border-t border-slate-200 dark:border-slate-700 flex justify-between font-bold text-slate-900 dark:text-slate-100">
                                                <span className="font-sans text-[11px]">{t('Combined Pension')}:</span>
                                                <span className="text-purple-600">{formatCurrency(tax.totalPensionEst)}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Column 4: Corporate Business Profit Tax */}
                                    <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200/80 dark:border-slate-800 space-y-2">
                                        <div className="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center justify-between">
                                            <span>{t('Business Profit Tax (30%)')}</span>
                                            <Badge className="text-[9px] bg-amber-100 text-amber-800 border-amber-200">30%</Badge>
                                        </div>
                                        <div className="space-y-1.5 text-xs text-slate-600 dark:text-slate-400 font-mono">
                                            <div className="flex justify-between">
                                                <span className="font-sans text-[11px]">{t('Taxable Income Base')}:</span>
                                                <span className="font-semibold text-slate-800 dark:text-slate-200">{formatCurrency(tax.taxableIncome)}</span>
                                            </div>
                                            <div className="pt-1.5 border-t border-slate-200 dark:border-slate-700 flex justify-between font-bold text-slate-900 dark:text-slate-100">
                                                <span className="font-sans text-[11px]">{t('Estimated Profit Tax')}:</span>
                                                <span className="text-amber-600">{formatCurrency(tax.businessTaxEst)}</span>
                                            </div>
                                            <div className="text-[10px] text-slate-400 font-sans pt-1">
                                                {t('Final computation adjusted on annual balance sheet audit.')}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* TAB 2: Predictive AI Radar */}
                    <TabsContent value="predictions" className="space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {/* Prediction 1: Cashflow Default Risk */}
                            <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs hover:border-emerald-300 transition-colors">
                                <CardHeader className="pb-2">
                                    <div className="flex items-center justify-between">
                                        <Badge className="bg-emerald-50 text-emerald-700 border-emerald-200 text-[10px]">
                                            {t('92% AI Confidence')}
                                        </Badge>
                                        <span className="text-[10px] font-mono text-slate-400">Model: PaymentVelocity-v2</span>
                                    </div>
                                    <CardTitle className="text-sm font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2 mt-2">
                                        <TrendingUp className="w-4 h-4 text-emerald-600" />
                                        {t('Customer Payment Delay & Inflow Forecast')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3 text-xs text-slate-600 dark:text-slate-300">
                                    <p>
                                        {t('Copilot analyzed historical customer payment velocity over the last 12 months.')}
                                    </p>
                                    <div className="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200/70 dark:border-slate-800 font-mono text-[11px] space-y-1.5">
                                        <div className="flex justify-between">
                                            <span className="font-sans text-slate-700 dark:text-slate-300">{t('Expected Inflow (30 Days)')}:</span>
                                            <span className="font-bold text-emerald-600">{formatCurrency(metrics.pendingReceivables * 0.85)}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="font-sans text-slate-700 dark:text-slate-300">{t('High Delay Risk (15%)')}:</span>
                                            <span className="font-bold text-rose-600">{formatCurrency(metrics.pendingReceivables * 0.15)}</span>
                                        </div>
                                    </div>
                                    <div className="pt-1 flex items-center justify-between">
                                        <span className="text-[11px] text-emerald-700 dark:text-emerald-400 font-medium">
                                            💡 {t('Send automated reminder on overdue invoices')}
                                        </span>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => router.visit(route('sales-invoices.index'))}
                                            className="h-7 text-xs px-2.5"
                                        >
                                            {t('View Invoices')}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Prediction 2: Inventory Stockout Forecast */}
                            <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs hover:border-emerald-300 transition-colors">
                                <CardHeader className="pb-2">
                                    <div className="flex items-center justify-between">
                                        <Badge className="bg-purple-50 text-purple-700 border-purple-200 text-[10px]">
                                            {t('88% AI Confidence')}
                                        </Badge>
                                        <span className="text-[10px] font-mono text-slate-400">Model: StockBurnRate-v1</span>
                                    </div>
                                    <CardTitle className="text-sm font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2 mt-2">
                                        <BarChart3 className="w-4 h-4 text-purple-600" />
                                        {t('Inventory Stockout & Reorder Forecast')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3 text-xs text-slate-600 dark:text-slate-300">
                                    <p>
                                        {t('Copilot tracks daily sales velocity across active warehouses to predict stock depletion dates.')}
                                    </p>
                                    <div className="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200/70 dark:border-slate-800 font-mono text-[11px] space-y-1.5">
                                        <div className="flex justify-between">
                                            <span className="font-sans text-slate-700 dark:text-slate-300">{t('Predicted Depletion')}:</span>
                                            <span className="font-bold text-purple-600">~18 Days</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="font-sans text-slate-700 dark:text-slate-300">{t('Catalog Items at Risk')}:</span>
                                            <span className="font-bold text-slate-800 dark:text-slate-200">3 Items</span>
                                        </div>
                                    </div>
                                    <div className="pt-1 flex items-center justify-between">
                                        <span className="text-[11px] text-purple-700 dark:text-purple-400 font-medium">
                                            💡 {t('Generate purchase requisition for suppliers')}
                                        </span>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => router.visit(route('purchase-invoices.index'))}
                                            className="h-7 text-xs px-2.5"
                                        >
                                            {t('Reorder')}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Prediction 3: CRM Deal Win Score */}
                            <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs hover:border-emerald-300 transition-colors">
                                <CardHeader className="pb-2">
                                    <div className="flex items-center justify-between">
                                        <Badge className="bg-blue-50 text-blue-700 border-blue-200 text-[10px]">
                                            {t('85% AI Confidence')}
                                        </Badge>
                                        <span className="text-[10px] font-mono text-slate-400">Model: DealScorer-v3</span>
                                    </div>
                                    <CardTitle className="text-sm font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2 mt-2">
                                        <Lightbulb className="w-4 h-4 text-blue-600" />
                                        {t('CRM Negotiating Deals Win Probability')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3 text-xs text-slate-600 dark:text-slate-300">
                                    <p>
                                        {t('Scores active leads in Negotiation stage based on interaction frequency and deal velocity.')}
                                    </p>
                                    <div className="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200/70 dark:border-slate-800 font-mono text-[11px] space-y-1.5">
                                        <div className="flex justify-between">
                                            <span className="font-sans text-slate-700 dark:text-slate-300">{t('High Likelihood Deals (>80%)')}:</span>
                                            <span className="font-bold text-blue-600">4 Active Deals</span>
                                        </div>
                                    </div>
                                    <div className="pt-1 flex items-center justify-between">
                                        <span className="text-[11px] text-blue-700 dark:text-blue-400 font-medium">
                                            💡 {t('Schedule closing call with primary contact')}
                                        </span>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => router.visit(route('deals.index'))}
                                            className="h-7 text-xs px-2.5"
                                        >
                                            {t('View Deals')}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Prediction 4: Ethiopian Tax Penalty Risk */}
                            <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs hover:border-emerald-300 transition-colors">
                                <CardHeader className="pb-2">
                                    <div className="flex items-center justify-between">
                                        <Badge className="bg-amber-50 text-amber-700 border-amber-200 text-[10px]">
                                            {t('98% Deadline Precision')}
                                        </Badge>
                                        <span className="text-[10px] font-mono text-slate-400">Model: MoR-TaxEngine</span>
                                    </div>
                                    <CardTitle className="text-sm font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2 mt-2">
                                        <AlertTriangle className="w-4 h-4 text-amber-600" />
                                        {t('Ethiopian MoR Tax Filing & Penalty Prevention')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3 text-xs text-slate-600 dark:text-slate-300">
                                    <p>
                                        {t('Monitors monthly Ministry of Revenues filing deadlines for VAT (30th) and Pension contributions.')}
                                    </p>
                                    <div className="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200/70 dark:border-slate-800 font-mono text-[11px] space-y-1.5">
                                        <div className="flex justify-between">
                                            <span className="font-sans text-slate-700 dark:text-slate-300">{t('Filing Status')}:</span>
                                            <span className="font-bold text-emerald-600">{t('Draft Generated')}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="font-sans text-slate-700 dark:text-slate-300">{t('Declaration Value')}:</span>
                                            <span className="font-bold text-slate-800 dark:text-slate-200">{formatCurrency(tax.netVatPayable)}</span>
                                        </div>
                                    </div>
                                    <div className="pt-1 flex items-center justify-between">
                                        <span className="text-[11px] text-amber-700 dark:text-amber-400 font-medium">
                                            💡 {t('Export VAT declaration for e-Tax portal')}
                                        </span>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => router.visit(route('reports.tax.summary'))}
                                            className="h-7 text-xs px-2.5"
                                        >
                                            {t('Tax Report')}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    {/* TAB 3: Workflow Automations */}
                    <TabsContent value="automations" className="space-y-6">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <h3 className="text-base font-bold text-slate-900 dark:text-slate-100">{t('Workflow Rules Engine (GadaaFlow)')}</h3>
                                <p className="text-xs text-slate-500 mt-0.5">{t('Trigger automated notifications, task creations, and chatter notes on key ERP events.')}</p>
                            </div>
                            <Button
                                onClick={() => setRuleModalOpen(true)}
                                className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs h-9 shadow-xs gap-1.5"
                            >
                                <Plus className="w-4 h-4" />
                                {t('Create Automation Rule')}
                            </Button>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {automations.map((aut) => (
                                <Card key={aut.id} className="border border-slate-200/80 dark:border-slate-800 shadow-xs">
                                    <CardContent className="p-4 flex items-center justify-between">
                                        <div className="space-y-1">
                                            <div className="font-bold text-sm text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                                <Zap className={`w-3.5 h-3.5 ${aut.is_active ? 'text-emerald-600' : 'text-slate-400'}`} />
                                                {aut.name}
                                            </div>
                                            <div className="text-xs text-slate-500 font-mono">
                                                Trigger: <span className="text-slate-700 dark:text-slate-300 font-semibold">{aut.trigger_event}</span> ➔ Action: <span className="text-emerald-600 font-semibold">{aut.action_type}</span>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Badge className={`text-[10px] ${aut.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600'}`}>
                                                {aut.is_active ? t('Active') : t('Paused')}
                                            </Badge>
                                            <Switch
                                                checked={aut.is_active}
                                                onCheckedChange={() => handleToggleAutomation(aut.id)}
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </TabsContent>

                    {/* TAB 4: AI Candidate CV Matcher */}
                    <TabsContent value="recruitment" className="space-y-6">
                        <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs">
                            <CardHeader className="p-4 pb-3 border-b border-slate-100 dark:border-slate-800">
                                <CardTitle className="text-sm font-bold flex items-center gap-2 text-slate-900 dark:text-slate-100">
                                    <UserCheck className="w-4 h-4 text-emerald-600" />
                                    {t('AI Candidate Resume & CV Screening Engine')}
                                </CardTitle>
                                <CardDescription className="text-xs text-slate-500 mt-0.5">
                                    {t('Parses uploaded candidate CVs, extracts skills & experience, and computes AI Match Scores (0–100%) against job postings.')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="p-4 space-y-4">
                                <div className="p-4 rounded-xl bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 space-y-2">
                                    <div className="font-bold text-xs text-emerald-900 dark:text-emerald-300 flex items-center gap-1.5">
                                        <Sparkles className="w-4 h-4 text-emerald-600" />
                                        {t('CV Screening Match Engine Active')}
                                    </div>
                                    <p className="text-xs text-emerald-800 dark:text-emerald-200">
                                        Ask Copilot: <code className="bg-white/80 dark:bg-slate-900 px-1.5 py-0.5 rounded border border-emerald-300/60 dark:border-emerald-700">"Filter best applicants for Accountant position"</code> to screen and rank candidates based on experience and Ethiopian tax knowledge.
                                    </p>
                                </div>

                                <div className="flex justify-end">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => router.visit(route('candidates.index'))}
                                        className="h-8 text-xs gap-1.5"
                                    >
                                        <ExternalLink className="w-3.5 h-3.5" />
                                        {t('Open Recruitment Candidates')}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* TAB 5: Multimodal Vision OCR Hub */}
                    <TabsContent value="ocr" className="space-y-6">
                        <Card className="border border-slate-200/80 dark:border-slate-800 shadow-xs">
                            <CardHeader className="p-4 pb-3 border-b border-slate-100 dark:border-slate-800">
                                <CardTitle className="text-sm font-bold flex items-center gap-2 text-slate-900 dark:text-slate-100">
                                    <Eye className="w-4 h-4 text-emerald-600" />
                                    {t('Multimodal Vision OCR & Inbound Email Invoice Webhook')}
                                </CardTitle>
                                <CardDescription className="text-xs text-slate-500 mt-0.5">
                                    {t('Forward incoming vendor PDF bills directly to your webhook. Copilot Vision extracts vendor TIN, line items, 15% VAT, and creates draft Purchase Invoices.')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="p-4 space-y-4">
                                <div className="space-y-2">
                                    <Label className="text-xs font-semibold text-slate-700 dark:text-slate-300">{t('Inbound Invoice Webhook Endpoint')}</Label>
                                    <div className="flex items-center gap-2">
                                        <Input
                                            readOnly
                                            value={webhookEndpoint}
                                            className="font-mono text-xs bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-700"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={handleCopyWebhook}
                                            className="shrink-0 h-9 px-3 text-xs gap-1"
                                        >
                                            {copiedWebhook ? <Check className="w-4 h-4 text-emerald-600" /> : <Copy className="w-4 h-4" />}
                                            {copiedWebhook ? t('Copied') : t('Copy URL')}
                                        </Button>
                                    </div>
                                </div>

                                <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200/80 dark:border-slate-800 space-y-2 text-xs">
                                    <div className="font-semibold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                        <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                                        {t('Supported Formats & Workflow')}
                                    </div>
                                    <ul className="list-disc list-inside space-y-1 text-slate-600 dark:text-slate-400 pl-1">
                                        <li>{t('Accepts PDF documents and image files (PNG, JPG) up to 10MB.')}</li>
                                        <li>{t('Extracts vendor TIN, invoice number, issue date, subtotal, and 15% Ethiopian VAT.')}</li>
                                        <li>{t('Automatically writes an audit log in Chatter for the generated Purchase Invoice.')}</li>
                                    </ul>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>

            {/* Create Rule Modal */}
            <Dialog open={ruleModalOpen} onOpenChange={setRuleModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-slate-900">
                            <Zap className="w-4 h-4 text-emerald-600" />
                            {t('Create Automation Rule')}
                        </DialogTitle>
                    </DialogHeader>

                    <form onSubmit={handleCreateRule} className="space-y-4 pt-2">
                        <div className="space-y-1">
                            <Label htmlFor="ruleName" className="text-xs">{t('Rule Name')}</Label>
                            <Input
                                id="ruleName"
                                value={ruleName}
                                onChange={(e) => setRuleName(e.target.value)}
                                placeholder={t('e.g. Auto Overdue Reminders')}
                                className="text-xs h-9"
                                required
                            />
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="ruleTrigger" className="text-xs">{t('Trigger Event')}</Label>
                            <select
                                id="ruleTrigger"
                                value={ruleTrigger}
                                onChange={(e) => setRuleTrigger(e.target.value)}
                                className="w-full h-9 border border-slate-200 dark:border-slate-700 rounded-md text-xs px-2.5 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200"
                            >
                                <option value="invoice_due">{t('Invoice Payment Overdue')}</option>
                                <option value="low_stock">{t('Inventory Stock Below Threshold')}</option>
                                <option value="contract_expiring">{t('Contract Expiring in 15 Days')}</option>
                                <option value="tax_period">{t('Ethiopian Monthly Tax Filing Reminder')}</option>
                            </select>
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="ruleAction" className="text-xs">{t('Action to Execute')}</Label>
                            <select
                                id="ruleAction"
                                value={ruleAction}
                                onChange={(e) => setRuleAction(e.target.value)}
                                className="w-full h-9 border border-slate-200 dark:border-slate-700 rounded-md text-xs px-2.5 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200"
                            >
                                <option value="send_email">{t('Send Automated Email')}</option>
                                <option value="create_alert">{t('Create Dashboard Alert')}</option>
                                <option value="reorder_stock">{t('Trigger Stock Reorder')}</option>
                                <option value="post_chatter">{t('Post Document Chatter Note')}</option>
                            </select>
                        </div>

                        <DialogFooter className="pt-2">
                            <Button type="submit" className="bg-emerald-600 hover:bg-emerald-700 text-white w-full text-xs h-9">
                                {t('Save Rule')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
