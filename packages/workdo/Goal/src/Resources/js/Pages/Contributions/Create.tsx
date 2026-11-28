import { useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import { DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { CurrencyInput } from '@/components/ui/currency-input';
import { DatePicker } from '@/components/ui/date-picker';
import { CreateContributionFormData, CreateContributionProps, ContributionsIndexProps } from './types';

export default function Create({ onSuccess }: CreateContributionProps) {
    const { t } = useTranslation();
    const { goals } = usePage<ContributionsIndexProps>().props;

    const safeGoals = goals || [];
    const initialGoalId = safeGoals.length > 0 ? safeGoals[0].id : 0;

    const { data, setData, post, processing, errors } = useForm<CreateContributionFormData>({
        goal_id: initialGoalId,
        contribution_date: new Date().toISOString().split('T')[0],
        contribution_amount: 0,
        contribution_type: 'manual',
        reference_type: 'manual',
        reference_id: undefined,
        notes: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('goal.contributions.store'), {
            onSuccess: () => {
                onSuccess();
            },
        });
    };

    return (
        <DialogContent className="max-w-md">
            <DialogHeader>
                <DialogTitle>{t('Create Contribution')}</DialogTitle>
            </DialogHeader>

            <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                    <Label htmlFor="goal_id" required>{t('Goal')}</Label>
                    <Select
                        value={data.goal_id ? data.goal_id.toString() : '0'}
                        onValueChange={(value) => setData('goal_id', parseInt(value))}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder={t('Select Goal')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="0" disabled>{t('Select Goal')}</SelectItem>
                            {safeGoals.map((goal) => (
                                <SelectItem key={goal.id} value={goal.id.toString()}>
                                    {goal.goal_name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {errors.goal_id && <p className="text-sm text-destructive">{errors.goal_id}</p>}
                </div>

                <div>
                    <Label htmlFor="contribution_date" required>{t('Date')}</Label>
                    <DatePicker
                        value={data.contribution_date}
                        onChange={(value) => setData('contribution_date', value)}
                        placeholder={t('Select contribution date')}
                    />
                    {errors.contribution_date && <p className="text-sm text-destructive">{errors.contribution_date}</p>}
                </div>

                <div>
                    <CurrencyInput
                        label={t('Amount')}
                        value={data.contribution_amount.toString()}
                        onChange={(value) => setData('contribution_amount', parseFloat(value) || 0)}
                        error={errors.contribution_amount}
                        required
                    />
                </div>

                <div>
                    <Label htmlFor="contribution_type" required>{t('Type')}</Label>
                    <Select
                        value={data.contribution_type}
                        onValueChange={(value: any) => setData('contribution_type', value)}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder={t('Select Type')} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="manual">{t('Manual Cash/Transfer')}</SelectItem>
                            <SelectItem value="automatic">{t('Automatic Rule')}</SelectItem>
                            <SelectItem value="journal_entry">{t('GL Journal Entry')}</SelectItem>
                        </SelectContent>
                    </Select>
                    {errors.contribution_type && <p className="text-sm text-destructive">{errors.contribution_type}</p>}
                </div>

                <div>
                    <Label htmlFor="notes">{t('Notes')}</Label>
                    <RichTextEditor
                        id="notes"
                        variant="compact"
                        minHeight="100px"
                        content={data.notes}
                        onChange={(value) => setData('notes', value)}
                        placeholder={t('Enter notes')}
                    />
                    {errors.notes && <p className="text-sm text-destructive">{errors.notes}</p>}
                </div>

                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={onSuccess}>
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? t('Saving...') : t('Create')}
                    </Button>
                </div>
            </form>
        </DialogContent>
    );
}
