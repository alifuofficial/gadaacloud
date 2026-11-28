import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useForm } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RichTextEditor } from "@/components/ui/rich-text-editor";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { DatePicker } from "@/components/ui/date-picker";
import { EthiopianFiscalPresets } from "@/components/ethiopian-fiscal-presets";
import InputError from "@/components/ui/input-error";

interface EditProps {
    reviewCycle: any;
    onSuccess: () => void;
    hrmContext?: any;
}

export default function Edit({ reviewCycle, onSuccess, hrmContext }: EditProps) {
    const { t } = useTranslation();
    const { data, setData, put, processing, errors, transform } = useForm({
        name: reviewCycle.name || '',
        frequency: reviewCycle.frequency || 'annual',
        start_date: reviewCycle.start_date || '',
        end_date: reviewCycle.end_date || '',
        self_review_deadline: reviewCycle.self_review_deadline || '',
        manager_review_deadline: reviewCycle.manager_review_deadline || '',
        calendar_type: reviewCycle.calendar_type || 'gregorian',
        branch_id: reviewCycle.branch_id ? reviewCycle.branch_id.toString() : 'none',
        department_id: reviewCycle.department_id ? reviewCycle.department_id.toString() : 'none',
        description: reviewCycle.description || '',
        status: reviewCycle.status || 'active',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({
            ...form,
            branch_id: form.branch_id === 'none' ? '' : form.branch_id,
            department_id: form.department_id === 'none' ? '' : form.department_id,
        }));
        put(route('performance.review-cycles.update', reviewCycle.id), {
            onSuccess: () => onSuccess(),
        });
    };

    return (
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{t('Edit Review Cycle')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <Label htmlFor="name" required>{t('Name')}</Label>
                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    <InputError message={errors.name} />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label htmlFor="frequency" required>{t('Frequency')}</Label>
                        <Select value={data.frequency} onValueChange={(value) => setData('frequency', value)} required>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="monthly">{t('Monthly')}</SelectItem>
                                <SelectItem value="quarterly">{t('Quarterly')}</SelectItem>
                                <SelectItem value="semi-annual">{t('Semi-Annual')}</SelectItem>
                                <SelectItem value="annual">{t('Annual')}</SelectItem>
                                <SelectItem value="ethiopian_fiscal">{t('Ethiopian Fiscal Period (Hamle-Sene)')}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.frequency} />
                    </div>
                    <div>
                        <Label>{t('Calendar Type')}</Label>
                        <Select value={data.calendar_type} onValueChange={(value) => setData('calendar_type', value)}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="gregorian">{t('Gregorian')}</SelectItem>
                                <SelectItem value="ethiopian">{t('Ethiopian (Kenat)')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label>{t('Start Date')}</Label>
                        <DatePicker value={data.start_date} onChange={(v) => setData('start_date', v)} />
                    </div>
                    <div>
                        <Label>{t('End Date')}</Label>
                        <DatePicker value={data.end_date} onChange={(v) => setData('end_date', v)} />
                    </div>
                </div>

                <EthiopianFiscalPresets
                    onSelectRange={(from, to) => {
                        setData((prev) => ({
                            ...prev,
                            start_date: from,
                            end_date: to,
                            calendar_type: 'ethiopian',
                        }));
                    }}
                />

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label>{t('Self-Review Deadline')}</Label>
                        <DatePicker value={data.self_review_deadline} onChange={(v) => setData('self_review_deadline', v)} />
                    </div>
                    <div>
                        <Label>{t('Manager Review Deadline')}</Label>
                        <DatePicker value={data.manager_review_deadline} onChange={(v) => setData('manager_review_deadline', v)} />
                    </div>
                </div>

                {hrmContext?.hrm_active && (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <Label>{t('Branch Scope')}</Label>
                            <Select value={data.branch_id} onValueChange={(v) => setData('branch_id', v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">{t('All Branches')}</SelectItem>
                                    {(hrmContext?.branches || []).map((b: any) => (
                                        <SelectItem key={b.id} value={b.id.toString()}>{b.branch_name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label>{t('Department Scope')}</Label>
                            <Select value={data.department_id} onValueChange={(v) => setData('department_id', v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">{t('All Departments')}</SelectItem>
                                    {(hrmContext?.departments || []).map((d: any) => (
                                        <SelectItem key={d.id} value={d.id.toString()}>{d.department_name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                )}

                <div>
                    <Label htmlFor="description">{t('Description')}</Label>
                    <RichTextEditor
                        id="description"
                        variant="compact"
                        minHeight="100px"
                        content={data.description}
                        onChange={(value) => setData('description', value)}
                    />
                </div>

                <div>
                    <Label htmlFor="status">{t('Status')}</Label>
                    <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">{t('Active')}</SelectItem>
                            <SelectItem value="inactive">{t('Inactive')}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex justify-end gap-2 pt-4">
                    <Button type="button" variant="outline" onClick={onSuccess}>{t('Cancel')}</Button>
                    <Button type="submit" disabled={processing}>{processing ? t('Updating...') : t('Update')}</Button>
                </div>
            </form>
        </DialogContent>
    );
}
