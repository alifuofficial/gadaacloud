import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useForm } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { DatePicker } from "@/components/ui/date-picker";
import InputError from "@/components/ui/input-error";

interface CreateProps {
    onSuccess: () => void;
    employees: any[];
    reviewers: any[];
    reviewCycles: any[];
    reviewTemplates?: any[];
}

export default function Create({
    onSuccess,
    employees = [],
    reviewers = [],
    reviewCycles = [],
    reviewTemplates = [],
}: CreateProps) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, transform } = useForm({
        user_id: '',
        reviewer_id: '',
        review_cycle_id: '',
        review_template_id: 'none',
        review_date: '',
        status: 'pending',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({
            ...form,
            review_template_id: form.review_template_id === 'none' ? '' : form.review_template_id,
        }));
        post(route('performance.employee-reviews.store'), {
            onSuccess: () => onSuccess(),
        });
    };

    return (
        <DialogContent className="max-w-2xl">
            <DialogHeader>
                <DialogTitle>{t('Create Employee Review')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label htmlFor="user_id" required>{t('Employee')}</Label>
                        <Select value={data.user_id} onValueChange={(value) => setData('user_id', value)} required>
                            <SelectTrigger>
                                <SelectValue placeholder={t('Select employee')} />
                            </SelectTrigger>
                            <SelectContent searchable={true}>
                                {(employees || []).length > 0 ? (
                                    (employees || []).map((employee) => (
                                        <SelectItem key={employee.id} value={(employee.user_id ?? employee.id).toString()}>
                                            {employee.name}
                                        </SelectItem>
                                    ))
                                ) : (
                                    <SelectItem value="no-employees" disabled>
                                        {t('No employees available')}
                                    </SelectItem>
                                )}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.user_id} />
                    </div>

                    <div>
                        <Label htmlFor="reviewer_id" required>{t('Reviewer')}</Label>
                        <Select value={data.reviewer_id} onValueChange={(value) => setData('reviewer_id', value)} required>
                            <SelectTrigger>
                                <SelectValue placeholder={t('Select reviewer')} />
                            </SelectTrigger>
                            <SelectContent searchable={true}>
                                {(reviewers || []).map((reviewer) => (
                                    <SelectItem key={reviewer.id} value={reviewer.id.toString()}>
                                        {reviewer.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.reviewer_id} />
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label htmlFor="review_cycle_id" required>{t('Review Cycle')}</Label>
                        <Select value={data.review_cycle_id} onValueChange={(value) => setData('review_cycle_id', value)} required>
                            <SelectTrigger>
                                <SelectValue placeholder={t('Select review cycle')} />
                            </SelectTrigger>
                            <SelectContent>
                                {(reviewCycles || []).length > 0 ? (
                                    (reviewCycles || []).map((cycle) => (
                                        <SelectItem key={cycle.id} value={cycle.id.toString()}>
                                            {cycle.name}
                                        </SelectItem>
                                    ))
                                ) : (
                                    <SelectItem value="no-review-cycles" disabled>
                                        {t('No review cycles available')}
                                    </SelectItem>
                                )}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.review_cycle_id} />
                    </div>

                    <div>
                        <Label htmlFor="review_template_id">{t('Review Template')}</Label>
                        <Select value={data.review_template_id} onValueChange={(value) => setData('review_template_id', value)}>
                            <SelectTrigger>
                                <SelectValue placeholder={t('Standard KPI Template')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">{t('Standard KPI Set (Default)')}</SelectItem>
                                {(reviewTemplates || []).map((tpl) => (
                                    <SelectItem key={tpl.id} value={tpl.id.toString()}>
                                        {tpl.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.review_template_id} />
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label required>{t('Review Date')}</Label>
                        <DatePicker
                            value={data.review_date}
                            onChange={(value) => setData('review_date', value)}
                            placeholder={t('Select review date')}
                        />
                        <InputError message={errors.review_date} />
                    </div>

                    <div>
                        <Label htmlFor="status" required>{t('Status')}</Label>
                        <Select value={data.status} onValueChange={(value) => setData('status', value)} required>
                            <SelectTrigger>
                                <SelectValue placeholder={t('Select status')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="pending">{t('Pending')}</SelectItem>
                                <SelectItem value="in_progress">{t('In Progress')}</SelectItem>
                                <SelectItem value="self_submitted">{t('Self Submitted')}</SelectItem>
                                <SelectItem value="completed">{t('Completed')}</SelectItem>
                                <SelectItem value="cancelled">{t('Cancelled')}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.status} />
                    </div>
                </div>

                <div className="flex justify-end gap-2 pt-4">
                    <Button type="button" variant="outline" onClick={onSuccess}>
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? t('Creating...') : t('Create')}
                    </Button>
                </div>
            </form>
        </DialogContent>
    );
}
