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

interface CreateProps {
    onSuccess: () => void;
    employees: any[];
    goalTypes: any[];
    reviewCycles?: any[];
    kpiSources?: { value: string; label: string }[];
}

export default function Create({
    onSuccess,
    employees = [],
    goalTypes = [],
    reviewCycles = [],
    kpiSources = [{ value: 'manual', label: 'Manual Tracking' }],
}: CreateProps) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, transform, setError, clearErrors } = useForm({
        employee_id: '',
        goal_type_id: '',
        review_cycle_id: 'none',
        title: '',
        description: '',
        start_date: '',
        end_date: '',
        target: '',
        target_numeric: '',
        current_value: '',
        unit: '',
        progress: '0',
        weight: '0',
        status: 'not_started',
        kpi_source: 'manual',
        auto_sync_enabled: false,
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
        post(route('performance.employee-goals.store'), {
            onSuccess: () => onSuccess(),
        });
    };

    return (
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{t('Create Employee Goal')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label htmlFor="employee_id" required>{t('Employee')}</Label>
                        <Select value={data.employee_id} onValueChange={(value) => setData('employee_id', value)} required>
                            <SelectTrigger><SelectValue placeholder={t('Select employee')} /></SelectTrigger>
                            <SelectContent searchable={true}>
                                {(employees || []).length > 0 ? (
                                    (employees || []).map((employee) => (
                                        <SelectItem key={employee.id} value={employee.id.toString()}>
                                            {employee.name}
                                        </SelectItem>
                                    ))
                                ) : (
                                    <SelectItem value="no-employees" disabled>{t('No employees available')}</SelectItem>
                                )}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.employee_id} />
                    </div>

                    <div>
                        <Label htmlFor="goal_type_id" required>{t('Goal Type')}</Label>
                        <Select value={data.goal_type_id} onValueChange={(value) => setData('goal_type_id', value)} required>
                            <SelectTrigger><SelectValue placeholder={t('Select goal type')} /></SelectTrigger>
                            <SelectContent>
                                {(goalTypes || []).length > 0 ? (
                                    (goalTypes || []).map((type) => (
                                        <SelectItem key={type.id} value={type.id.toString()}>
                                            {type.name}
                                        </SelectItem>
                                    ))
                                ) : (
                                    <SelectItem value="no-goal-types" disabled>{t('No goal types available')}</SelectItem>
                                )}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.goal_type_id} />
                    </div>
                </div>

                <div>
                    <Label htmlFor="title" required>{t('Title')}</Label>
                    <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder={t('Enter goal title')} required />
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
                        placeholder={t('Enter goal description')}
                    />
                    <InputError message={errors.description} />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label required>{t('Start Date')}</Label>
                        <DatePicker value={data.start_date} onChange={(value) => setData('start_date', value)} placeholder={t('Select start date')} />
                        <InputError message={errors.start_date} />
                    </div>
                    <div>
                        <Label required>{t('End Date')}</Label>
                        <DatePicker value={data.end_date} onChange={(value) => setData('end_date', value)} placeholder={t('Select end date')} />
                        <InputError message={errors.end_date} />
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <Label htmlFor="target" required>{t('Target Display')}</Label>
                        <Input id="target" value={data.target} onChange={(e) => setData('target', e.target.value)} placeholder={t('e.g., 100 calls or ETB 500k')} required />
                        <InputError message={errors.target} />
                    </div>
                    <div>
                        <Label htmlFor="target_numeric">{t('Numeric Target')}</Label>
                        <Input id="target_numeric" type="number" step="0.01" min="0" value={data.target_numeric} onChange={(e) => setData('target_numeric', e.target.value)} placeholder="100" />
                    </div>
                    <div>
                        <Label htmlFor="progress">{t('Progress (%)')}</Label>
                        <Input id="progress" type="number" min="0" max="100" value={data.progress} onChange={(e) => setData('progress', e.target.value)} placeholder="0" />
                        <InputError message={errors.progress} />
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <Label>{t('Review Cycle')}</Label>
                        <Select value={data.review_cycle_id} onValueChange={(value) => setData('review_cycle_id', value)}>
                            <SelectTrigger><SelectValue placeholder={t('Optional cycle')} /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">{t('Unlinked')}</SelectItem>
                                {(reviewCycles || []).map((c) => (
                                    <SelectItem key={c.id} value={c.id.toString()}>{c.name}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label>{t('ERP KPI Auto-Sync Source')}</Label>
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
                            <SelectTrigger><SelectValue placeholder={t('Select status')} /></SelectTrigger>
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

                {data.kpi_source !== 'manual' && (
                    <label className="flex items-center gap-2 text-sm cursor-pointer">
                        <Checkbox checked={data.auto_sync_enabled} onCheckedChange={(v) => setData('auto_sync_enabled', Boolean(v))} />
                        {t('Automatically pull KPI actuals from linked ERP module on save')}
                    </label>
                )}

                <div className="flex justify-end gap-2 pt-4">
                    <Button type="button" variant="outline" onClick={onSuccess}>{t('Cancel')}</Button>
                    <Button type="submit" disabled={processing}>{processing ? t('Creating...') : t('Create')}</Button>
                </div>
            </form>
        </DialogContent>
    );
}
