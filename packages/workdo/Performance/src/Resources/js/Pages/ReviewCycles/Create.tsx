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

interface CreateProps {
    onSuccess: () => void;
    hrmContext?: any;
}

export default function Create({ onSuccess, hrmContext }: CreateProps) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, transform } = useForm({
        name: '',
        frequency: 'annual',
        start_date: '',
        end_date: '',
        self_review_deadline: '',
        manager_review_deadline: '',
        calendar_type: 'gregorian',
        ethiopian_fiscal_year: '',
        ethiopian_quarter: 'annual',
        branch_id: 'none',
        department_id: 'none',
        description: '',
        status: 'active',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({
            ...form,
            branch_id: form.branch_id === 'none' ? '' : form.branch_id,
            department_id: form.department_id === 'none' ? '' : form.department_id,
            ethiopian_fiscal_year: form.ethiopian_fiscal_year === '' ? null : form.ethiopian_fiscal_year,
        }));
        post(route('performance.review-cycles.store'), {
            onSuccess: () => onSuccess(),
        });
    };

    return (
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{t('Create Review Cycle')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <Label htmlFor="name" required>{t('Name')}</Label>
                    <Input
                        id="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder={t('e.g., FY 2019 Q1 Appraisal Cycle')}
                        required
                    />
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
                        <InputError message={errors.start_date} />
                    </div>
                    <div>
                        <Label>{t('End Date')}</Label>
                        <DatePicker value={data.end_date} onChange={(v) => setData('end_date', v)} />
                        <InputError message={errors.end_date} />
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
                    <InputError message={errors.description} />
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
                    <InputError message={errors.status} />
                </div>

                <div className="flex justify-end gap-2 pt-4">
                    <Button type="button" variant="outline" onClick={onSuccess}>{t('Cancel')}</Button>
                    <Button type="submit" disabled={processing}>{processing ? t('Creating...') : t('Create')}</Button>
                </div>
            </form>
        </DialogContent>
    );
}
