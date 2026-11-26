import { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { AlertTriangle, Play } from 'lucide-react';
import { Resignation } from './types';

interface OffboardingImpact {
    open_tasks: number;
    open_bugs: number;
    projects: number;
    leads: number;
    deals: number;
    tickets: number;
    pending_approvals: number;
    direct_reports: number;
    total_open_items: number;
}

interface ResignationStatusModalProps {
    resignation: Resignation;
    onSuccess: () => void;
}

export default function ResignationStatusModal({ resignation, onSuccess }: ResignationStatusModalProps) {
    const { t } = useTranslation();
    const [selectedStatus, setSelectedStatus] = useState(resignation.status || 'pending');
    const [impact, setImpact] = useState<OffboardingImpact | null>(null);

    const { put, processing, setData } = useForm({
        status: selectedStatus
    });

    useEffect(() => {
        if (selectedStatus !== 'accepted' || !resignation.employee_id) {
            return;
        }
        let cancelled = false;
        fetch(route('hrm.resignations.impact', resignation.employee_id), {
            headers: { Accept: 'application/json' },
        })
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => {
                if (!cancelled && data && typeof data.total_open_items === 'number') {
                    setImpact(data);
                }
            })
            .catch(() => {
                // Non-blocking advisory fetch
            });
        return () => {
            cancelled = true;
        };
    }, [selectedStatus, resignation.employee_id]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('hrm.resignations.update-status', [resignation.id, selectedStatus]), {
            onSuccess: () => {
                onSuccess();
            }
        });
    };

    const statusOptions = [
        { value: 'pending', label: t('Pending') },
        { value: 'accepted', label: t('Accepted') },
        { value: 'rejected', label: t('Rejected') }
    ];

    return (
        <DialogContent className="max-w-md">
            <DialogHeader>
                <DialogTitle className="flex items-center gap-2">
                    <Play className="h-5 w-5 text-purple-600" />
                    {t('Update Resignation Status')}
                </DialogTitle>
            </DialogHeader>

            <form onSubmit={handleSubmit} className="space-y-6">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        {t('Status')}
                    </label>
                    <Select value={selectedStatus} onValueChange={(value) => {
                        setSelectedStatus(value);
                        setData('status', value);
                    }}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {statusOptions.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {selectedStatus === 'accepted' && (
                    <div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 space-y-1">
                        <div className="flex items-center gap-1.5 font-semibold">
                            <AlertTriangle className="h-4 w-4 text-amber-600 shrink-0" />
                            <span>{t('Offboarding & Handover Impact')}</span>
                        </div>
                        <p className="text-amber-800">
                            {t('Accepting this resignation places the employee on notice period or deactivates login once last working date is reached, while keeping historical project/work records intact.')}
                        </p>
                        {impact && impact.total_open_items > 0 && (
                            <p className="font-medium text-amber-950 pt-1">
                                {t('Open items requiring handover')}: {[
                                    impact.open_tasks > 0 ? `${impact.open_tasks} ${t('tasks')}` : null,
                                    impact.open_bugs > 0 ? `${impact.open_bugs} ${t('bugs')}` : null,
                                    impact.leads > 0 ? `${impact.leads} ${t('leads')}` : null,
                                    impact.deals > 0 ? `${impact.deals} ${t('deals')}` : null,
                                    impact.tickets > 0 ? `${impact.tickets} ${t('tickets')}` : null,
                                    impact.pending_approvals > 0 ? `${impact.pending_approvals} ${t('approvals')}` : null,
                                    impact.direct_reports > 0 ? `${impact.direct_reports} ${t('direct reports')}` : null,
                                ].filter(Boolean).join(', ')}
                            </p>
                        )}
                    </div>
                )}

                <div className="flex justify-end gap-3">
                    <Button type="button" variant="outline" onClick={onSuccess}>
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? t('Updating...') : t('Update Status')}
                    </Button>
                </div>
            </form>
        </DialogContent>
    );
}