import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Ship, Clock, AlertTriangle, CheckCircle2, ShieldCheck, Box, Anchor, Navigation } from 'lucide-react';
import { formatCurrency, formatDate } from '@/utils/helpers';

interface LogisticsItem {
    id: number;
    lc_number?: string;
    nbe_permit_no?: string;
    bl_number?: string;
    container_count: number;
    container_numbers?: string;
    port_of_loading?: string;
    discharge_port: string;
    dry_port: string;
    current_stage: 'forex_approved' | 'lc_opened' | 'ocean_transit' | 'djibouti_discharge' | 'modjo_customs' | 'delivered';
    port_free_days: number;
    discharge_date?: string;
    demurrage_start_date?: string;
    demurrage_daily_cost: number;
    demurrage_days?: number;
    total_demurrage_risk?: number;
}

interface LogisticsTabProps {
    projectId: number;
    logistics?: LogisticsItem;
    currency: string;
}

const STAGES = [
    { key: 'forex_approved', label: '1. NBE Forex Approved' },
    { key: 'lc_opened', label: '2. LC Opened' },
    { key: 'ocean_transit', label: '3. Ocean Transit' },
    { key: 'djibouti_discharge', label: '4. Djibouti Discharge' },
    { key: 'modjo_customs', label: '5. Modjo Customs' },
    { key: 'delivered', label: '6. Warehouse Arrival' },
];

export function LogisticsWorkspaceTab({ projectId, logistics, currency }: LogisticsTabProps) {
    const [isEditing, setIsEditing] = useState(!logistics);

    const { data, setData, post, processing } = useForm({
        lc_number: logistics?.lc_number || '',
        nbe_permit_no: logistics?.nbe_permit_no || '',
        bl_number: logistics?.bl_number || '',
        container_count: logistics?.container_count || 1,
        container_numbers: logistics?.container_numbers || '',
        port_of_loading: logistics?.port_of_loading || '',
        discharge_port: logistics?.discharge_port || 'Djibouti Port',
        dry_port: logistics?.dry_port || 'Modjo Dry Port',
        current_stage: logistics?.current_stage || 'forex_approved',
        port_free_days: logistics?.port_free_days || 14,
        discharge_date: logistics?.discharge_date || '',
        demurrage_daily_cost: logistics?.demurrage_daily_cost || 0,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('project.logistics.update', projectId), {
            onSuccess: () => {
                setIsEditing(false);
            }
        });
    };

    const currentStageIdx = STAGES.findIndex(s => s.key === (logistics?.current_stage || data.current_stage));
    const demurrageDays = logistics?.demurrage_days || 0;
    const demurrageRisk = logistics?.total_demurrage_risk || 0;

    return (
        <div className="space-y-6">
            {/* Visual Logistics Stepper */}
            <Card className="border shadow-sm">
                <CardHeader className="p-5 border-b bg-slate-50/50 dark:bg-slate-900/50 flex flex-row items-center justify-between">
                    <div>
                        <CardTitle className="text-base font-bold flex items-center gap-2">
                            <Ship className="w-5 h-5 text-emerald-600" />
                            Import / Export Shipment &amp; Port Clearance Pipeline
                        </CardTitle>
                        <CardDescription className="text-xs text-slate-500">
                            Trade finance, Bill of Lading, transit stages, and customs dry-port clearance
                        </CardDescription>
                    </div>
                    <Button
                        size="sm"
                        variant={isEditing ? 'outline' : 'default'}
                        onClick={() => setIsEditing(!isEditing)}
                        className={`text-xs ${!isEditing ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : ''}`}
                    >
                        {isEditing ? 'Cancel Edit' : 'Edit Logistics Details'}
                    </Button>
                </CardHeader>
                <CardContent className="p-6">
                    {/* Stepper Progress */}
                    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                        {STAGES.map((stage, idx) => {
                            const isPast = idx < currentStageIdx;
                            const isCurrent = idx === currentStageIdx;
                            return (
                                <div
                                    key={stage.key}
                                    className={`p-3 rounded-xl border text-center transition-all ${
                                        isCurrent
                                            ? 'bg-emerald-50 dark:bg-emerald-950/60 border-emerald-500 text-emerald-800 dark:text-emerald-200 ring-2 ring-emerald-500/20'
                                            : isPast
                                            ? 'bg-slate-50 dark:bg-slate-800/40 border-slate-200 text-slate-600'
                                            : 'bg-white dark:bg-slate-900/40 border-slate-100 text-slate-400 opacity-60'
                                    }`}
                                >
                                    <div className="flex justify-center mb-1">
                                        {isPast ? (
                                            <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                                        ) : isCurrent ? (
                                            <Navigation className="w-4 h-4 text-emerald-600 animate-pulse" />
                                        ) : (
                                            <span className="w-4 h-4 rounded-full border border-slate-300 dark:border-slate-700 inline-block text-[10px] font-mono">
                                                {idx + 1}
                                            </span>
                                        )}
                                    </div>
                                    <span className="text-xs font-semibold block">{stage.label}</span>
                                </div>
                            );
                        })}
                    </div>
                </CardContent>
            </Card>

            {/* Demurrage Clock & Trade Finance Overview */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <Card className={`border shadow-none ${demurrageDays > 0 ? 'bg-red-50/50 border-red-200' : 'bg-slate-50 dark:bg-slate-900/50'}`}>
                    <CardContent className="p-4 space-y-1">
                        <span className="text-xs text-slate-500 font-medium block">Port Free Days Allowed</span>
                        <div className="text-xl font-bold font-mono text-slate-900 dark:text-white">
                            {logistics?.port_free_days || data.port_free_days} Days
                        </div>
                        <span className="text-[11px] text-slate-400">Standard carrier allowance</span>
                    </CardContent>
                </Card>

                <Card className={`border shadow-none ${demurrageDays > 0 ? 'bg-red-50/50 border-red-200' : 'bg-slate-50 dark:bg-slate-900/50'}`}>
                    <CardContent className="p-4 space-y-1">
                        <span className="text-xs text-slate-500 font-medium block">Demurrage Days Incurred</span>
                        <div className={`text-xl font-bold font-mono flex items-center gap-1.5 ${demurrageDays > 0 ? 'text-red-600' : 'text-emerald-600'}`}>
                            {demurrageDays > 0 ? <AlertTriangle className="w-4 h-4" /> : <ShieldCheck className="w-4 h-4" />}
                            {demurrageDays} Days
                        </div>
                        <span className="text-[11px] text-slate-400">
                            {demurrageDays > 0 ? 'Demurrage clock running!' : 'Within free window'}
                        </span>
                    </CardContent>
                </Card>

                <Card className={`border shadow-none ${demurrageRisk > 0 ? 'bg-red-50/50 border-red-200' : 'bg-slate-50 dark:bg-slate-900/50'}`}>
                    <CardContent className="p-4 space-y-1">
                        <span className="text-xs text-slate-500 font-medium block">Accumulated Demurrage Risk</span>
                        <div className={`text-xl font-bold font-mono ${demurrageRisk > 0 ? 'text-red-600' : 'text-emerald-600'}`}>
                            {formatCurrency(demurrageRisk)}
                        </div>
                        <span className="text-[11px] text-slate-400 font-mono">
                            Rate: {formatCurrency(logistics?.demurrage_daily_cost || data.demurrage_daily_cost)} / container / day
                        </span>
                    </CardContent>
                </Card>
            </div>

            {/* Editable Form or Overview */}
            {isEditing ? (
                <Card className="border border-emerald-500/30 shadow-md">
                    <CardHeader className="p-4 pb-2">
                        <CardTitle className="text-sm font-bold">Update Logistics &amp; Shipping Data</CardTitle>
                    </CardHeader>
                    <CardContent className="p-4 pt-2">
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <Label className="text-xs">Letter of Credit (LC) Number</Label>
                                    <Input
                                        value={data.lc_number}
                                        onChange={(e) => setData('lc_number', e.target.value)}
                                        placeholder="e.g. LC-2026-NBE-9912"
                                        className="h-9 text-xs"
                                    />
                                </div>
                                <div>
                                    <Label className="text-xs">NBE Forex Approval Ref</Label>
                                    <Input
                                        value={data.nbe_permit_no}
                                        onChange={(e) => setData('nbe_permit_no', e.target.value)}
                                        placeholder="e.g. FX/2026/04481"
                                        className="h-9 text-xs"
                                    />
                                </div>
                                <div>
                                    <Label className="text-xs">Bill of Lading (BL) Number</Label>
                                    <Input
                                        value={data.bl_number}
                                        onChange={(e) => setData('bl_number', e.target.value)}
                                        placeholder="e.g. MAEU982138129"
                                        className="h-9 text-xs"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                <div>
                                    <Label className="text-xs">Container Count</Label>
                                    <Input
                                        type="number"
                                        min="1"
                                        value={data.container_count}
                                        onChange={(e) => setData('container_count', parseInt(e.target.value) || 1)}
                                        className="h-9 text-xs"
                                    />
                                </div>
                                <div>
                                    <Label className="text-xs">Port of Loading</Label>
                                    <Input
                                        value={data.port_of_loading}
                                        onChange={(e) => setData('port_of_loading', e.target.value)}
                                        placeholder="e.g. Shanghai / Ningbo"
                                        className="h-9 text-xs"
                                    />
                                </div>
                                <div>
                                    <Label className="text-xs">Discharge Port</Label>
                                    <Input
                                        value={data.discharge_port}
                                        onChange={(e) => setData('discharge_port', e.target.value)}
                                        placeholder="Djibouti Port"
                                        className="h-9 text-xs"
                                    />
                                </div>
                                <div>
                                    <Label className="text-xs">Inland Dry Port</Label>
                                    <Input
                                        value={data.dry_port}
                                        onChange={(e) => setData('dry_port', e.target.value)}
                                        placeholder="Modjo Dry Port"
                                        className="h-9 text-xs"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                <div>
                                    <Label className="text-xs">Current Logistics Stage</Label>
                                    <Select
                                        value={data.current_stage}
                                        onValueChange={(val: any) => setData('current_stage', val)}
                                    >
                                        <SelectTrigger className="h-9 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {STAGES.map(s => (
                                                <SelectItem key={s.key} value={s.key}>{s.label}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <Label className="text-xs">Port Discharge Date</Label>
                                    <Input
                                        type="date"
                                        value={data.discharge_date}
                                        onChange={(e) => setData('discharge_date', e.target.value)}
                                        className="h-9 text-xs"
                                    />
                                </div>
                                <div>
                                    <Label className="text-xs">Free Days Allowed</Label>
                                    <Input
                                        type="number"
                                        min="0"
                                        value={data.port_free_days}
                                        onChange={(e) => setData('port_free_days', parseInt(e.target.value) || 0)}
                                        className="h-9 text-xs"
                                    />
                                </div>
                                <div>
                                    <Label className="text-xs">Daily Demurrage Rate ({currency})</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.demurrage_daily_cost}
                                        onChange={(e) => setData('demurrage_daily_cost', parseFloat(e.target.value) || 0)}
                                        className="h-9 text-xs"
                                    />
                                </div>
                            </div>

                            <div>
                                <Label className="text-xs">Container Identifiers</Label>
                                <Input
                                    value={data.container_numbers}
                                    onChange={(e) => setData('container_numbers', e.target.value)}
                                    placeholder="e.g. MSKU9812931, TGHU4910291"
                                    className="h-9 text-xs"
                                />
                            </div>

                            <div className="flex justify-end gap-2 pt-2">
                                <Button type="button" variant="outline" size="sm" onClick={() => setIsEditing(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" size="sm" disabled={processing} className="bg-emerald-600 hover:bg-emerald-700 text-white">
                                    {processing ? 'Saving...' : 'Save Logistics Record'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            ) : (
                <Card className="border shadow-sm">
                    <CardHeader className="p-4 border-b">
                        <CardTitle className="text-base font-bold">Active Shipment Credentials</CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                            <div>
                                <span className="text-slate-400 block font-mono">Letter of Credit (LC):</span>
                                <span className="font-bold text-slate-800 dark:text-white">{logistics?.lc_number || '-'}</span>
                            </div>
                            <div>
                                <span className="text-slate-400 block font-mono">NBE Permit:</span>
                                <span className="font-bold text-slate-800 dark:text-white">{logistics?.nbe_permit_no || '-'}</span>
                            </div>
                            <div>
                                <span className="text-slate-400 block font-mono">Bill of Lading:</span>
                                <span className="font-bold text-slate-800 dark:text-white">{logistics?.bl_number || '-'}</span>
                            </div>
                            <div>
                                <span className="text-slate-400 block font-mono">Total Containers:</span>
                                <span className="font-bold text-slate-800 dark:text-white">{logistics?.container_count || 1} Units</span>
                            </div>
                            <div>
                                <span className="text-slate-400 block font-mono">Port of Loading:</span>
                                <span className="font-medium">{logistics?.port_of_loading || '-'}</span>
                            </div>
                            <div>
                                <span className="text-slate-400 block font-mono">Discharge Port:</span>
                                <span className="font-medium">{logistics?.discharge_port || 'Djibouti'}</span>
                            </div>
                            <div>
                                <span className="text-slate-400 block font-mono">Inland Dry Port:</span>
                                <span className="font-medium">{logistics?.dry_port || 'Modjo'}</span>
                            </div>
                            <div>
                                <span className="text-slate-400 block font-mono">Discharge Date:</span>
                                <span className="font-medium font-mono">{logistics?.discharge_date ? formatDate(logistics.discharge_date) : '-'}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

export default LogisticsWorkspaceTab;
