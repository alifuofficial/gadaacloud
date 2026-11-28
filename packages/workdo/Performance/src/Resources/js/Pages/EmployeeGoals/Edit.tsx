import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useForm } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RichTextEditor } from "@/components/ui/rich-text-editor";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { DatePicker } from "@/components/ui/date-picker";
import { Checkbox } from "@/components/ui/checkbox";
import InputError from "@/components/ui/input-error";
import { isRichTextEmpty } from "@/utils/helpers";

interface EditProps {
    goal: any;
    onSuccess: () => void;
    employees: any[];
    goalTypes: any[];
    reviewCycles?: any[];
    kpiSources?: { value: string; label: string }[];
}

export default function Edit({
    goal,
    onSuccess,
    employees = [],
    goalTypes = [],
    reviewCycles = [],
    kpiSources = [{ value: 'manual', label: 'Manual Tracking' }],
}: EditProps) {
    const { t } = useTranslation();
    const { data, setData, put, processing, errors, transform, setError, clearErrors } = useForm({
        employee_id: goal.employee_id?.toString() || '',
        goal_type_id: goal.goal_type_id?.toString() || '',
        review_cycle_id: goal.review_cycle_id ? goal.review_cycle_id.toString() : 'none',
        title: goal.title || '',
        description: goal.description || '',
        start_date: goal.start_date || '',
        end_date: goal.end_date || '',
        target: goal.target || '',
        target_numeric: goal.target_numeric?.toString() || '',
        current_value: goal.current_value?.toString() || '',
        progress: goal.progress?.toString() || '0',
        status: goal.status || 'not_started',
        kpi_source: goal.kpi_source || 'manual',
        auto_sync_enabled: Boolean(goal.auto_sync_enabled),
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isRichTextEmpty(data.description)) {
            setError('description', t('The description field is required.'));
            return;
        }
        transform((form) => ({
            ...form,
            review_cycle_id: form.review_cycle_id === 'none' ? '' : form.review_cycle_id,
            target_numeric: form.target_numeric === '' ? null : form.target_numeric,
            current_value: form.current_value === '' ? null : form.current_value,
        }));
        put(route('performance.employee-goals.update', goal.id), {
            onSuccess: () => onSuccess(),
        });
    };

    return (
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{t('Edit Employee Goal')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label htmlFor="employee_id" required>{t('Employee')}</Label>
                        <Select value={data.employee_id} onValueChange={(value) => setData('employee_id', value)} required>
                            <SelectTrigger><SelectValue placeholder={t('Select employee')} /></SelectTrigger>
                            <SelectContent searchable={true}>
                                {(employees || []).map((employee) => (
                                    <SelectItem key={employee.id} value={employee.id?.toString() || ''}>{employee.name}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.employee_id} />
                    </div>
                    <div>
                        <Label htmlFor="goal_type_id" required>{t('Goal Type')}</Label>
                        <Select value={data.goal_type_id} onValueChange={(value) => setData('goal_type_id', value)} required>
                            <SelectTrigger><SelectValue placeholder={t('Select goal type')} /></SelectTrigger>
                            <SelectContent>
                                {(goalTypes || []).map((type) => (
                                    <SelectItem key={type.id} value={type.id.toString()}>{type.name}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.goal_type_id} />
                    </div>
                </div>

                <div>
                    <Label htmlFor="title" required>{t('Title')}</Label>
                    <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                    <InputError message={errors.title} />
                </div>

                <div>
                    <Label htmlFor="description" required>{t('Description')}</Label>
                    <RichTextEditor
                        id="description"
                        variant="compact"
                        minHeight="110px"
                        content={data.description}
                        onChange={(value) => {
                            setData('description', value);
                            if (!isRichTextEmpty(value)) clearErrors('description');
                        }}
                    />
                    <InputError message={errors.description} />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label required>{t('Start Date')}</Label>
                        <DatePicker value={data.start_date} onChange={(value) => setData('start_date', value)} />
                        <InputError message={errors.start_date} />
                    </div>
                    <div>
                        <Label required>{t('End Date')}</Label>
                        <DatePicker value={data.end_date} onChange={(value) => setData('end_date', value)} />
                        <InputError message={errors.end_date} />
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <Label htmlFor="target" required>{t('Target Display')}</Label>
                        <Input id="target" value={data.target} onChange={(e) => setData('target', e.target.value)} required />
                        <InputError message={errors.target} />
                    </div>
                    <div>
                        <Label htmlFor="current_value">{t('Current Actual')}</Label>
                        <Input id="current_value" type="number" step="0.01" min="0" value={data.current_value} onChange={(e) => setData('current_value', e.target.value)} placeholder="Optional" />
                    </div>
                    <div>
                        <Label htmlFor="progress">{t('Progress (%)')}</Label>
                        <Input id="progress" type="number" min="0" max="100" value={data.progress} onChange={(e) => setData('progress', e.target.value)} />
                        <InputError message={errors.progress} />
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <Label>{t('Review Cycle')}</Label>
                        <Select value={data.review_cycle_id} onValueChange={(value) => setData('review_cycle_id', value)}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">{t('Unlinked')}</SelectItem>
                                {(reviewCycles || []).map((c) => (
                                    <SelectItem key={c.id} value={c.id.toString()}>{c.name}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label>{t('ERP KPI Source')}</Label>
                        <Select value={data.kpi_source} onValueChange={(value) => setData('kpi_source', value)}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                {(kpiSources || []).map((s) => (
                                    <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label htmlFor="status">{t('Status')}</Label>
                        <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="not_started">{t('Not Started')}</SelectItem>
                                <SelectItem value="in_progress">{t('In Progress')}</SelectItem>
                                <SelectItem value="completed">{t('Completed')}</SelectItem>
                                <SelectItem value="overdue">{t('Overdue')}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.status} />
                    </div>
                </div>

                <label className="flex items-center gap-2 text-sm cursor-pointer">
                    <Checkbox checked={data.auto_sync_enabled} onCheckedChange={(v) => setData('auto_sync_enabled', Boolean(v))} />
                    {t('Enable ERP KPI auto-sync')}
                </label>

                <div className="flex justify-end gap-2 pt-4">
                    <Button type="button" variant="outline" onClick={onSuccess}>{t('Cancel')}</Button>
                    <Button type="submit" disabled={processing}>{processing ? t('Updating...') : t('Update')}</Button>
                </div>
            </form>
        </DialogContent>
    );
}
