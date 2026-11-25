import { useTranslation } from 'react-i18next';
import { DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate, formatCurrency } from '@/utils/helpers';
import { Goal } from './types';

interface ViewGoalProps {
    goal: Goal;
}

export default function View({ goal }: ViewGoalProps) {
    const { t } = useTranslation();

    const getStatusBadge = (status?: string) => {
        const s = status || 'draft';
        return (
            <span className={`px-2 py-1 rounded-full text-xs font-semibold ${
                s === 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' :
                s === 'active' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' :
                s === 'paused' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' :
                s === 'cancelled' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' :
                'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300'
            }`}>
                {t(s.charAt(0).toUpperCase() + s.slice(1))}
            </span>
        );
    };

    const getPriorityBadge = (priority?: string) => {
        const p = priority || 'medium';
        return (
            <span className={`px-2 py-1 rounded-full text-xs font-semibold ${
                p === 'critical' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' :
                p === 'high' ? 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400' :
                p === 'medium' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' :
                'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
            }`}>
                {t(p.charAt(0).toUpperCase() + p.slice(1))}
            </span>
        );
    };

    const target = Number(goal?.target_amount || 0);
    const current = Number(goal?.current_amount || 0);
    const progressPercentage = target > 0 ? Math.min(100, (current / target) * 100) : 0;

    return (
        <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{t('Goal Details')} - {goal?.goal_name || '-'}</DialogTitle>
            </DialogHeader>

            <div className="space-y-6 mt-3">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base flex justify-between items-center">
                            {t('Goal Information')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div>
                                <span className="font-semibold">{t('Goal Name')}</span>
                                <p className="mt-1 text-muted-foreground">{goal?.goal_name || '-'}</p>
                            </div>
                            <div>
                                <span className="font-semibold">{t('Category')}</span>
                                <p className="mt-1 text-muted-foreground">{goal?.category?.category_name || '-'}</p>
                            </div>
                            <div>
                                <span className="font-semibold">{t('Goal Type')}</span>
                                <p className="mt-1 text-muted-foreground">{goal?.goal_type ? t(goal.goal_type.replace('_', ' ')) : '-'}</p>
                            </div>
                            <div>
                                <span className="font-semibold">{t('Priority')}</span>
                                <div className="mt-1">{getPriorityBadge(goal?.priority)}</div>
                            </div>
                            <div>
                                <span className="font-semibold">{t('Status')}</span>
                                <div className="mt-1">{getStatusBadge(goal?.status)}</div>
                            </div>
                            <div>
                                <span className="font-semibold">{t('Chart of Account')}</span>
                                <p className="mt-1 text-muted-foreground">{goal?.account?.account_name || goal?.account?.name || '-'}</p>
                            </div>
                            <div>
                                <span className="font-semibold">{t('Linked Bank Account')}</span>
                                <p className="mt-1 text-muted-foreground">
                                    {goal?.bank_account ? `${goal.bank_account.bank_name} - ${goal.bank_account.holder_name}` : '-'}
                                </p>
                            </div>
                            <div>
                                <span className="font-semibold">{t('Start Date')}</span>
                                <p className="mt-1 text-muted-foreground">{formatDate(goal?.start_date)}</p>
                            </div>
                            <div>
                                <span className="font-semibold">{t('Target Date')}</span>
                                <p className="mt-1 text-muted-foreground">{formatDate(goal?.target_date)}</p>
                            </div>
                        </div>
                        {goal?.goal_description && (
                            <div className="text-sm mt-4">
                                <span className="font-semibold">{t('Description')}</span>
                                <p className="mt-1 p-3 bg-muted/40 rounded text-sm">{goal.goal_description}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{t('Financial Progress')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <span className="font-semibold text-sm">{t('Target Amount')}</span>
                                    <p className="mt-1 text-2xl font-bold text-primary">{formatCurrency(target)}</p>
                                </div>
                                <div>
                                    <span className="font-semibold text-sm">{t('Current Amount')}</span>
                                    <p className="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{formatCurrency(current)}</p>
                                </div>
                            </div>

                            <div>
                                <div className="flex justify-between items-center mb-2">
                                    <span className="font-semibold text-sm">{t('Progress')}</span>
                                    <span className="text-sm text-muted-foreground font-mono">{progressPercentage.toFixed(1)}%</span>
                                </div>
                                <div className="w-full bg-secondary rounded-full h-3 overflow-hidden">
                                    <div
                                        className="bg-primary h-3 rounded-full transition-all duration-300"
                                        style={{ width: `${progressPercentage}%` }}
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </DialogContent>
    );
}
