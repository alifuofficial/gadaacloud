import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useForm, usePage, router } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Label } from '@/components/ui/label';
import InputError from '@/components/ui/input-error';
import { Input } from '@/components/ui/input';
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DatePicker } from '@/components/ui/date-picker';
import { CurrencyInput } from '@/components/ui/currency-input';
import { CreateGoalFormData, CreateGoalProps } from './types';

export default function Create({ onSuccess }: CreateGoalProps) {
    const { categories, chartOfAccounts, bankAccounts, auth } = usePage<any>().props;
    const { t } = useTranslation();

    const { data, setData, post, processing, errors } = useForm<CreateGoalFormData>({
        goal_name: '',
        goal_description: '',
        category_id: (categories || []).length > 0 ? categories[0].id : 0,
        goal_type: 'savings',
        target_amount: 0,
        current_amount: 0,
        start_date: '',
        target_date: '',
        ethiopian_fiscal_year: '',
        ethiopian_quarter: '',
        priority: 'medium',
        status: 'draft',
        account_id: undefined,
        bank_account_id: undefined,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('goal.goals.store'), {
            onSuccess: () => {
                onSuccess();
            }
        });
    };

    return (
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{t('Create Goal')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label htmlFor="goal_name" required>{t('Goal Name')}</Label>
                        <Input
                            id="goal_name"
                            type="text"
                            value={data.goal_name}
                            onChange={(e) => setData('goal_name', e.target.value)}
                            placeholder={t('Enter Goal Name')}
                            required
                        />
                        <InputError message={errors.goal_name} />
                    </div>

                    <div>
                        <Label htmlFor="category_id" required>{t('Category')}</Label>
                        <Select
                            value={data.category_id ? data.category_id.toString() : '0'}
                            onValueChange={(value) => setData('category_id', parseInt(value))}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={t('Select Category')} />
                            </SelectTrigger>
                            <SelectContent>
                                {(categories || []).map((category: any) => (
                                    <SelectItem key={category.id} value={category.id.toString()}>
                                        {category.category_name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {(categories || []).length === 0 && auth?.user?.permissions?.includes('manage-categories') && (
                            <p className="text-xs text-muted-foreground mt-1">
                                {t('Create category here.')} <button type="button" onClick={() => router.get(route('goal.categories.index'))} className="text-primary hover:underline">{t('Create category')}</button>
                            </p>
                        )}
                        <InputError message={errors.category_id} />
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label htmlFor="goal_type" required>{t('Goal Type')}</Label>
                        <Select value={data.goal_type} onValueChange={(value: any) => setData('goal_type', value)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="savings">{t('Savings / Liquid Reserves')}</SelectItem>
                                <SelectItem value="debt_reduction">{t('Debt Reduction')}</SelectItem>
                                <SelectItem value="expense_reduction">{t('Expense Reduction')}</SelectItem>
                                <SelectItem value="revenue_target">{t('Revenue Target')}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.goal_type} />
                    </div>

                    <div>
                        <Label htmlFor="priority" required>{t('Priority')}</Label>
                        <Select value={data.priority} onValueChange={(value: any) => setData('priority', value)}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="low">{t('Low')}</SelectItem>
                                <SelectItem value="medium">{t('Medium')}</SelectItem>
                                <SelectItem value="high">{t('High')}</SelectItem>
                                <SelectItem value="critical">{t('Critical')}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.priority} />
                    </div>
                </div>

                <div>
                    <CurrencyInput
                        label={t('Target Amount')}
                        value={data.target_amount.toString()}
                        onChange={(value) => setData('target_amount', parseFloat(value) || 0)}
                        error={errors.target_amount}
                        required
                    />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label required>{t('Start Date')}</Label>
                        <DatePicker
                            value={data.start_date}
                            onChange={(value) => setData('start_date', value)}
                            placeholder={t('Select start date')}
                        />
                        <InputError message={errors.start_date} />
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
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label htmlFor="account_id">{t('General Ledger Account')}</Label>
                        <Select
                            value={data.account_id ? data.account_id.toString() : '0'}
                            onValueChange={(value) => setData('account_id', value === '0' ? undefined : parseInt(value))}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={t('Select Account (Optional)')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="0">{t('None / Manual Tracking')}</SelectItem>
                                {(chartOfAccounts || []).filter((account: any) => {
                                    if (data.goal_type === 'savings') {
                                        // Standard Tier-1 ERP: Savings are Cash/Bank Assets (debit) or Reserve Equity (credit)
                                        return true;
                                    } else if (data.goal_type === 'debt_reduction') {
                                        return account.normal_balance === 'credit';
                                    } else if (data.goal_type === 'expense_reduction') {
                                        return account.normal_balance === 'debit';
                                    } else if (data.goal_type === 'revenue_target') {
                                        return account.normal_balance === 'credit';
                                    }
                                    return true;
                                }).map((account: any) => (
                                    <SelectItem key={account.id} value={account.id.toString()}>
                                        {account.account_code} - {account.account_name} ({account.normal_balance?.toUpperCase()})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.account_id} />
                    </div>

                    <div>
                        <Label htmlFor="bank_account_id">{t('Linked Bank Account')}</Label>
                        <Select
                            value={data.bank_account_id ? data.bank_account_id.toString() : '0'}
                            onValueChange={(value) => setData('bank_account_id', value === '0' ? undefined : parseInt(value))}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={t('Select Bank (Optional)')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="0">{t('None / Not Linked')}</SelectItem>
                                {(bankAccounts || []).map((bank: any) => (
                                    <SelectItem key={bank.id} value={bank.id.toString()}>
                                        {bank.bank_name} - {bank.holder_name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.bank_account_id} />
                    </div>
                </div>

                <div>
                    <Label htmlFor="goal_description">{t('Description')}</Label>
                    <RichTextEditor
                        id="goal_description"
                        variant="compact"
                        minHeight="120px"
                        content={data.goal_description}
                        onChange={(value) => setData('goal_description', value)}
                        placeholder={t('Enter Description')}
                    />
                    <InputError message={errors.goal_description} />
                </div>

                <div className="flex justify-end gap-3 pt-2">
                    <Button type="button" variant="outline" onClick={onSuccess}>
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? t('Saving...') : t('Create Goal')}
                    </Button>
                </div>
            </form>
        </DialogContent>
    );
}
