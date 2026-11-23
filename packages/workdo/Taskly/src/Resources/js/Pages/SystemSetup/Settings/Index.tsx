import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import SystemSetupSidebar from "../SystemSetupSidebar";
import {
    SlidersHorizontal,
    Building2,
    Briefcase,
    HardHat,
    Ship,
    TrendingUp,
    Clock,
    ShieldCheck,
    AlertTriangle,
    Save,
    CheckCircle2,
    Users,
    FileSpreadsheet,
    DollarSign,
    GitGraph,
    Sparkles
} from "lucide-react";

interface ProjectSettingsProps {
    settings: {
        taskly_default_project_scope?: string;
        taskly_default_internal_category?: string;
        taskly_default_currency?: string;
        taskly_default_hourly_rate?: string | number;
        taskly_enable_evm?: string;
        taskly_enable_construction?: string;
        taskly_enable_logistics?: string;
        taskly_enable_gantt_dependencies?: string;
        taskly_enable_milestone_approvals?: string;
        taskly_demurrage_free_days?: string | number;
        taskly_demurrage_daily_rate?: string | number;
        taskly_demurrage_alert_days?: string | number;
        taskly_timesheet_approval_required?: string;
        taskly_allow_billable_hours?: string;
        taskly_max_daily_hours?: string | number;
        taskly_auto_generate_magic_link?: string;
        taskly_client_milestone_approval?: string;
        taskly_client_hide_financials?: string;
        taskly_client_show_subtasks?: string;
        taskly_cpi_warning_threshold?: string | number;
        taskly_spi_warning_threshold?: string | number;
        [key: string]: any;
    };
}

export default function SettingsIndex() {
    const { t } = useTranslation();
    const { settings = {} } = usePage<ProjectSettingsProps>().props;

    const [formData, setFormData] = useState({
        taskly_default_project_scope: settings.taskly_default_project_scope || 'client',
        taskly_default_internal_category: settings.taskly_default_internal_category || 'opex',
        taskly_default_currency: settings.taskly_default_currency || 'ETB',
        taskly_default_hourly_rate: settings.taskly_default_hourly_rate || '50',
        taskly_enable_evm: settings.taskly_enable_evm !== 'off',
        taskly_enable_construction: settings.taskly_enable_construction !== 'off',
        taskly_enable_logistics: settings.taskly_enable_logistics !== 'off',
        taskly_enable_gantt_dependencies: settings.taskly_enable_gantt_dependencies !== 'off',
        taskly_enable_milestone_approvals: settings.taskly_enable_milestone_approvals !== 'off',
        taskly_demurrage_free_days: settings.taskly_demurrage_free_days || '7',
        taskly_demurrage_daily_rate: settings.taskly_demurrage_daily_rate || '150',
        taskly_demurrage_alert_days: settings.taskly_demurrage_alert_days || '3',
        taskly_timesheet_approval_required: settings.taskly_timesheet_approval_required === 'on',
        taskly_allow_billable_hours: settings.taskly_allow_billable_hours !== 'off',
        taskly_max_daily_hours: settings.taskly_max_daily_hours || '12',
        taskly_auto_generate_magic_link: settings.taskly_auto_generate_magic_link !== 'off',
        taskly_client_milestone_approval: settings.taskly_client_milestone_approval !== 'off',
        taskly_client_hide_financials: settings.taskly_client_hide_financials !== 'off',
        taskly_client_show_subtasks: settings.taskly_client_show_subtasks !== 'off',
        taskly_cpi_warning_threshold: settings.taskly_cpi_warning_threshold || '0.90',
        taskly_spi_warning_threshold: settings.taskly_spi_warning_threshold || '0.90',
    });

    const [isSaving, setIsSaving] = useState(false);
    const [savedSuccess, setSavedSuccess] = useState(false);

    const handleToggle = (key: string, checked: boolean) => {
        setFormData(prev => ({ ...prev, [key]: checked }));
    };

    const handleChange = (key: string, value: any) => {
        setFormData(prev => ({ ...prev, [key]: value }));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSaving(true);
        setSavedSuccess(false);

        router.post(route('project.settings.store'), {
            settings: {
                ...formData,
                taskly_enable_evm: formData.taskly_enable_evm ? 'on' : 'off',
                taskly_enable_construction: formData.taskly_enable_construction ? 'on' : 'off',
                taskly_enable_logistics: formData.taskly_enable_logistics ? 'on' : 'off',
                taskly_enable_gantt_dependencies: formData.taskly_enable_gantt_dependencies ? 'on' : 'off',
                taskly_enable_milestone_approvals: formData.taskly_enable_milestone_approvals ? 'on' : 'off',
                taskly_timesheet_approval_required: formData.taskly_timesheet_approval_required ? 'on' : 'off',
                taskly_allow_billable_hours: formData.taskly_allow_billable_hours ? 'on' : 'off',
                taskly_auto_generate_magic_link: formData.taskly_auto_generate_magic_link ? 'on' : 'off',
                taskly_client_milestone_approval: formData.taskly_client_milestone_approval ? 'on' : 'off',
                taskly_client_hide_financials: formData.taskly_client_hide_financials ? 'on' : 'off',
                taskly_client_show_subtasks: formData.taskly_client_show_subtasks ? 'on' : 'off',
            }
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsSaving(false);
                setSavedSuccess(true);
                setTimeout(() => setSavedSuccess(false), 4000);
            },
            onError: () => {
                setIsSaving(false);
            }
        });
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Project'), url: route('project.index') },
                { label: t('System Setup') },
                { label: t('Project Preferences') }
            ]}
            pageTitle={t('System Setup')}
        >
            <Head title={t('Project Preferences')} />

            <div className="flex flex-col md:flex-row gap-8">
                {/* System Setup Sidebar */}
                <div className="md:w-64 flex-shrink-0">
                    <SystemSetupSidebar activeItem="settings" />
                </div>

                {/* Main Settings Panel */}
                <div className="flex-1 space-y-6">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b">
                        <div>
                            <div className="flex items-center gap-2">
                                <SlidersHorizontal className="h-6 w-6 text-primary" />
                                <h2 className="text-xl font-bold tracking-tight text-slate-900 dark:text-white">
                                    {t('Project Preferences & Configurations')}
                                </h2>
                            </div>
                            <p className="text-sm text-muted-foreground mt-1">
                                {t('Customize default scopes, active industry workspaces, EVM thresholds, and portal permissions according to your company requirements.')}
                            </p>
                        </div>

                        <div className="flex items-center gap-2">
                            {savedSuccess && (
                                <span className="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 px-3 py-1.5 rounded-full border border-emerald-200 dark:border-emerald-800 animate-in fade-in">
                                    <CheckCircle2 className="h-4 w-4" />
                                    {t('Preferences Saved!')}
                                </span>
                            )}
                            <Button
                                onClick={handleSubmit}
                                disabled={isSaving}
                                className="shadow-sm flex items-center gap-2"
                            >
                                <Save className="h-4 w-4" />
                                {isSaving ? t('Saving...') : t('Save Preferences')}
                            </Button>
                        </div>
                    </div>

                    <form onSubmit={handleSubmit}>
                        <Tabs defaultValue="scope" className="w-full space-y-6">
                            <TabsList className="bg-slate-100 dark:bg-slate-800 p-1 rounded-xl flex flex-wrap gap-1 h-auto">
                                <TabsTrigger value="scope" className="flex items-center gap-1.5 text-xs sm:text-sm font-medium">
                                    <Building2 className="h-4 w-4" />
                                    <span>{t('Scope & Defaults')}</span>
                                </TabsTrigger>
                                <TabsTrigger value="industry" className="flex items-center gap-1.5 text-xs sm:text-sm font-medium">
                                    <Briefcase className="h-4 w-4" />
                                    <span>{t('Industry Workspaces')}</span>
                                </TabsTrigger>
                                <TabsTrigger value="logistics" className="flex items-center gap-1.5 text-xs sm:text-sm font-medium">
                                    <Ship className="h-4 w-4" />
                                    <span>{t('Logistics & Demurrage')}</span>
                                </TabsTrigger>
                                <TabsTrigger value="timesheets" className="flex items-center gap-1.5 text-xs sm:text-sm font-medium">
                                    <Clock className="h-4 w-4" />
                                    <span>{t('Timesheets & Governance')}</span>
                                </TabsTrigger>
                                <TabsTrigger value="portal" className="flex items-center gap-1.5 text-xs sm:text-sm font-medium">
                                    <ShieldCheck className="h-4 w-4" />
                                    <span>{t('Client Portal & Security')}</span>
                                </TabsTrigger>
                                <TabsTrigger value="evm" className="flex items-center gap-1.5 text-xs sm:text-sm font-medium">
                                    <TrendingUp className="h-4 w-4" />
                                    <span>{t('EVM Risk Thresholds')}</span>
                                </TabsTrigger>
                            </TabsList>

                            {/* TAB 1: Project Scope & Operational Defaults */}
                            <TabsContent value="scope" className="space-y-6">
                                <Card className="shadow-sm border-slate-200 dark:border-slate-800">
                                    <CardHeader>
                                        <CardTitle className="text-base flex items-center gap-2">
                                            <Building2 className="h-4 w-4 text-blue-600" />
                                            {t('Default Operational Scope')}
                                        </CardTitle>
                                        <CardDescription>
                                            {t('Choose how projects in your company are structured by default. You can still customize each project upon creation.')}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-6">
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            {/* Client Facing Option */}
                                            <div
                                                onClick={() => handleChange('taskly_default_project_scope', 'client')}
                                                className={`p-4 rounded-xl border-2 cursor-pointer transition-all ${
                                                    formData.taskly_default_project_scope === 'client'
                                                        ? 'border-blue-600 bg-blue-50/50 dark:bg-blue-950/20 shadow-sm'
                                                        : 'border-slate-200 dark:border-slate-800 hover:border-slate-300'
                                                }`}
                                            >
                                                <div className="flex items-center justify-between mb-2">
                                                    <div className="flex items-center gap-2">
                                                        <Users className="h-5 w-5 text-blue-600" />
                                                        <h4 className="font-semibold text-sm text-slate-900 dark:text-white">
                                                            {t('Client-Facing Delivery')}
                                                        </h4>
                                                    </div>
                                                    {formData.taskly_default_project_scope === 'client' && (
                                                        <Badge variant="default" className="bg-blue-600 text-white text-[10px]">
                                                            {t('Company Default')}
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="text-xs text-muted-foreground leading-relaxed">
                                                    {t('Recommended for agencies, general contractors, import/export traders, and consulting firms delivering services to external clients.')}
                                                </p>
                                            </div>

                                            {/* In-House Option */}
                                            <div
                                                onClick={() => handleChange('taskly_default_project_scope', 'internal')}
                                                className={`p-4 rounded-xl border-2 cursor-pointer transition-all ${
                                                    formData.taskly_default_project_scope === 'internal'
                                                        ? 'border-purple-600 bg-purple-50/50 dark:bg-purple-950/20 shadow-sm'
                                                        : 'border-slate-200 dark:border-slate-800 hover:border-slate-300'
                                                }`}
                                            >
                                                <div className="flex items-center justify-between mb-2">
                                                    <div className="flex items-center gap-2">
                                                        <Building2 className="h-5 w-5 text-purple-600" />
                                                        <h4 className="font-semibold text-sm text-slate-900 dark:text-white">
                                                            {t('In-House / Internal Initiative')}
                                                        </h4>
                                                    </div>
                                                    {formData.taskly_default_project_scope === 'internal' && (
                                                        <Badge variant="default" className="bg-purple-600 text-white text-[10px]">
                                                            {t('Company Default')}
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="text-xs text-muted-foreground leading-relaxed">
                                                    {t('Ideal for manufacturing plants, enterprises, conglomerates, and internal PMOs managing CapEx, OpEx, R&D, and cost-reduction initiatives.')}
                                                </p>
                                            </div>
                                        </div>

                                        <Separator />

                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                            {/* Default Internal Accounting Class */}
                                            <div className="space-y-2">
                                                <Label className="text-xs font-semibold">
                                                    {t('Default Internal Accounting Class')}
                                                </Label>
                                                <Select
                                                    value={formData.taskly_default_internal_category}
                                                    onValueChange={(val) => handleChange('taskly_default_internal_category', val)}
                                                >
                                                    <SelectTrigger className="w-full">
                                                        <SelectValue placeholder={t('Select class')} />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="opex">{t('OpEx (Operational Improvement)')}</SelectItem>
                                                        <SelectItem value="capex">{t('CapEx (Capital Asset Investment)')}</SelectItem>
                                                        <SelectItem value="rd">{t('R&D (Research & Development)')}</SelectItem>
                                                        <SelectItem value="strategic">{t('Strategic Corporate PMO')}</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <p className="text-[11px] text-muted-foreground">
                                                    {t('Pre-selected classification when creating in-house corporate projects.')}
                                                </p>
                                            </div>

                                            {/* Default Currency */}
                                            <div className="space-y-2">
                                                <Label className="text-xs font-semibold">
                                                    {t('Default Project Currency')}
                                                </Label>
                                                <Select
                                                    value={formData.taskly_default_currency}
                                                    onValueChange={(val) => handleChange('taskly_default_currency', val)}
                                                >
                                                    <SelectTrigger className="w-full">
                                                        <SelectValue placeholder={t('Select currency')} />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="ETB">ETB - Ethiopian Birr</SelectItem>
                                                        <SelectItem value="USD">USD - US Dollar ($)</SelectItem>
                                                        <SelectItem value="EUR">EUR - Euro (€)</SelectItem>
                                                        <SelectItem value="GBP">GBP - British Pound (£)</SelectItem>
                                                        <SelectItem value="AED">AED - UAE Dirham</SelectItem>
                                                        <SelectItem value="CNY">CNY - Chinese Yuan (¥)</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <p className="text-[11px] text-muted-foreground">
                                                    {t('Standard currency code applied to newly created projects and BoQ cost items.')}
                                                </p>
                                            </div>

                                            {/* Default Loaded Labor Rate */}
                                            <div className="space-y-2">
                                                <Label className="text-xs font-semibold">
                                                    {t('Standard Loaded Hourly Labor Rate')}
                                                </Label>
                                                <div className="relative">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={formData.taskly_default_hourly_rate}
                                                        onChange={(e) => handleChange('taskly_default_hourly_rate', e.target.value)}
                                                        className="pr-14"
                                                    />
                                                    <span className="absolute right-3 top-2.5 text-xs text-muted-foreground font-mono">
                                                        / hr
                                                    </span>
                                                </div>
                                                <p className="text-[11px] text-muted-foreground">
                                                    {t('Baseline labor burn cost rate used in EVM calculations when member rate is blank.')}
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            {/* TAB 2: Industry Workspaces & Feature Flags */}
                            <TabsContent value="industry" className="space-y-6">
                                <Card className="shadow-sm border-slate-200 dark:border-slate-800">
                                    <CardHeader>
                                        <CardTitle className="text-base flex items-center gap-2">
                                            <Briefcase className="h-4 w-4 text-emerald-600" />
                                            {t('Industry Workspace Modules & Feature Toggles')}
                                        </CardTitle>
                                        <CardDescription>
                                            {t('Enable or disable specialized workspace modules to match your exact business domain.')}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-5">
                                        {/* Construction Workspace */}
                                        <div className="flex items-start justify-between p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-amber-50/20">
                                            <div className="flex items-start gap-3">
                                                <div className="p-2 bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 rounded-lg">
                                                    <HardHat className="h-5 w-5" />
                                                </div>
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <h4 className="font-semibold text-sm text-slate-900 dark:text-white">
                                                            {t('Construction Workspace (BoQ & Daily Site Diary)')}
                                                        </h4>
                                                        <Badge variant="outline" className="border-amber-400 text-amber-700 text-[10px]">
                                                            {t('Civil & Contracting')}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-xs text-muted-foreground mt-1 max-w-xl">
                                                        {t('Enables itemized Bill of Quantities (BoQ) tracking, subcontractor performance measurement, and daily site supervisor diaries (weather, labor headcount, equipment logs).')}
                                                    </p>
                                                </div>
                                            </div>
                                            <Switch
                                                checked={formData.taskly_enable_construction}
                                                onCheckedChange={(checked) => handleToggle('taskly_enable_construction', checked)}
                                            />
                                        </div>

                                        {/* Import / Export Logistics Workspace */}
                                        <div className="flex items-start justify-between p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-blue-50/20">
                                            <div className="flex items-start gap-3">
                                                <div className="p-2 bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 rounded-lg">
                                                    <Ship className="h-5 w-5" />
                                                </div>
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <h4 className="font-semibold text-sm text-slate-900 dark:text-white">
                                                            {t('Import/Export Maritime Logistics & Demurrage Clock')}
                                                        </h4>
                                                        <Badge variant="outline" className="border-blue-400 text-blue-700 text-[10px]">
                                                            {t('Global Trade & Supply Chain')}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-xs text-muted-foreground mt-1 max-w-xl">
                                                        {t('Enables container tracking, Letter of Credit (LC) milestones, Bill of Lading (BL) management, and live port container demurrage penalty countdown clocks.')}
                                                    </p>
                                                </div>
                                            </div>
                                            <Switch
                                                checked={formData.taskly_enable_logistics}
                                                onCheckedChange={(checked) => handleToggle('taskly_enable_logistics', checked)}
                                            />
                                        </div>

                                        {/* Earned Value Management (EVM) */}
                                        <div className="flex items-start justify-between p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-indigo-50/20">
                                            <div className="flex items-start gap-3">
                                                <div className="p-2 bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 rounded-lg">
                                                    <TrendingUp className="h-5 w-5" />
                                                </div>
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <h4 className="font-semibold text-sm text-slate-900 dark:text-white">
                                                            {t('Earned Value Management (EVM) Financial Analysis')}
                                                        </h4>
                                                        <Badge variant="outline" className="border-indigo-400 text-indigo-700 text-[10px]">
                                                            {t('Enterprise PMO')}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-xs text-muted-foreground mt-1 max-w-xl">
                                                        {t('Calculates Planned Value (PV), Earned Value (EV), Actual Cost (AC), Cost Variance (CV), and Cost & Schedule Performance Indices (CPI / SPI) across project overviews.')}
                                                    </p>
                                                </div>
                                            </div>
                                            <Switch
                                                checked={formData.taskly_enable_evm}
                                                onCheckedChange={(checked) => handleToggle('taskly_enable_evm', checked)}
                                            />
                                        </div>

                                        {/* Gantt Dependencies */}
                                        <div className="flex items-start justify-between p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/40">
                                            <div className="flex items-start gap-3">
                                                <div className="p-2 bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg">
                                                    <GitGraph className="h-5 w-5" />
                                                </div>
                                                <div>
                                                    <h4 className="font-semibold text-sm text-slate-900 dark:text-white">
                                                        {t('Gantt Task Dependencies & Critical Path')}
                                                    </h4>
                                                    <p className="text-xs text-muted-foreground mt-1 max-w-xl">
                                                        {t('Allows linking predecessor tasks with Finish-to-Start dependency constraints and visual warning highlights for schedule bottlenecks.')}
                                                    </p>
                                                </div>
                                            </div>
                                            <Switch
                                                checked={formData.taskly_enable_gantt_dependencies}
                                                onCheckedChange={(checked) => handleToggle('taskly_enable_gantt_dependencies', checked)}
                                            />
                                        </div>

                                        {/* Milestone Stage-Gate Approvals */}
                                        <div className="flex items-start justify-between p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/40">
                                            <div className="flex items-start gap-3">
                                                <div className="p-2 bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg">
                                                    <ShieldCheck className="h-5 w-5" />
                                                </div>
                                                <div>
                                                    <h4 className="font-semibold text-sm text-slate-900 dark:text-white">
                                                        {t('Stage-Gate Milestone Approvals')}
                                                    </h4>
                                                    <p className="text-xs text-muted-foreground mt-1 max-w-xl">
                                                        {t('Enforces formal sign-off workflow on major project milestones by project sponsors or external clients before phase closeout.')}
                                                    </p>
                                                </div>
                                            </div>
                                            <Switch
                                                checked={formData.taskly_enable_milestone_approvals}
                                                onCheckedChange={(checked) => handleToggle('taskly_enable_milestone_approvals', checked)}
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            {/* TAB 3: Maritime & Demurrage Policy */}
                            <TabsContent value="logistics" className="space-y-6">
                                <Card className="shadow-sm border-slate-200 dark:border-slate-800">
                                    <CardHeader>
                                        <CardTitle className="text-base flex items-center gap-2">
                                            <Ship className="h-4 w-4 text-blue-600" />
                                            {t('Demurrage & Maritime Supply Chain Baselines')}
                                        </CardTitle>
                                        <CardDescription>
                                            {t('Configure standard baseline values for port container free days and demurrage penalty rates for import/export operations.')}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-6">
                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                            <div className="space-y-2">
                                                <Label className="text-xs font-semibold">
                                                    {t('Default Port Free Days')}
                                                </Label>
                                                <div className="relative">
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        value={formData.taskly_demurrage_free_days}
                                                        onChange={(e) => handleChange('taskly_demurrage_free_days', e.target.value)}
                                                        className="pr-16"
                                                    />
                                                    <span className="absolute right-3 top-2.5 text-xs text-muted-foreground font-mono">
                                                        {t('Days')}
                                                    </span>
                                                </div>
                                                <p className="text-[11px] text-muted-foreground">
                                                    {t('Baseline standard port dwell free period before shipping lines commence demurrage penalties (typically 7, 14, or 21 days).')}
                                                </p>
                                            </div>

                                            <div className="space-y-2">
                                                <Label className="text-xs font-semibold">
                                                    {t('Default Daily Demurrage Rate')}
                                                </Label>
                                                <div className="relative">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={formData.taskly_demurrage_daily_rate}
                                                        onChange={(e) => handleChange('taskly_demurrage_daily_rate', e.target.value)}
                                                        className="pr-16"
                                                    />
                                                    <span className="absolute right-3 top-2.5 text-xs text-muted-foreground font-mono">
                                                        / {t('Day')}
                                                    </span>
                                                </div>
                                                <p className="text-[11px] text-muted-foreground">
                                                    {t('Default penalty fee charged per container per day past the free port dwell window.')}
                                                </p>
                                            </div>

                                            <div className="space-y-2">
                                                <Label className="text-xs font-semibold">
                                                    {t('Demurrage Warning Alert Days')}
                                                </Label>
                                                <div className="relative">
                                                    <Input
                                                        type="number"
                                                        min="1"
                                                        value={formData.taskly_demurrage_alert_days}
                                                        onChange={(e) => handleChange('taskly_demurrage_alert_days', e.target.value)}
                                                        className="pr-16"
                                                    />
                                                    <span className="absolute right-3 top-2.5 text-xs text-muted-foreground font-mono">
                                                        {t('Days Left')}
                                                    </span>
                                                </div>
                                                <p className="text-[11px] text-muted-foreground">
                                                    {t('Triggers amber risk warning badge on logistics cards when free days remaining fall below this threshold.')}
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            {/* TAB 4: Timesheets & Cost Governance */}
                            <TabsContent value="timesheets" className="space-y-6">
                                <Card className="shadow-sm border-slate-200 dark:border-slate-800">
                                    <CardHeader>
                                        <CardTitle className="text-base flex items-center gap-2">
                                            <Clock className="h-4 w-4 text-purple-600" />
                                            {t('Timesheet Policy & Cost Governance')}
                                        </CardTitle>
                                        <CardDescription>
                                            {t('Define how project labor hours are submitted, verified, and absorbed into actual cost calculations.')}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-6">
                                        <div className="flex items-start justify-between p-4 rounded-xl border border-slate-200 dark:border-slate-800">
                                            <div>
                                                <h4 className="font-semibold text-sm text-slate-900 dark:text-white">
                                                    {t('Require Manager Approval Before EVM Absorption')}
                                                </h4>
                                                <p className="text-xs text-muted-foreground mt-1 max-w-xl">
                                                    {t('When enabled, logged timesheets remain in "Pending Review" status until explicitly approved by the PM or Project Sponsor, preventing unverified hours from inflating Actual Cost.')}
                                                </p>
                                            </div>
                                            <Switch
                                                checked={formData.taskly_timesheet_approval_required}
                                                onCheckedChange={(checked) => handleToggle('taskly_timesheet_approval_required', checked)}
                                            />
                                        </div>

                                        <div className="flex items-start justify-between p-4 rounded-xl border border-slate-200 dark:border-slate-800">
                                            <div>
                                                <h4 className="font-semibold text-sm text-slate-900 dark:text-white">
                                                    {t('Enable Billable vs. Non-Billable Designation')}
                                                </h4>
                                                <p className="text-xs text-muted-foreground mt-1 max-w-xl">
                                                    {t('Allows team members to tag logged hours as billable to external clients or non-billable administrative effort.')}
                                                </p>
                                            </div>
                                            <Switch
                                                checked={formData.taskly_allow_billable_hours}
                                                onCheckedChange={(checked) => handleToggle('taskly_allow_billable_hours', checked)}
                                            />
                                        </div>

                                        <div className="max-w-xs space-y-2">
                                            <Label className="text-xs font-semibold">
                                                {t('Maximum Daily Logged Hours Limit')}
                                            </Label>
                                            <div className="relative">
                                                <Input
                                                    type="number"
                                                    min="1"
                                                    max="24"
                                                    value={formData.taskly_max_daily_hours}
                                                    onChange={(e) => handleChange('taskly_max_daily_hours', e.target.value)}
                                                    className="pr-16"
                                                />
                                                <span className="absolute right-3 top-2.5 text-xs text-muted-foreground font-mono">
                                                    {t('hrs / day')}
                                                </span>
                                            </div>
                                            <p className="text-[11px] text-muted-foreground">
                                                {t('Prevents inadvertent or duplicate timesheet entries exceeding this daily ceiling per user.')}
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            {/* TAB 5: Client Portal & Security */}
                            <TabsContent value="portal" className="space-y-6">
                                <Card className="shadow-sm border-slate-200 dark:border-slate-800">
                                    <CardHeader>
                                        <CardTitle className="text-base flex items-center gap-2">
                                            <ShieldCheck className="h-4 w-4 text-emerald-600" />
                                            {t('Client Portal & Magic Link Security')}
                                        </CardTitle>
                                        <CardDescription>
                                            {t('Set security and visibility policies for public tracking magic links shared with external clients.')}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-5">
                                        <div className="flex items-start justify-between p-4 rounded-xl border border-slate-200 dark:border-slate-800">
                                            <div>
                                                <h4 className="font-semibold text-sm text-slate-900 dark:text-white">
                                                    {t('Auto-Generate Public Magic Link on Project Creation')}
                                                </h4>
                                                <p className="text-xs text-muted-foreground mt-1 max-w-xl">
                                                    {t('Automatically generates a 32-character cryptographically secure token whenever a new client project is created.')}
                                                </p>
                                            </div>
                                            <Switch
                                                checked={formData.taskly_auto_generate_magic_link}
                                                onCheckedChange={(checked) => handleToggle('taskly_auto_generate_magic_link', checked)}
                                            />
                                        </div>

                                        <div className="flex items-start justify-between p-4 rounded-xl border border-slate-200 dark:border-slate-800">
                                            <div>
                                                <h4 className="font-semibold text-sm text-slate-900 dark:text-white">
                                                    {t('Allow Client Milestone Digital Approvals')}
                                                </h4>
                                                <p className="text-xs text-muted-foreground mt-1 max-w-xl">
                                                    {t('Allows external clients accessing the public tracking link to digitally approve milestone completions with their full name and notes.')}
                                                </p>
                                            </div>
                                            <Switch
                                                checked={formData.taskly_client_milestone_approval}
                                                onCheckedChange={(checked) => handleToggle('taskly_client_milestone_approval', checked)}
                                            />
                                        </div>

                                        <div className="flex items-start justify-between p-4 rounded-xl border border-slate-200 dark:border-slate-800">
                                            <div>
                                                <h4 className="font-semibold text-sm text-slate-900 dark:text-white">
                                                    {t('Mask Internal Financials & Profit Margins from Client Portal')}
                                                </h4>
                                                <p className="text-xs text-muted-foreground mt-1 max-w-xl">
                                                    {t('Protects commercial privacy by completely hiding internal cost breakdown, EVM calculations, and loaded labor rates from external portal viewers.')}
                                                </p>
                                            </div>
                                            <Switch
                                                checked={formData.taskly_client_hide_financials}
                                                onCheckedChange={(checked) => handleToggle('taskly_client_hide_financials', checked)}
                                            />
                                        </div>

                                        <div className="flex items-start justify-between p-4 rounded-xl border border-slate-200 dark:border-slate-800">
                                            <div>
                                                <h4 className="font-semibold text-sm text-slate-900 dark:text-white">
                                                    {t('Show Subtasks & Checklists on Client Portal')}
                                                </h4>
                                                <p className="text-xs text-muted-foreground mt-1 max-w-xl">
                                                    {t('Displays granular checklist progress and completed subtasks alongside milestone deliverables.')}
                                                </p>
                                            </div>
                                            <Switch
                                                checked={formData.taskly_client_show_subtasks}
                                                onCheckedChange={(checked) => handleToggle('taskly_client_show_subtasks', checked)}
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            {/* TAB 6: EVM Risk Thresholds */}
                            <TabsContent value="evm" className="space-y-6">
                                <Card className="shadow-sm border-slate-200 dark:border-slate-800">
                                    <CardHeader>
                                        <CardTitle className="text-base flex items-center gap-2">
                                            <AlertTriangle className="h-4 w-4 text-amber-500" />
                                            {t('EVM Performance & Variance Alert Thresholds')}
                                        </CardTitle>
                                        <CardDescription>
                                            {t('Set warning trigger points for Cost Performance Index (CPI) and Schedule Performance Index (SPI) across company dashboards.')}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-6">
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 space-y-3">
                                                <div className="flex items-center gap-2">
                                                    <DollarSign className="h-4 w-4 text-amber-600" />
                                                    <Label className="text-xs font-semibold">
                                                        {t('Cost Performance Index (CPI) Warning Alert Level')}
                                                    </Label>
                                                </div>
                                                <Input
                                                    type="number"
                                                    step="0.05"
                                                    min="0.1"
                                                    max="1.5"
                                                    value={formData.taskly_cpi_warning_threshold}
                                                    onChange={(e) => handleChange('taskly_cpi_warning_threshold', e.target.value)}
                                                />
                                                <p className="text-[11px] text-muted-foreground leading-relaxed">
                                                    {t('A CPI below 1.0 indicates cost overrun. Setting this to 0.90 flags projects that exceed expected costs by more than 10%.')}
                                                </p>
                                            </div>

                                            <div className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 space-y-3">
                                                <div className="flex items-center gap-2">
                                                    <Clock className="h-4 w-4 text-blue-600" />
                                                    <Label className="text-xs font-semibold">
                                                        {t('Schedule Performance Index (SPI) Warning Alert Level')}
                                                    </Label>
                                                </div>
                                                <Input
                                                    type="number"
                                                    step="0.05"
                                                    min="0.1"
                                                    max="1.5"
                                                    value={formData.taskly_spi_warning_threshold}
                                                    onChange={(e) => handleChange('taskly_spi_warning_threshold', e.target.value)}
                                                />
                                                <p className="text-[11px] text-muted-foreground leading-relaxed">
                                                    {t('An SPI below 1.0 indicates schedule slippage. Setting this to 0.90 flags projects progressing 10% slower than planned.')}
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>

                        {/* Bottom Actions Bar */}
                        <div className="flex items-center justify-end gap-3 pt-4 border-t mt-6">
                            <Button
                                type="submit"
                                disabled={isSaving}
                                size="lg"
                                className="shadow-md flex items-center gap-2 px-6"
                            >
                                <Save className="h-4 w-4" />
                                {isSaving ? t('Saving Settings...') : t('Save Preferences')}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
