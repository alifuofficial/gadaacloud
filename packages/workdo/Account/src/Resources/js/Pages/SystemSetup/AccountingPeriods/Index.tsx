import { useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import NoRecordsFound from '@/components/no-records-found';
import SystemSetupSidebar from '../SystemSetupSidebar';
import { Calendar, Lock, LockOpen, ShieldCheck, Plus } from 'lucide-react';
import { formatDate } from '@/utils/helpers';

interface Period {
    id: number;
    name: string;
    fiscal_year: string;
    period_number: number;
    start_date: string;
    end_date: string;
    status: 'open' | 'soft_locked' | 'closed';
    locked_at?: string;
    locked_by_user?: { id: number; name: string };
}

export default function Index() {
    const { t } = useTranslation();
    const { periods, filters } = usePage<any>().props;
    const [generateOpen, setGenerateOpen] = useState(false);

    const { data, setData, post, processing } = useForm({
        fiscal_year: String(new Date().getFullYear()),
        calendar_mode: 'ethiopian',
    });

    const handleGenerate = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('account.accounting-periods.generate'), {
            onSuccess: () => setGenerateOpen(false),
        });
    };

    const changeStatus = (id: number, status: 'open' | 'soft_locked' | 'closed') => {
        router.post(route('account.accounting-periods.update-status', id), { status }, { preserveScroll: true });
    };

    const statusBadge = (status: Period['status']) => {
        if (status === 'closed') {
            return <Badge className="bg-red-100 text-red-800">{t('Closed')}</Badge>;
        }
        if (status === 'soft_locked') {
            return <Badge className="bg-amber-100 text-amber-800">{t('Soft Locked')}</Badge>;
        }
        return <Badge className="bg-green-100 text-green-800">{t('Open')}</Badge>;
    };

    const columns = [
        { key: 'fiscal_year', header: t('Fiscal Year') },
        { key: 'name', header: t('Period Name') },
        { key: 'start_date', header: t('Start Date'), render: (v: string) => formatDate(v) },
        { key: 'end_date', header: t('End Date'), render: (v: string) => formatDate(v) },
        { key: 'status', header: t('Status'), render: (v: Period['status']) => statusBadge(v) },
        {
            key: 'locked_by',
            header: t('Locked By'),
            render: (_: any, row: Period) => row.locked_by_user?.name || '-',
        },
        {
            key: 'actions',
            header: t('Actions'),
            render: (_: any, row: Period) => (
                <div className="flex items-center gap-1">
                    {row.status !== 'closed' && row.status !== 'soft_locked' && (
                        <Button variant="ghost" size="sm" onClick={() => changeStatus(row.id, 'soft_locked')} title={t('Soft Lock')}>
                            <Lock className="h-4 w-4 text-amber-600" />
                        </Button>
                    )}
                    {row.status === 'soft_locked' && (
                        <Button variant="ghost" size="sm" onClick={() => changeStatus(row.id, 'open')} title={t('Reopen')}>
                            <LockOpen className="h-4 w-4 text-green-600" />
                        </Button>
                    )}
                    {row.status !== 'closed' && (
                        <Button variant="ghost" size="sm" onClick={() => changeStatus(row.id, 'closed')} title={t('Permanently Close')}>
                            <ShieldCheck className="h-4 w-4 text-red-600" />
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Accounting'), url: route('account.index') },
                { label: t('System Setup') },
                { label: t('Accounting Periods') },
            ]}
            pageTitle={t('Accounting Periods & Period Locks')}
        >
            <Head title={t('Accounting Periods')} />

            <div className="flex flex-col md:flex-row gap-8">
                <div className="md:w-64 flex-shrink-0">
                    <SystemSetupSidebar activeItem="accounting-periods" />
                </div>

                <div className="flex-1">
                    <Card className="shadow-sm">
                        <CardContent className="p-6">
                            <div className="flex flex-wrap justify-between items-center gap-4 mb-6">
                                <div>
                                    <h3 className="text-lg font-medium">{t('Fiscal Periods & Posting Locks')}</h3>
                                    <p className="text-sm text-muted-foreground">
                                        {t('Control GL posting windows for Ethiopian Fiscal Year (Hamle–Sene) or Gregorian periods.')}
                                    </p>
                                </div>
                                <Button size="sm" onClick={() => setGenerateOpen(true)} className="gap-2">
                                    <Plus className="h-4 w-4" />
                                    {t('Generate Fiscal Periods')}
                                </Button>
                            </div>

                            <DataTable
                                data={periods?.data || []}
                                columns={columns}
                                emptyState={
                                    <NoRecordsFound
                                        icon={Calendar}
                                        title={t('No accounting periods configured')}
                                        description={t('Generate your Ethiopian or Gregorian fiscal year periods to protect closed books from backdated entries.')}
                                        onCreateClick={() => setGenerateOpen(true)}
                                        createButtonText={t('Generate Fiscal Periods')}
                                        className="h-auto"
                                    />
                                }
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Dialog open={generateOpen} onOpenChange={setGenerateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Generate Fiscal Year Periods')}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleGenerate} className="space-y-4">
                        <div>
                            <Label htmlFor="calendar_mode">{t('Calendar Regime')}</Label>
                            <Select value={data.calendar_mode} onValueChange={(v) => setData('calendar_mode', v)}>
                                <SelectTrigger className="mt-1">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="ethiopian">{t('Ethiopian Fiscal Year (Hamle 1 – Sene 30, 13 Periods)')}</SelectItem>
                                    <SelectItem value="gregorian">{t('Gregorian Calendar (Jan 1 – Dec 31, 12 Months)')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label htmlFor="fiscal_year">{t('Fiscal Year (e.g. 2018 EFY or 2026)')}</Label>
                            <Input
                                id="fiscal_year"
                                type="number"
                                className="mt-1"
                                value={data.fiscal_year}
                                onChange={(e) => setData('fiscal_year', e.target.value)}
                                required
                            />
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => setGenerateOpen(false)}>
                                {t('Cancel')}
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? t('Generating...') : t('Generate')}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
