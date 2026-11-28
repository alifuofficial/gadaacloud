import { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useDeleteHandler } from '@/hooks/useDeleteHandler';
import { formatDate } from '@/utils/helpers';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DatePicker } from '@/components/ui/date-picker';
import { Pagination } from '@/components/ui/pagination';
import { Plus, Edit, Trash2, Award } from 'lucide-react';
import NoRecordsFound from '@/components/no-records-found';

export default function Index() {
    const { t } = useTranslation();
    const { assessments, employees = [], competencies = [], reviewCycles = [] } = usePage<any>().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<any>(null);

    const form = useForm({
        employee_id: '',
        competency_id: '',
        review_cycle_id: 'none',
        rating: '4',
        assessment_date: new Date().toISOString().slice(0, 10),
        comments: '',
    });

    const { deleteState, openDeleteDialog, closeDeleteDialog, confirmDelete } = useDeleteHandler({
        routeName: 'performance.employee-competencies.destroy',
        defaultMessage: t('Are you sure you want to delete this competency assessment?'),
    });

    const openCreate = () => {
        setEditing(null);
        form.setData({
            employee_id: '',
            competency_id: '',
            review_cycle_id: 'none',
            rating: '4',
            assessment_date: new Date().toISOString().slice(0, 10),
            comments: '',
        });
        setModalOpen(true);
    };

    const openEdit = (item: any) => {
        setEditing(item);
        form.setData({
            employee_id: item.employee_id?.toString() || '',
            competency_id: item.competency_id?.toString() || '',
            review_cycle_id: item.review_cycle_id ? item.review_cycle_id.toString() : 'none',
            rating: item.rating?.toString() || '4',
            assessment_date: item.assessment_date || '',
            comments: item.comments || '',
        });
        setModalOpen(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((f) => ({
            ...f,
            review_cycle_id: f.review_cycle_id === 'none' ? '' : f.review_cycle_id,
        }));
        if (editing) {
            form.put(route('performance.employee-competencies.update', editing.id), { onSuccess: () => setModalOpen(false) });
        } else {
            form.post(route('performance.employee-competencies.store'), { onSuccess: () => setModalOpen(false) });
        }
    };

    const columns = [
        { key: 'employee', header: t('Employee'), render: (_: any, r: any) => r.employee?.name || '-' },
        { key: 'competency', header: t('Competency'), render: (_: any, r: any) => r.competency?.name || '-' },
        { key: 'rating', header: t('Rating (0-5)'), sortable: true, render: (v: any) => `${v}/5 ★` },
        { key: 'assessment_date', header: t('Date'), sortable: true, render: (v: string) => formatDate(v) },
        { key: 'assessor', header: t('Assessor'), render: (_: any, r: any) => r.assessor?.name || '-' },
        {
            key: 'actions',
            header: t('Actions'),
            render: (_: any, item: any) => (
                <div className="flex gap-1">
                    <Button variant="ghost" size="sm" onClick={() => openEdit(item)} className="h-8 w-8 p-0 text-blue-600"><Edit className="h-4 w-4" /></Button>
                    <Button variant="ghost" size="sm" onClick={() => openDeleteDialog(item.id)} className="h-8 w-8 p-0 text-destructive"><Trash2 className="h-4 w-4" /></Button>
                </div>
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Performance') }, { label: t('Competency Assessments') }]}
            pageTitle={t('Employee Competency Assessments')}
            pageActions={<Button size="sm" onClick={openCreate}><Plus className="h-4 w-4 mr-1" /> {t('Assess Competency')}</Button>}
        >
            <Head title={t('Competency Assessments')} />
            <Card>
                <CardContent className="p-0">
                    <DataTable
                        data={assessments?.data || []}
                        columns={columns}
                        emptyState={<NoRecordsFound icon={Award} title={t('No competency assessments found')} description={t('Evaluate employees on behavioral competencies.')} onCreateClick={openCreate} createButtonText={t('Assess Competency')} />}
                    />
                </CardContent>
                <CardContent className="px-4 py-2 border-t">
                    <Pagination data={assessments || { data: [], links: [], meta: {} }} routeName="performance.employee-competencies.index" filters={{}} />
                </CardContent>
            </Card>

            <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader><DialogTitle>{editing ? t('Edit Assessment') : t('Assess Employee Competency')}</DialogTitle></DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label required>{t('Employee')}</Label>
                                <Select value={form.data.employee_id} onValueChange={(v) => form.setData('employee_id', v)} required>
                                    <SelectTrigger><SelectValue placeholder={t('Select employee')} /></SelectTrigger>
                                    <SelectContent searchable={true}>
                                        {(employees || []).map((e: any) => (<SelectItem key={e.id} value={e.id.toString()}>{e.name}</SelectItem>))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label required>{t('Competency')}</Label>
                                <Select value={form.data.competency_id} onValueChange={(v) => form.setData('competency_id', v)} required>
                                    <SelectTrigger><SelectValue placeholder={t('Select competency')} /></SelectTrigger>
                                    <SelectContent>
                                        {(competencies || []).map((c: any) => (<SelectItem key={c.id} value={c.id.toString()}>{c.name}</SelectItem>))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="grid grid-cols-3 gap-3">
                            <div>
                                <Label required>{t('Rating (0-5)')}</Label>
                                <Input type="number" min="0" max="5" step="0.5" value={form.data.rating} onChange={(e) => form.setData('rating', e.target.value)} required />
                            </div>
                            <div>
                                <Label required>{t('Date')}</Label>
                                <DatePicker value={form.data.assessment_date} onChange={(v) => form.setData('assessment_date', v)} />
                            </div>
                            <div>
                                <Label>{t('Review Cycle')}</Label>
                                <Select value={form.data.review_cycle_id} onValueChange={(v) => form.setData('review_cycle_id', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">{t('None')}</SelectItem>
                                        {(reviewCycles || []).map((c: any) => (<SelectItem key={c.id} value={c.id.toString()}>{c.name}</SelectItem>))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div>
                            <Label>{t('Assessor Comments')}</Label>
                            <RichTextEditor
                                variant="compact"
                                minHeight="100px"
                                content={form.data.comments}
                                onChange={(value) => form.setData('comments', value)}
                            />
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>{t('Cancel')}</Button>
                            <Button type="submit" disabled={form.processing}>{t('Save')}</Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmationDialog open={deleteState.isOpen} onOpenChange={closeDeleteDialog} title={t('Delete Assessment')} message={deleteState.message} confirmText={t('Delete')} onConfirm={confirmDelete} variant="destructive" />
        </AuthenticatedLayout>
    );
}
