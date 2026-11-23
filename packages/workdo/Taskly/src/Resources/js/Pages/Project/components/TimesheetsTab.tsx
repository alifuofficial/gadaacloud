import React, { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Clock, CheckCircle2, Trash2, Plus, Calendar, DollarSign, UserCheck } from 'lucide-react';
import { formatCurrency, formatDate } from '@/utils/helpers';

interface TimesheetItem {
    id: number;
    project_id: number;
    task_id?: number;
    task?: { id: number; title: string };
    user_id: number;
    user: { id: number; name: string; avatar?: string };
    date: string;
    hours: number;
    is_billable: boolean;
    hourly_rate: number;
    note?: string;
    status: 'draft' | 'submitted' | 'approved' | 'invoiced';
}

interface TimesheetsTabProps {
    projectId: number;
    timesheets: TimesheetItem[];
    tasks: Array<{ id: number; title: string }>;
    currency: string;
    canApprove: boolean;
}

export function TimesheetsTab({ projectId, timesheets, tasks, currency, canApprove }: TimesheetsTabProps) {
    const [isLoggingOpen, setIsLoggingOpen] = useState(false);

    const { data, setData, post, processing, reset, errors } = useForm({
        task_id: '',
        date: new Date().toISOString().split('T')[0],
        hours: 1,
        is_billable: true,
        hourly_rate: 0,
        note: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('project.timesheets.store', projectId), {
            onSuccess: () => {
                reset();
                setIsLoggingOpen(false);
            }
        });
    };

    const handleApprove = (timesheetId: number) => {
        post(route('project.timesheets.approve', timesheetId));
    };

    const handleDelete = (timesheetId: number) => {
        if (confirm('Are you sure you want to delete this timesheet entry?')) {
            router.delete(route('project.timesheets.destroy', timesheetId));
        }
    };

    const totalHours = timesheets.reduce((acc, t) => acc + Number(t.hours), 0);
    const totalLaborCost = timesheets.reduce((acc, t) => acc + (Number(t.hours) * Number(t.hourly_rate)), 0);
    const approvedHours = timesheets.filter(t => t.status === 'approved').reduce((acc, t) => acc + Number(t.hours), 0);

    return (
        <div className="space-y-6">
            {/* Quick Metrics & Log Time Button */}
            <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <Card className="border shadow-none bg-slate-50 dark:bg-slate-900/50">
                    <CardContent className="p-4 space-y-1">
                        <span className="text-xs text-slate-500 font-medium">Total Hours Logged</span>
                        <div className="text-xl font-bold font-mono text-slate-900 dark:text-white flex items-center gap-1.5">
                            <Clock className="w-4 h-4 text-blue-600" />
                            {totalHours.toFixed(1)} hrs
                        </div>
                    </CardContent>
                </Card>

                <Card className="border shadow-none bg-slate-50 dark:bg-slate-900/50">
                    <CardContent className="p-4 space-y-1">
                        <span className="text-xs text-slate-500 font-medium">Approved Hours</span>
                        <div className="text-xl font-bold font-mono text-emerald-600 flex items-center gap-1.5">
                            <CheckCircle2 className="w-4 h-4" />
                            {approvedHours.toFixed(1)} hrs
                        </div>
                    </CardContent>
                </Card>

                <Card className="border shadow-none bg-slate-50 dark:bg-slate-900/50">
                    <CardContent className="p-4 space-y-1">
                        <span className="text-xs text-slate-500 font-medium">Total Labor Cost</span>
                        <div className="text-xl font-bold font-mono text-slate-900 dark:text-white flex items-center gap-1.5">
                            <DollarSign className="w-4 h-4 text-emerald-600" />
                            {formatCurrency(totalLaborCost)}
                        </div>
                    </CardContent>
                </Card>

                <div className="flex items-center justify-end">
                    <Button
                        onClick={() => setIsLoggingOpen(!isLoggingOpen)}
                        className="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5 h-12"
                    >
                        <Plus className="w-4 h-4" /> Log Project Hours
                    </Button>
                </div>
            </div>

            {/* Quick Log Form */}
            {isLoggingOpen && (
                <Card className="border border-emerald-500/30 shadow-md bg-emerald-50/20 dark:bg-emerald-950/20 animate-in fade-in">
                    <CardHeader className="p-4 pb-2">
                        <CardTitle className="text-sm font-bold flex items-center gap-2 text-emerald-900 dark:text-emerald-200">
                            <Clock className="w-4 h-4 text-emerald-600" /> Log Time / Timesheet Entry
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-4 pt-2">
                        <form onSubmit={handleSubmit} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
                            <div>
                                <Label className="text-xs">Date</Label>
                                <Input
                                    type="date"
                                    value={data.date}
                                    onChange={(e) => setData('date', e.target.value)}
                                    required
                                    className="h-9 text-xs"
                                />
                            </div>

                            <div>
                                <Label className="text-xs">Task (Optional)</Label>
                                <Select
                                    value={data.task_id}
                                    onValueChange={(val) => setData('task_id', val)}
                                >
                                    <SelectTrigger className="h-9 text-xs w-full">
                                        <SelectValue placeholder="General Project Work" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">General Project Work</SelectItem>
                                        {tasks.map((task) => (
                                            <SelectItem key={task.id} value={task.id.toString()}>
                                                {task.title}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <Label className="text-xs">Hours</Label>
                                <Input
                                    type="number"
                                    step="0.25"
                                    min="0.1"
                                    max="24"
                                    value={data.hours}
                                    onChange={(e) => setData('hours', parseFloat(e.target.value) || 0)}
                                    required
                                    className="h-9 text-xs"
                                />
                            </div>

                            <div>
                                <Label className="text-xs">Hourly Rate ({currency})</Label>
                                <Input
                                    type="number"
                                    step="1"
                                    min="0"
                                    value={data.hourly_rate}
                                    onChange={(e) => setData('hourly_rate', parseFloat(e.target.value) || 0)}
                                    className="h-9 text-xs"
                                />
                            </div>

                            <div className="lg:col-span-2">
                                <Label className="text-xs">Work Note</Label>
                                <Input
                                    value={data.note}
                                    onChange={(e) => setData('note', e.target.value)}
                                    placeholder="Summary of work delivered..."
                                    className="h-9 text-xs"
                                />
                            </div>

                            <div className="flex items-center gap-2 pt-2 sm:col-span-2 lg:col-span-6 justify-between border-t border-emerald-500/20">
                                <div className="flex items-center gap-2">
                                    <Switch
                                        checked={data.is_billable}
                                        onCheckedChange={(val) => setData('is_billable', val)}
                                        id="billable-toggle"
                                    />
                                    <Label htmlFor="billable-toggle" className="text-xs font-medium cursor-pointer">
                                        Billable to Client
                                    </Label>
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setIsLoggingOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={processing}
                                        className="bg-emerald-600 hover:bg-emerald-700 text-white"
                                    >
                                        {processing ? 'Saving...' : 'Save Timesheet'}
                                    </Button>
                                </div>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            )}

            {/* Timesheets History Table */}
            <Card className="border shadow-sm">
                <CardHeader className="p-4 border-b bg-slate-50/50 dark:bg-slate-900/50">
                    <CardTitle className="text-base font-bold">Logged Timesheets</CardTitle>
                </CardHeader>
                <CardContent className="p-0">
                    {timesheets.length === 0 ? (
                        <div className="p-8 text-center text-sm text-slate-500">
                            No timesheets logged for this project yet. Use the "Log Project Hours" button above.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs text-left">
                                <thead className="bg-slate-50 dark:bg-slate-800/60 border-b text-slate-500 font-semibold uppercase text-[10px] tracking-wider">
                                    <tr>
                                        <th className="py-3 px-4">Date</th>
                                        <th className="py-3 px-4">Member</th>
                                        <th className="py-3 px-4">Task</th>
                                        <th className="py-3 px-4">Hours</th>
                                        <th className="py-3 px-4">Rate</th>
                                        <th className="py-3 px-4">Cost</th>
                                        <th className="py-3 px-4">Status</th>
                                        <th className="py-3 px-4">Note</th>
                                        <th className="py-3 px-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {timesheets.map((ts) => (
                                        <tr key={ts.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                            <td className="py-3 px-4 font-mono text-slate-600 dark:text-slate-300">
                                                {formatDate(ts.date)}
                                            </td>
                                            <td className="py-3 px-4 font-medium text-slate-900 dark:text-white">
                                                {ts.user?.name || 'Unknown'}
                                            </td>
                                            <td className="py-3 px-4 text-slate-600 dark:text-slate-300">
                                                {ts.task?.title || <span className="text-slate-400">General</span>}
                                            </td>
                                            <td className="py-3 px-4 font-mono font-bold text-slate-900 dark:text-white">
                                                {Number(ts.hours).toFixed(2)} hrs
                                            </td>
                                            <td className="py-3 px-4 font-mono text-slate-500">
                                                {formatCurrency(ts.hourly_rate)}
                                            </td>
                                            <td className="py-3 px-4 font-mono font-semibold text-emerald-600">
                                                {formatCurrency(Number(ts.hours) * Number(ts.hourly_rate))}
                                            </td>
                                            <td className="py-3 px-4">
                                                <Badge
                                                    variant="outline"
                                                    className={`text-[10px] capitalize ${
                                                        ts.status === 'approved'
                                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-300'
                                                            : 'bg-amber-50 text-amber-700 border-amber-300'
                                                    }`}
                                                >
                                                    {ts.status}
                                                </Badge>
                                            </td>
                                            <td className="py-3 px-4 text-slate-500 truncate max-w-[180px]">
                                                {ts.note || '-'}
                                            </td>
                                            <td className="py-3 px-4 text-right space-x-1">
                                                {ts.status !== 'approved' && canApprove && (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => handleApprove(ts.id)}
                                                        className="h-7 px-2 text-emerald-600 hover:text-emerald-700 text-xs gap-1"
                                                    >
                                                        <UserCheck className="w-3.5 h-3.5" /> Approve
                                                    </Button>
                                                )}
                                                <Button
                                                    size="icon"
                                                    variant="ghost"
                                                    onClick={() => handleDelete(ts.id)}
                                                    className="h-7 w-7 text-red-500 hover:text-red-700"
                                                >
                                                    <Trash2 className="w-3.5 h-3.5" />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

export default TimesheetsTab;
