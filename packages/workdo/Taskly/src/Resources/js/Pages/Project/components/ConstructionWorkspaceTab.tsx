import React, { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RichTextEditor } from "@/components/ui/rich-text-editor";
import { RichTextViewer } from "@/components/ui/rich-text-viewer";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { HardHat, Plus, Trash2, Edit, Calendar, Sun, Users, Wrench, FileSpreadsheet, CheckCircle2 } from 'lucide-react';
import { formatCurrency, formatDate, isRichTextEmpty } from '@/utils/helpers';

interface BoqItem {
    id: number;
    item_no: string;
    description: string;
    unit: string;
    quantity: number;
    rate: number;
    amount: number;
    actual_quantity: number;
    actual_amount: number;
    status: 'pending' | 'in_progress' | 'completed';
}

interface SiteDiaryItem {
    id: number;
    date: string;
    weather: string;
    workforce_count: number;
    machinery_hours: number;
    work_completed: string;
    challenges?: string;
    logged_by: { id: number; name: string };
}

interface ConstructionTabProps {
    projectId: number;
    boqs: BoqItem[];
    siteDiaries: SiteDiaryItem[];
    currency: string;
}

export function ConstructionWorkspaceTab({ projectId, boqs, siteDiaries, currency }: ConstructionTabProps) {
    const [activeSection, setActiveSection] = useState<'boq' | 'diary'>('boq');
    const [isAddBoqOpen, setIsAddBoqOpen] = useState(false);
    const [isAddDiaryOpen, setIsAddDiaryOpen] = useState(false);
    const [editingBoq, setEditingBoq] = useState<BoqItem | null>(null);

    // BoQ Form
    const boqForm = useForm({
        item_no: '',
        description: '',
        unit: 'm3',
        quantity: 1,
        rate: 0,
    });

    // Update BoQ Form
    const editBoqForm = useForm({
        actual_quantity: 0,
        status: 'pending',
    });

    // Site Diary Form
    const diaryForm = useForm({
        date: new Date().toISOString().split('T')[0],
        weather: 'Sunny & Clear',
        workforce_count: 10,
        machinery_hours: 8,
        work_completed: '',
        challenges: '',
    });

    const handleCreateBoq = (e: React.FormEvent) => {
        e.preventDefault();
        boqForm.post(route('project.boqs.store', projectId), {
            onSuccess: () => {
                boqForm.reset();
                setIsAddBoqOpen(false);
            }
        });
    };

    const handleUpdateBoq = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingBoq) return;
        editBoqForm.put(route('project.boqs.update', editingBoq.id), {
            onSuccess: () => {
                setEditingBoq(null);
            }
        });
    };

    const handleDeleteBoq = (id: number) => {
        if (confirm('Delete this BoQ line item?')) {
            router.delete(route('project.boqs.destroy', id));
        }
    };

    const handleCreateDiary = (e: React.FormEvent) => {
        e.preventDefault();
        if (isRichTextEmpty(diaryForm.data.work_completed)) return;
        diaryForm.post(route('project.site-diaries.store', projectId), {
            onSuccess: () => {
                diaryForm.reset();
                setIsAddDiaryOpen(false);
            }
        });
    };

    const handleDeleteDiary = (id: number) => {
        if (confirm('Delete this site diary entry?')) {
            router.delete(route('project.site-diaries.destroy', id));
        }
    };

    const totalPlannedBoq = boqs.reduce((acc, b) => acc + Number(b.amount), 0);
    const totalActualBoq = boqs.reduce((acc, b) => acc + Number(b.actual_amount), 0);

    return (
        <div className="space-y-6">
            {/* Section Switcher */}
            <div className="flex items-center justify-between border-b pb-3">
                <div className="flex items-center gap-2">
                    <Button
                        size="sm"
                        variant={activeSection === 'boq' ? 'default' : 'outline'}
                        onClick={() => setActiveSection('boq')}
                        className={`text-xs gap-1.5 ${activeSection === 'boq' ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : ''}`}
                    >
                        <FileSpreadsheet className="w-3.5 h-3.5" />
                        Bill of Quantities (BoQ)
                    </Button>
                    <Button
                        size="sm"
                        variant={activeSection === 'diary' ? 'default' : 'outline'}
                        onClick={() => setActiveSection('diary')}
                        className={`text-xs gap-1.5 ${activeSection === 'diary' ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : ''}`}
                    >
                        <HardHat className="w-3.5 h-3.5" />
                        Daily Site Diary Logs
                    </Button>
                </div>

                <div>
                    {activeSection === 'boq' ? (
                        <Button
                            size="sm"
                            onClick={() => setIsAddBoqOpen(!isAddBoqOpen)}
                            className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1"
                        >
                            <Plus className="w-3.5 h-3.5" /> Add BoQ Line Item
                        </Button>
                    ) : (
                        <Button
                            size="sm"
                            onClick={() => setIsAddDiaryOpen(!isAddDiaryOpen)}
                            className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1"
                        >
                            <Plus className="w-3.5 h-3.5" /> Log Daily Site Diary
                        </Button>
                    )}
                </div>
            </div>

            {/* SECTION 1: Bill of Quantities */}
            {activeSection === 'boq' && (
                <div className="space-y-4">
                    {/* Add BoQ Item Form */}
                    {isAddBoqOpen && (
                        <Card className="border border-emerald-500/30 shadow-md bg-emerald-50/10 dark:bg-emerald-950/20">
                            <CardHeader className="p-4 pb-2">
                                <CardTitle className="text-sm font-bold">New Bill of Quantities (BoQ) Item</CardTitle>
                            </CardHeader>
                            <CardContent className="p-4 pt-2">
                                <form onSubmit={handleCreateBoq} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                                    <div>
                                        <Label className="text-xs">Item No.</Label>
                                        <Input
                                            value={boqForm.data.item_no}
                                            onChange={(e) => boqForm.setData('item_no', e.target.value)}
                                            placeholder="e.g. 2.1.A"
                                            required
                                            className="h-9 text-xs"
                                        />
                                    </div>
                                    <div className="sm:col-span-2">
                                        <Label className="text-xs">Work / Material Description</Label>
                                        <Input
                                            value={boqForm.data.description}
                                            onChange={(e) => boqForm.setData('description', e.target.value)}
                                            placeholder="e.g. Reinforced Concrete C-25 for foundation"
                                            required
                                            className="h-9 text-xs"
                                        />
                                    </div>
                                    <div>
                                        <Label className="text-xs">Unit</Label>
                                        <Input
                                            value={boqForm.data.unit}
                                            onChange={(e) => boqForm.setData('unit', e.target.value)}
                                            placeholder="m3, ton, m2, pcs"
                                            required
                                            className="h-9 text-xs"
                                        />
                                    </div>
                                    <div>
                                        <Label className="text-xs">Planned Qty</Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            value={boqForm.data.quantity}
                                            onChange={(e) => boqForm.setData('quantity', parseFloat(e.target.value) || 0)}
                                            required
                                            className="h-9 text-xs"
                                        />
                                    </div>
                                    <div>
                                        <Label className="text-xs">Unit Rate ({currency})</Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            value={boqForm.data.rate}
                                            onChange={(e) => boqForm.setData('rate', parseFloat(e.target.value) || 0)}
                                            required
                                            className="h-9 text-xs"
                                        />
                                    </div>
                                    <div className="flex gap-2 sm:col-span-2 lg:col-span-4 justify-end pt-2">
                                        <Button type="button" variant="outline" size="sm" onClick={() => setIsAddBoqOpen(false)}>
                                            Cancel
                                        </Button>
                                        <Button type="submit" size="sm" disabled={boqForm.processing} className="bg-emerald-600 hover:bg-emerald-700 text-white">
                                            {boqForm.processing ? 'Saving...' : 'Add Line Item'}
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    )}

                    {/* BoQ Table */}
                    <Card className="border shadow-sm">
                        <CardHeader className="p-4 border-b flex flex-row items-center justify-between">
                            <CardTitle className="text-base font-bold">Project BoQ Schedule</CardTitle>
                            <div className="text-xs text-slate-500 font-mono">
                                Total Budget: <strong>{formatCurrency(totalPlannedBoq)}</strong> | Actual: <strong>{formatCurrency(totalActualBoq)}</strong>
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            {boqs.length === 0 ? (
                                <div className="p-8 text-center text-sm text-slate-500">
                                    No BoQ line items defined. Click "Add BoQ Line Item" to structure construction costs.
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-xs text-left">
                                        <thead className="bg-slate-50 dark:bg-slate-800/60 border-b text-slate-500 uppercase text-[10px]">
                                            <tr>
                                                <th className="py-3 px-4">Item #</th>
                                                <th className="py-3 px-4">Description</th>
                                                <th className="py-3 px-4">Unit</th>
                                                <th className="py-3 px-4">Planned Qty</th>
                                                <th className="py-3 px-4">Rate</th>
                                                <th className="py-3 px-4">Planned Total</th>
                                                <th className="py-3 px-4">Actual Qty</th>
                                                <th className="py-3 px-4">Actual Total</th>
                                                <th className="py-3 px-4">Status</th>
                                                <th className="py-3 px-4 text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                            {boqs.map((b) => (
                                                <tr key={b.id} className="hover:bg-slate-50/80">
                                                    <td className="py-3 px-4 font-mono font-bold text-slate-900 dark:text-white">
                                                        {b.item_no}
                                                    </td>
                                                    <td className="py-3 px-4 text-slate-700 dark:text-slate-300 font-medium">
                                                        {b.description}
                                                    </td>
                                                    <td className="py-3 px-4 text-slate-500 font-mono">
                                                        {b.unit}
                                                    </td>
                                                    <td className="py-3 px-4 font-mono">
                                                        {Number(b.quantity).toFixed(2)}
                                                    </td>
                                                    <td className="py-3 px-4 font-mono">
                                                        {formatCurrency(b.rate)}
                                                    </td>
                                                    <td className="py-3 px-4 font-mono font-bold">
                                                        {formatCurrency(b.amount)}
                                                    </td>
                                                    <td className="py-3 px-4 font-mono text-emerald-600 font-semibold">
                                                        {Number(b.actual_quantity).toFixed(2)}
                                                    </td>
                                                    <td className="py-3 px-4 font-mono text-emerald-600 font-semibold">
                                                        {formatCurrency(b.actual_amount)}
                                                    </td>
                                                    <td className="py-3 px-4">
                                                        <Badge variant="outline" className="capitalize text-[10px]">
                                                            {b.status.replace('_', ' ')}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-3 px-4 text-right space-x-1">
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => {
                                                                setEditingBoq(b);
                                                                editBoqForm.setData({
                                                                    actual_quantity: b.actual_quantity,
                                                                    status: b.status,
                                                                });
                                                            }}
                                                            className="h-7 px-2 text-blue-600 hover:text-blue-700 text-xs gap-1"
                                                        >
                                                            <Edit className="w-3.5 h-3.5" /> Progress
                                                        </Button>
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            onClick={() => handleDeleteBoq(b.id)}
                                                            className="h-7 w-7 text-red-500"
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

                    {/* Edit BoQ Progress Modal */}
                    {editingBoq && (
                        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                            <div className="bg-white dark:bg-slate-900 border rounded-2xl max-w-sm w-full p-5 shadow-2xl space-y-4">
                                <h3 className="font-bold text-sm">Update Actual Progress: {editingBoq.item_no}</h3>
                                <p className="text-xs text-slate-500">{editingBoq.description}</p>
                                <form onSubmit={handleUpdateBoq} className="space-y-3">
                                    <div>
                                        <Label className="text-xs">Actual Executed Quantity ({editingBoq.unit})</Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            value={editBoqForm.data.actual_quantity}
                                            onChange={(e) => editBoqForm.setData('actual_quantity', parseFloat(e.target.value) || 0)}
                                            required
                                            className="h-9 text-xs"
                                        />
                                    </div>
                                    <div>
                                        <Label className="text-xs">Execution Status</Label>
                                        <Select
                                            value={editBoqForm.data.status}
                                            onValueChange={(val: any) => editBoqForm.setData('status', val)}
                                        >
                                            <SelectTrigger className="h-9 text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="pending">Pending</SelectItem>
                                                <SelectItem value="in_progress">In Progress</SelectItem>
                                                <SelectItem value="completed">Completed</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="flex justify-end gap-2 pt-2">
                                        <Button type="button" variant="outline" size="sm" onClick={() => setEditingBoq(null)}>
                                            Cancel
                                        </Button>
                                        <Button type="submit" size="sm" disabled={editBoqForm.processing} className="bg-emerald-600 hover:bg-emerald-700 text-white">
                                            Update BoQ
                                        </Button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* SECTION 2: Daily Site Diary */}
            {activeSection === 'diary' && (
                <div className="space-y-4">
                    {/* Add Diary Log Form */}
                    {isAddDiaryOpen && (
                        <Card className="border border-emerald-500/30 shadow-md bg-emerald-50/10 dark:bg-emerald-950/20">
                            <CardHeader className="p-4 pb-2">
                                <CardTitle className="text-sm font-bold flex items-center gap-2">
                                    <HardHat className="w-4 h-4 text-emerald-600" /> New Daily Site Diary Entry
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-4 pt-2">
                                <form onSubmit={handleCreateDiary} className="space-y-3">
                                    <div className="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                        <div>
                                            <Label className="text-xs">Date</Label>
                                            <Input
                                                type="date"
                                                value={diaryForm.data.date}
                                                onChange={(e) => diaryForm.setData('date', e.target.value)}
                                                required
                                                className="h-9 text-xs"
                                            />
                                        </div>
                                        <div>
                                            <Label className="text-xs">Weather Conditions</Label>
                                            <Input
                                                value={diaryForm.data.weather}
                                                onChange={(e) => diaryForm.setData('weather', e.target.value)}
                                                placeholder="Sunny, Heavy Rain, Dusty"
                                                required
                                                className="h-9 text-xs"
                                            />
                                        </div>
                                        <div>
                                            <Label className="text-xs">Workforce Headcount</Label>
                                            <Input
                                                type="number"
                                                value={diaryForm.data.workforce_count}
                                                onChange={(e) => diaryForm.setData('workforce_count', parseInt(e.target.value) || 0)}
                                                required
                                                className="h-9 text-xs"
                                            />
                                        </div>
                                        <div>
                                            <Label className="text-xs">Machinery Operating Hours</Label>
                                            <Input
                                                type="number"
                                                step="0.5"
                                                value={diaryForm.data.machinery_hours}
                                                onChange={(e) => diaryForm.setData('machinery_hours', parseFloat(e.target.value) || 0)}
                                                className="h-9 text-xs"
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <Label className="text-xs">Work Accomplished Today</Label>
                                        <RichTextEditor
                                            variant="compact"
                                            minHeight="100px"
                                            content={diaryForm.data.work_completed}
                                            onChange={(value) => diaryForm.setData('work_completed', value)}
                                            placeholder="Detailed summary of construction/site activities completed..."
                                        />
                                        {diaryForm.errors.work_completed && (
                                            <p className="text-xs text-red-500 mt-1">{diaryForm.errors.work_completed}</p>
                                        )}
                                    </div>
                                    <div>
                                        <Label className="text-xs">Site Issues / Material Delays (Optional)</Label>
                                        <Input
                                            value={diaryForm.data.challenges}
                                            onChange={(e) => diaryForm.setData('challenges', e.target.value)}
                                            placeholder="e.g. Cement delivery delayed by 4 hours..."
                                            className="h-9 text-xs"
                                        />
                                    </div>
                                    <div className="flex justify-end gap-2 pt-2">
                                        <Button type="button" variant="outline" size="sm" onClick={() => setIsAddDiaryOpen(false)}>
                                            Cancel
                                        </Button>
                                        <Button type="submit" size="sm" disabled={diaryForm.processing} className="bg-emerald-600 hover:bg-emerald-700 text-white">
                                            {diaryForm.processing ? 'Saving...' : 'Submit Site Log'}
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    )}

                    {/* Diary History List */}
                    <div className="space-y-3">
                        {siteDiaries.length === 0 ? (
                            <Card className="border shadow-sm p-8 text-center text-sm text-slate-500">
                                No site diary entries logged yet. Click "Log Daily Site Diary" to document daily site operations.
                            </Card>
                        ) : (
                            siteDiaries.map((diary) => (
                                <Card key={diary.id} className="border shadow-sm hover:shadow-md transition-shadow">
                                    <CardHeader className="p-4 pb-2 border-b bg-slate-50/50 dark:bg-slate-900/50 flex flex-row items-center justify-between">
                                        <div className="flex items-center gap-3">
                                            <div className="h-8 w-8 rounded-lg bg-emerald-100 dark:bg-emerald-950 flex items-center justify-center text-emerald-600 font-bold text-xs">
                                                <Calendar className="w-4 h-4" />
                                            </div>
                                            <div>
                                                <span className="font-bold text-sm text-slate-900 dark:text-white">
                                                    {formatDate(diary.date)}
                                                </span>
                                                <div className="text-[11px] text-slate-500 flex items-center gap-2">
                                                    <span>Logged by: <strong>{diary.logged_by?.name || 'Engineer'}</strong></span>
                                                    <span>•</span>
                                                    <span className="flex items-center gap-1">
                                                        <Sun className="w-3 h-3 text-amber-500" /> {diary.weather}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <div className="flex items-center gap-2 text-xs">
                                                <Badge variant="outline" className="gap-1 text-[11px]">
                                                    <Users className="w-3 h-3 text-blue-500" /> {diary.workforce_count} Workers
                                                </Badge>
                                                <Badge variant="outline" className="gap-1 text-[11px]">
                                                    <Wrench className="w-3 h-3 text-amber-500" /> {diary.machinery_hours} Machine Hrs
                                                </Badge>
                                            </div>
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                onClick={() => handleDeleteDiary(diary.id)}
                                                className="h-7 w-7 text-red-500"
                                            >
                                                <Trash2 className="w-3.5 h-3.5" />
                                            </Button>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="p-4 space-y-2 text-xs">
                                        <div>
                                            <span className="font-semibold text-slate-700 dark:text-slate-300 block mb-0.5">Work Done:</span>
                                            <RichTextViewer content={diary.work_completed} compact />
                                        </div>
                                        {diary.challenges && (
                                            <div className="p-2.5 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900/60 text-amber-900 dark:text-amber-200">
                                                <span className="font-semibold block mb-0.5">Site Challenges / Delays:</span>
                                                <p>{diary.challenges}</p>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

export default ConstructionWorkspaceTab;
