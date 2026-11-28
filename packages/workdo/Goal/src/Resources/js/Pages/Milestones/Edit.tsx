import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useForm } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Label } from '@/components/ui/label';
import InputError from '@/components/ui/input-error';
import { Input } from '@/components/ui/input';
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DatePicker } from '@/components/ui/date-picker';
import { CurrencyInput } from '@/components/ui/currency-input';
import { EditMilestoneFormData, EditMilestoneProps } from './types';

export default function EditMilestone({ milestone, goals, onSuccess }: EditMilestoneProps) {
    const { t } = useTranslation();

    const { data, setData, put, processing, errors } = useForm<EditMilestoneFormData>({
        goal_id: milestone.goal_id ? milestone.goal_id.toString() : '0',
        milestone_name: milestone.milestone_name ?? '',
        milestone_description: milestone.milestone_description ?? '',
        target_amount: milestone.target_amount ?? 0,
        current_amount: (milestone as any).achieved_amount ?? milestone.current_amount ?? 0,
        target_date: milestone.target_date ?? '',
        status: milestone.status ?? 'pending',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('goal.milestones.update', milestone.id), {
            onSuccess: () => {
                onSuccess();
            }
        });
    };

    return (
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{t('Edit Milestone')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <Label htmlFor="goal_id" required>{t('Goal')}</Label>
                    <Select value={data.goal_id || '0'} onValueChange={(value) => setData('goal_id', value)}>
                        <SelectTrigger>
                            <SelectValue placeholder={t('Select Goal')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="0" disabled>{t('Select Goal')}</SelectItem>
                            {(goals || []).map((goal) => (
                                <SelectItem key={goal.id} value={goal.id.toString()}>
                                    {goal.goal_name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.goal_id} />
                </div>

                <div>
                    <Label htmlFor="milestone_name" required>{t('Milestone Name')}</Label>
                    <Input
                        id="milestone_name"
                        type="text"
                        value={data.milestone_name}
                        onChange={(e) => setData('milestone_name', e.target.value)}
                        placeholder={t('Enter Milestone Name')}
                        required
                    />
                    <InputError message={errors.milestone_name} />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <CurrencyInput
                            label={t('Target Amount')}
                            value={data.target_amount.toString()}
                            onChange={(value) => setData('target_amount', parseFloat(value) || 0)}
                            error={errors.target_amount}
                            required
                        />
                    </div>
                    <div>
                        <Label htmlFor="status" required>{t('Status')}</Label>
                        <Select value={data.status} onValueChange={(value: any) => setData('status', value)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="pending">{t('Pending')}</SelectItem>
                                <SelectItem value="achieved">{t('Achieved')}</SelectItem>
                                <SelectItem value="overdue">{t('Overdue')}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.status} />
                    </div>
                </div>

                <div>
                    <Label required>{t('Target Date')}</Label>
                    <DatePicker
                        value={data.target_date}
                        onChange={(value) => setData('target_date', value)}
                        placeholder={t('Select target date')}
                    />
                    <InputError message={errors.target_date} />
                </div>

                <div>
                    <Label htmlFor="milestone_description">{t('Description')}</Label>
                    <RichTextEditor
                        id="milestone_description"
                        variant="compact"
                        minHeight="120px"
                        content={data.milestone_description}
                        onChange={(value) => setData('milestone_description', value)}
                        placeholder={t('Enter Description')}
                    />
                    <InputError message={errors.milestone_description} />
                </div>

                <div className="flex justify-end gap-3 pt-2">
                    <Button type="button" variant="outline" onClick={onSuccess}>
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? t('Saving...') : t('Update Milestone')}
                    </Button>
                </div>
            </form>
        </DialogContent>
    );
}
