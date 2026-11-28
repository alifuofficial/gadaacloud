import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useTranslation } from 'react-i18next';
import { Target, RefreshCw } from 'lucide-react';
import { router } from '@inertiajs/react';
import { formatDate, isRichTextEmpty } from '@/utils/helpers';
import { Button } from '@/components/ui/button';
import { RichTextViewer } from '@/components/ui/rich-text-viewer';

interface ShowProps {
    goal: any;
}

export default function Show({ goal }: ShowProps) {
    const { t } = useTranslation();
    const pct = Math.min(Number(goal?.progress_percentage ?? goal?.progress ?? 0), 100);

    const getStatusColor = (status: string) => {
        const colors: { [key: string]: string } = {
            not_started: 'bg-gray-100 text-gray-800',
            in_progress: 'bg-blue-100 text-blue-800',
            completed: 'bg-green-100 text-green-800',
            overdue: 'bg-red-100 text-red-800',
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    };

    const getStatusLabel = (status: string) => {
        const labels: { [key: string]: string } = {
            not_started: t('Not Started'),
            in_progress: t('In Progress'),
            completed: t('Completed'),
            overdue: t('Overdue'),
        };
        return labels[status] || status;
    };

    return (
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader className="pb-4 border-b">
                <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <div className="p-2 bg-primary/10 rounded-lg">
                            <Target className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <DialogTitle className="text-xl font-semibold">{t('Employee Goal Details')}</DialogTitle>
                            <p className="text-sm text-muted-foreground">{goal?.title}</p>
                        </div>
                    </div>
                    {goal?.kpi_source && goal.kpi_source !== 'manual' && (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => router.post(route('performance.employee-goals.sync-kpi', goal.id))}
                        >
                            <RefreshCw className="h-3.5 w-3.5 mr-1" /> {t('Sync ERP KPI')}
                        </Button>
                    )}
                </div>
            </DialogHeader>

            <div className="overflow-y-auto flex-1 p-4 space-y-6">
                <div className="grid grid-cols-2 gap-6">
                    <div>
                        <label className="text-sm font-medium text-gray-500">{t('Title')}</label>
                        <p className="mt-1 text-sm text-gray-900">{goal?.title || '-'}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-gray-500">{t('Employee')}</label>
                        <p className="mt-1 text-sm text-gray-900">{goal?.employee?.name || '-'}</p>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-6">
                    <div>
                        <label className="text-sm font-medium text-gray-500">{t('Goal Type')}</label>
                        <p className="mt-1 text-sm text-gray-900">{goal?.goal_type?.name || '-'}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-gray-500">{t('Target')}</label>
                        <p className="mt-1 text-sm text-gray-900">
                            {goal?.target || '-'} {goal?.unit || ''}
                            {goal?.current_value != null ? ` (${t('Actual')}: ${goal.current_value})` : ''}
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-6">
                    <div>
                        <label className="text-sm font-medium text-gray-500">{t('Progress')}</label>
                        <div className="mt-1 flex items-center gap-2">
                            <div className="flex-1 bg-gray-200 rounded-full h-2">
                                <div className="bg-blue-600 h-2 rounded-full transition-all duration-300" style={{ width: `${pct}%` }} />
                            </div>
                            <span className="text-sm text-gray-900">{pct}%</span>
                        </div>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-gray-500">{t('Status')}</label>
                        <p className="mt-1 text-sm text-gray-900">
                            <span className={`px-2 py-1 rounded-full text-sm ${getStatusColor(goal?.status)}`}>
                                {getStatusLabel(goal?.status)}
                            </span>
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-6">
                    <div>
                        <label className="text-sm font-medium text-gray-500">{t('Start Date')}</label>
                        <p className="mt-1 text-sm text-gray-900">{goal?.start_date ? formatDate(goal.start_date) : '-'}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-gray-500">{t('End Date')}</label>
                        <p className="mt-1 text-sm text-gray-900">{goal?.end_date ? formatDate(goal.end_date) : '-'}</p>
                    </div>
                </div>

                {!isRichTextEmpty(goal?.description) && (
                    <div>
                        <label className="text-sm font-medium text-gray-500">{t('Description')}</label>
                        <RichTextViewer content={goal.description} compact className="mt-1" />
                    </div>
                )}
            </div>
        </DialogContent>
    );
}
