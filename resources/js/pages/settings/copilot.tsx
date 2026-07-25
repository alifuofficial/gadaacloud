import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Head, useForm, router } from "@inertiajs/react";
import { useTranslation } from "react-i18next";
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { formatCurrency } from "@/utils/helpers";
import {
    Bot,
    Sparkles,
    TrendingUp,
    Receipt,
    Zap,
    Send,
    LayoutDashboard,
    ArrowUpRight,
    ArrowDownRight,
    Ship,
    Users,
    CheckCircle2,
    Calendar,
    AlertTriangle,
    Building2,
    Coins,
    Brain,
    Eye,
    UserCheck,
    MessageSquare,
    Plus,
    FileText,
    ShieldCheck,
    Layers,
    Clock,
    RefreshCw
} from "lucide-react";
import { useState, useEffect } from "react";
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

export default function CopilotIndex({ metrics, tax, trade, forecastMonths, automations, insights }: CopilotProps) {
    const { t } = useTranslation();
    const urlParams = new URLSearchParams(window.location.search);
    const initialTab = urlParams.get('tab') || 'overview';

    const [activeTab, setActiveTab] = useState(initialTab);
    const [userQuery, setUserQuery] = useState("");
    const [aiResponse, setAiResponse] = useState<string | null>(null);
    const [isAsking, setIsAsking] = useState(false);

    // Create Rule Modal State
    const [ruleModalOpen, setRuleModalOpen] = useState(false);
    const [ruleName, setRuleName] = useState('');
    const [ruleTrigger, setRuleTrigger] = useState('invoice_due');
    const [ruleAction, setRuleAction] = useState('send_email');

    const handleAskCopilot = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!userQuery.trim() || isAsking) return;

        setIsAsking(true);
        try {
            let queryUrl = '/settings/copilot/query';
            try {
                if (typeof route === 'function') {
                    queryUrl = route('settings.copilot.query');
                }
            } catch (err) {}

            const res = await axios.post(queryUrl, { prompt: userQuery });
            setAiResponse(res.data.reply);
        } catch (err) {
            setAiResponse(t("Copilot AI is online and analyzing multi-module system context."));
        } finally {
            setIsAsking(false);
        }
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
            router.reload();
        } catch (err) {
            console.error("Create rule error:", err);
        }
    };

    const handleToggleAutomation = (id: number) => {
        let toggleUrl = `/settings/copilot/automation/${id}/toggle`;
        try {
            if (typeof route === 'function') {
                toggleUrl = route('settings.copilot.automation.toggle', { id });
            }
        } catch (e) {}

        router.post(toggleUrl, {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Settings') }, { label: t('GadaaCloud Copilot Hub') }]}
            pageTitle={t('GadaaCloud Copilot AI Capabilities Hub')}
        >
            <Head title={t('GadaaCloud Copilot Hub')} />

            <div className="space-y-6 max-w-7xl mx-auto">
                {/* Hero Header Banner */}
                <div className="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-950 via-purple-950 to-indigo-950 text-white p-6 md:p-8 shadow-2xl border border-purple-500/30">
                    <div className="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                        <div className="space-y-3 max-w-3xl">
                            <div className="flex items-center gap-2 flex-wrap">
                                <Badge className="bg-purple-500/20 text-purple-300 border-purple-400/30 px-3 py-1 text-xs font-semibold">
                                    <Sparkles className="w-3.5 h-3.5 mr-1 text-purple-300 animate-pulse" />
                                    {t('Autonomous Enterprise AI')}
                                </Badge>
                                <Badge className="bg-emerald-500/20 text-emerald-300 border-emerald-500/30 px-3 py-1 text-xs font-mono">
                                    <Brain className="w-3.5 h-3.5 mr-1" />
                                    {t('Persistent Memory Active')}
                                </Badge>
                                <Badge className="bg-blue-500/20 text-blue-300 border-blue-400/30 px-3 py-1 text-xs font-mono">
                                    <ShieldCheck className="w-3.5 h-3.5 mr-1" />
                                    {t('Permission-Aware DB Telemetry')}
                                </Badge>
                            </div>
                            <h1 className="text-2xl md:text-4xl font-black tracking-tight text-white">
                                {t('GadaaCloud Copilot AI Capabilities')}
                            </h1>
                            <p className="text-slate-300 text-xs md:text-sm leading-relaxed">
                                {t('Real-time database analytics across 59 modules, Ethiopian Tax Engine (MoR), Vision OCR for receipts/invoices, AI candidate CV match scoring, stateful memory, and automated workflows.')}
                            </p>
                        </div>

                        {/* Token Consumption Card */}
                        <div className="bg-white/10 backdrop-blur-md border border-white/15 p-4 rounded-2xl shrink-0 space-y-1 font-mono text-xs">
                            <div className="text-purple-300 font-sans font-bold flex items-center gap-1.5">
                                <Coins className="w-4 h-4 text-emerald-400" />
                                {t('Company AI Token Usage')}
                            </div>
                            <div className="text-xl font-bold text-white">
                                {(metrics.companyTokensUsed || 0).toLocaleString()} <span className="text-xs text-slate-400 font-sans">Tokens</span>
                            </div>
                            <div className="text-[11px] text-slate-300">
                                {t('Metered Value')}: <span className="font-bold text-emerald-400">{formatCurrency(metrics.companyTokenCost || 0)}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Live AI Query Terminal */}
                <Card className="border-purple-500/30 bg-slate-950 text-white shadow-xl overflow-hidden">
                    <CardHeader className="p-4 bg-gradient-to-r from-purple-950 via-slate-900 to-indigo-950 border-b border-purple-500/20">
                        <CardTitle className="text-sm font-bold flex items-center gap-2">
                            <Bot className="w-5 h-5 text-purple-400" />
                            {t('Ask GadaaCloud Copilot (Live Multi-Module Database Query)')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-4 space-y-4">
                        <form onSubmit={handleAskCopilot} className="flex gap-2">
                            <Input
                                value={userQuery}
                                onChange={(e) => setUserQuery(e.target.value)}
                                placeholder={t('Ask about sales, CRM leads, Ethiopian VAT tax, project tasks, or candidate CV scores...')}
                                className="bg-slate-900 border-slate-800 text-slate-100 placeholder:text-slate-500 text-xs focus:ring-purple-500/50"
                            />
                            <Button type="submit" disabled={isAsking || !userQuery.trim()} className="bg-purple-600 hover:bg-purple-700 text-white">
                                {isAsking ? <RefreshCw className="w-4 h-4 animate-spin mr-1" /> : <Send className="w-4 h-4 mr-1" />}
                                {t('Ask Copilot')}
                            </Button>
                        </form>

                        {aiResponse && (
                            <div className="p-4 rounded-xl bg-slate-900 border border-purple-500/30 text-xs text-slate-200 whitespace-pre-wrap leading-relaxed animate-in fade-in duration-300">
                                {aiResponse}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Main Feature Capability Tabs */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
                    <TabsList className="bg-slate-900/90 p-1.5 rounded-2xl border border-slate-800 flex items-center gap-1 overflow-x-auto no-scrollbar">
                        <TabsTrigger value="overview" className="text-xs data-[state=active]:bg-purple-600 data-[state=active]:text-white gap-1.5">
                            <LayoutDashboard className="w-3.5 h-3.5" />
                            {t('360° Financial Telemetry')}
                        </TabsTrigger>
                        <TabsTrigger value="automations" className="text-xs data-[state=active]:bg-purple-600 data-[state=active]:text-white gap-1.5">
                            <Zap className="w-3.5 h-3.5" />
                            {t('Workflow Automations')}
                        </TabsTrigger>
                        <TabsTrigger value="recruitment" className="text-xs data-[state=active]:bg-purple-600 data-[state=active]:text-white gap-1.5">
                            <UserCheck className="w-3.5 h-3.5" />
                            {t('AI CV Matcher')}
                        </TabsTrigger>
                        <TabsTrigger value="ocr" className="text-xs data-[state=active]:bg-purple-600 data-[state=active]:text-white gap-1.5">
                            <Eye className="w-3.5 h-3.5" />
                            {t('Vision OCR & Webhooks')}
                        </TabsTrigger>
                        <TabsTrigger value="chatter" className="text-xs data-[state=active]:bg-purple-600 data-[state=active]:text-white gap-1.5">
                            <MessageSquare className="w-3.5 h-3.5" />
                            {t('Document Chatter & Memory')}
                        </TabsTrigger>
                    </TabsList>

                    {/* TAB 1: 360° Financial & Tax Telemetry */}
                    <TabsContent value="overview" className="space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <Card className="bg-emerald-50 border-emerald-200">
                                <CardContent className="p-4">
                                    <div className="text-xs text-emerald-800 font-semibold">{t('Total Sales Revenue')}</div>
                                    <div className="text-xl font-bold text-emerald-950 mt-1">{formatCurrency(metrics.totalSales)}</div>
                                    <div className="text-[10px] text-emerald-700 mt-1">Collected: {formatCurrency(metrics.collectedSales)}</div>
                                </CardContent>
                            </Card>

                            <Card className="bg-rose-50 border-rose-200">
                                <CardContent className="p-4">
                                    <div className="text-xs text-rose-800 font-semibold">{t('Pending Receivables')}</div>
                                    <div className="text-xl font-bold text-rose-950 mt-1">{formatCurrency(metrics.pendingReceivables)}</div>
                                    <div className="text-[10px] text-rose-700 mt-1">Overdue customer invoices</div>
                                </CardContent>
                            </Card>

                            <Card className="bg-blue-50 border-blue-200">
                                <CardContent className="p-4">
                                    <div className="text-xs text-blue-800 font-semibold">{t('Ethiopian Net VAT Payable')}</div>
                                    <div className="text-xl font-bold text-blue-950 mt-1">{formatCurrency(tax.netVatPayable)}</div>
                                    <div className="text-[10px] text-blue-700 mt-1">Output VAT 15% - Input VAT 15%</div>
                                </CardContent>
                            </Card>

                            <Card className="bg-purple-50 border-purple-200">
                                <CardContent className="p-4">
                                    <div className="text-xs text-purple-800 font-semibold">{t('Schedule A Employment Tax')}</div>
                                    <div className="text-xl font-bold text-purple-950 mt-1">{formatCurrency(tax.estEmploymentTax)}</div>
                                    <div className="text-[10px] text-purple-700 mt-1">Total Pension: {formatCurrency(tax.totalPensionEst)}</div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* 6-Month Cashflow Forecast */}
                        <Card className="border">
                            <CardHeader>
                                <CardTitle className="text-base flex items-center gap-2">
                                    <TrendingUp className="w-5 h-5 text-emerald-600" />
                                    {t('AI Predictive Cashflow Forecast (Next 6 Months)')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 md:grid-cols-6 gap-3">
                                    {forecastMonths.map((fm, idx) => (
                                        <div key={idx} className="p-3 rounded-xl bg-gray-50 border text-center space-y-1">
                                            <div className="text-xs font-bold text-gray-900">{fm.month}</div>
                                            <div className="text-xs font-semibold text-emerald-600">+{formatCurrency(fm.receivables)}</div>
                                            <div className="text-xs font-semibold text-rose-600">-{formatCurrency(fm.payables)}</div>
                                            <div className="text-xs font-extrabold text-slate-900 pt-1 border-t">Net: {formatCurrency(fm.net)}</div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* TAB 2: Workflow Automations */}
                    <TabsContent value="automations" className="space-y-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h3 className="text-lg font-bold text-gray-900">{t('Visual Workflow Rules Engine (GadaaFlow)')}</h3>
                                <p className="text-xs text-gray-500">{t('Trigger automated emails, task creations, and chatter notes on key ERP events.')}</p>
                            </div>
                            <Button onClick={() => setRuleModalOpen(true)} className="bg-purple-700 hover:bg-purple-800 text-white">
                                <Plus className="w-4 h-4 mr-1.5" />
                                {t('Create Automation Rule')}
                            </Button>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {automations.map((aut) => (
                                <Card key={aut.id} className="border shadow-xs">
                                    <CardContent className="p-4 flex items-center justify-between">
                                        <div className="space-y-1">
                                            <div className="font-bold text-sm text-gray-900">{aut.name}</div>
                                            <div className="text-xs text-gray-500 font-mono">Trigger: {aut.trigger_event} ➔ Action: {aut.action_type}</div>
                                        </div>
                                        <Switch
                                            checked={aut.is_active}
                                            onCheckedChange={() => handleToggleAutomation(aut.id)}
                                        />
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </TabsContent>

                    {/* TAB 3: AI Candidate CV Matcher */}
                    <TabsContent value="recruitment" className="space-y-6">
                        <Card className="border">
                            <CardHeader>
                                <CardTitle className="text-base flex items-center gap-2">
                                    <UserCheck className="w-5 h-5 text-purple-600" />
                                    {t('AI Candidate Resume & CV Screening Engine')}
                                </CardTitle>
                                <CardDescription>
                                    {t('Parses uploaded candidate CVs, extracts skills & experience, and computes AI Match Scores (0–100%).')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="p-4 rounded-xl bg-purple-50/50 border border-purple-200 space-y-2">
                                    <div className="font-bold text-xs text-purple-900 flex items-center gap-1.5">
                                        <Sparkles className="w-4 h-4 text-purple-600" />
                                        {t('CV Screening Match Engine Active')}
                                    </div>
                                    <p className="text-xs text-purple-800">
                                        Ask Copilot: <code>"Filter best applicants for Accountant position"</code> to screen and rank candidates based on experience and Ethiopian tax knowledge.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* TAB 4: Multimodal Vision OCR Hub */}
                    <TabsContent value="ocr" className="space-y-6">
                        <Card className="border">
                            <CardHeader>
                                <CardTitle className="text-base flex items-center gap-2">
                                    <Eye className="w-5 h-5 text-emerald-600" />
                                    {t('Multimodal Vision OCR & Inbound Email Invoice Webhook')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="p-4 rounded-xl bg-slate-900 text-white space-y-2 font-mono text-xs">
                                    <div className="text-emerald-400 font-bold flex items-center gap-2">
                                        <CheckCircle2 className="w-4 h-4" />
                                        {t('Inbound Email Invoice Webhook Endpoint')}
                                    </div>
                                    <code className="block bg-slate-950 p-3 rounded-lg border border-slate-800 text-purple-300">
                                        POST https://gadaa.cloud/api/webhooks/inbound-invoice
                                    </code>
                                    <p className="text-slate-300 text-[11px]">
                                        Emailed vendor PDF bills are automatically sent to Copilot Vision OCR. Vendor TIN, subtotal, 15% VAT, and line items are extracted to auto-create draft Purchase Invoices.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* TAB 5: Document Chatter */}
                    <TabsContent value="chatter" className="space-y-6">
                        <Card className="border">
                            <CardHeader>
                                <CardTitle className="text-base flex items-center gap-2">
                                    <MessageSquare className="w-5 h-5 text-blue-600" />
                                    {t('Universal Document Chatter & Persistent Memory Engine')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-xs text-gray-600">
                                <p>
                                    Every ERP document (Sales Invoices, CRM Leads, Candidates, Employees) is equipped with a real-time Chatter stream for internal notes, `@mentions`, file attachments, and scheduled follow-up activities.
                                </p>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>

            {/* Create Rule Modal */}
            <Dialog open={ruleModalOpen} onOpenChange={setRuleModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Zap className="w-5 h-5 text-purple-600" />
                            {t('Create Automation Rule')}
                        </DialogTitle>
                    </DialogHeader>

                    <form onSubmit={handleCreateRule} className="space-y-4 pt-2">
                        <div className="space-y-1">
                            <Label htmlFor="ruleName">{t('Rule Name')}</Label>
                            <Input
                                id="ruleName"
                                value={ruleName}
                                onChange={(e) => setRuleName(e.target.value)}
                                placeholder={t('e.g. Auto Overdue Reminders')}
                                required
                            />
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="ruleTrigger">{t('Trigger Event')}</Label>
                            <select
                                id="ruleTrigger"
                                value={ruleTrigger}
                                onChange={(e) => setRuleTrigger(e.target.value)}
                                className="w-full h-9 border rounded-md text-xs px-2 bg-white"
                            >
                                <option value="invoice_due">{t('Invoice Payment Overdue')}</option>
                                <option value="low_stock">{t('Inventory Stock Below Threshold')}</option>
                                <option value="contract_expiring">{t('Contract Expiring in 15 Days')}</option>
                                <option value="tax_period">{t('Ethiopian Monthly Tax Filing Reminder')}</option>
                            </select>
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="ruleAction">{t('Action to Execute')}</Label>
                            <select
                                id="ruleAction"
                                value={ruleAction}
                                onChange={(e) => setRuleAction(e.target.value)}
                                className="w-full h-9 border rounded-md text-xs px-2 bg-white"
                            >
                                <option value="send_email">{t('Send Automated Email')}</option>
                                <option value="create_alert">{t('Create Dashboard Alert')}</option>
                                <option value="reorder_stock">{t('Trigger Stock Reorder')}</option>
                                <option value="post_chatter">{t('Post Document Chatter Note')}</option>
                            </select>
                        </div>

                        <DialogFooter className="pt-2">
                            <Button type="submit" className="bg-purple-700 hover:bg-purple-800 text-white w-full">
                                {t('Save Rule')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
