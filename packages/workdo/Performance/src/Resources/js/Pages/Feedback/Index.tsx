import { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useDeleteHandler } from '@/hooks/useDeleteHandler';
import { formatDate, isRichTextEmpty } from '@/utils/helpers';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import InputError from '@/components/ui/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DatePicker } from '@/components/ui/date-picker';
import { Pagination } from '@/components/ui/pagination';
import { Plus, Edit, Trash2, MessageSquare } from 'lucide-react';
import NoRecordsFound from '@/components/no-records-found';

export default function Index() {
    const { t } = useTranslation();
    const { feedback, employees = [], feedbackTypes = [], reviewCycles = [] } = usePage<any>().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<any>(null);

    const form = useForm({
        employee_id: '',
        feedback_type_id: '',
        review_cycle_id: 'none',
        relationship_type: 'peer',
        date: new Date().toISOString().slice(0, 10),
        rating: '5',
        content: '',
        visibility: 'team',
        is_anonymous: false,
    });

    const { deleteState, openDeleteDialog, closeDeleteDialog, confirmDelete } = useDeleteHandler({
        routeName: 'performance.feedback.destroy',
        defaultMessage: t('Are you sure you want to delete this feedback?'),
    });

    const openCreate = () => {
        setEditing(null);
        form.setData({
            employee_id: '',
            feedback_type_id: (feedbackTypes || [])[0]?.id?.toString() || '',
            review_cycle_id: 'none',
            relationship_type: 'peer',
            date: new Date().toISOString().slice(0, 10),
            rating: '5',
            content: '',
            visibility: 'team',
            is_anonymous: false,
        });
        setModalOpen(true);
    };

    const openEdit = (item: any) => {
        setEditing(item);
        form.setData({
            employee_id: item.employee_id?.toString() || '',
            feedback_type_id: item.feedback_type_id?.toString() || '',
            review_cycle_id: item.review_cycle_id ? item.review_cycle_id.toString() : 'none',
            relationship_type: item.relationship_type || 'peer',
            date: item.date || '',
            rating: item.rating?.toString() || '5',
            content: item.content || '',
            visibility: item.visibility || 'team',
            is_anonymous: Boolean(item.is_anonymous),
        });
        setModalOpen(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isRichTextEmpty(form.data.content)) {
            form.setError('content', t('Feedback content is required.'));
            return;
        }
        form.transform((f) => ({
            ...f,
            review_cycle_id: f.review_cycle_id === 'none' ? '' : f.review_cycle_id,
        }));
        if (editing) {
            form.put(route('performance.feedback.update', editing.id), { onSuccess: () => setModalOpen(false) });
        } else {
            form.post(route('performance.feedback.store'), { onSuccess: () => setModalOpen(false) });
        }
    };

    const columns = [
        { key: 'employee', header: t('Recipient'), render: (_: any, r: any) => r.employee?.name || '-' },
        { key: 'giver', header: t('From'), render: (_: any, r: any) => r.is_anonymous ? t('Anonymous') : (r.giver?.name || '-') },
        { key: 'relationship_type', header: t('Relation') },
        { key: 'feedback_type', header: t('Type'), render: (_: any, r: any) => r.feedback_type?.name || '-' },
        { key: 'rating', header: t('Rating'), render: (v: any) => v ? `${v}/5 ★` : '-' },
        { key: 'visibility', header: t('Visibility') },
        { key: 'date', header: t('Date'), render: (v: string) => formatDate(v) },
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
            breadcrumbs={[{ label: t('Performance') }, { label: t('360° Feedback') }]}
            pageTitle={t('360° Continuous Feedback')}
            pageActions={<Button size="sm" onClick={openCreate}><Plus className="h-4 w-4 mr-1" /> {t('Give Feedback')}</Button>}
        >
            <Head title={t('360° Feedback')} />
            <Card>
                <CardContent className="p-0">
                    <DataTable
                        data={feedback?.data || []}
                        columns={columns}
                        emptyState={<NoRecordsFound icon={MessageSquare} title={t('No 360° feedback submitted')} description={t('Recognize peers or share constructive feedback across teams.')} onCreateClick={openCreate} createButtonText={t('Give Feedback')} />}
                    />
                </CardContent>
                <CardContent className="px-4 py-2 border-t">
                    <Pagination data={feedback || { data: [], links: [], meta: {} }} routeName="performance.feedback.index" filters={{}} />
                </CardContent>
            </Card>

            <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader><DialogTitle>{editing ? t('Edit Feedback') : t('Submit 360° Feedback')}</DialogTitle></DialogHeader>
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
                                <Label required>{t('Feedback Type')}</Label>
                                <Select value={form.data.feedback_type_id} onValueChange={(v) => form.setData('feedback_type_id', v)} required>
                                    <SelectTrigger><SelectValue placeholder={t('Select type')} /></SelectTrigger>
                                    <SelectContent>
                                        {(feedbackTypes || []).map((ft: any) => (<SelectItem key={ft.id} value={ft.id.toString()}>{ft.name}</SelectItem>))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="grid grid-cols-3 gap-3">
                            <div>
                                <Label>{t('Relationship')}</Label>
                                <Select value={form.data.relationship_type} onValueChange={(v) => form.setData('relationship_type', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="peer">{t('Peer')}</SelectItem>
                                        <SelectItem value="manager">{t('Manager')}</SelectItem>
                                        <SelectItem value="subordinate">{t('Direct Report')}</SelectItem>
                                        <SelectItem value="client">{t('Internal Client')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label>{t('Visibility')}</Label>
                                <Select value={form.data.visibility} onValueChange={(v) => form.setData('visibility', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="private">{t('Private (HR/Manager)')}</SelectItem>
                                        <SelectItem value="team">{t('Employee & Manager')}</SelectItem>
                                        <SelectItem value="public">{t('Public Recognition')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label>{t('Rating (1-5)')}</Label>
                                <Input type="number" min="1" max="5" value={form.data.rating} onChange={(e) => form.setData('rating', e.target.value)} />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label required>{t('Date')}</Label>
                                <DatePicker value={form.data.date} onChange={(v) => form.setData('date', v)} />
                            </div>
                            <div>
                                <Label>{t('Review Cycle')}</Label>
                                <Select value={form.data.review_cycle_id} onValueChange={(v) => form.setData('review_cycle_id', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">{t('Continuous (Unlinked)')}</SelectItem>
                                        {(reviewCycles || []).map((c: any) => (<SelectItem key={c.id} value={c.id.toString()}>{c.name}</SelectItem>))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div>
                            <Label required>{t('Feedback')}</Label>
                            <RichTextEditor
                                variant="compact"
                                minHeight="110px"
                                content={form.data.content}
                                onChange={(value) => {
                                    form.setData('content', value);
                                    if (!isRichTextEmpty(value)) form.clearErrors('content');
                                }}
                            />
                            <InputError message={form.errors.content} />
                        </div>
                        <label className="flex items-center gap-2 text-sm cursor-pointer">
                            <Checkbox checked={form.data.is_anonymous} onCheckedChange={(v) => form.setData('is_anonymous', Boolean(v))} />
                            {t('Hide my identity from recipient (Anonymous 360)')}
                        </label>
                        <div className="flex justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>{t('Cancel')}</Button>
                            <Button type="submit" disabled={form.processing}>{t('Submit')}</Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmationDialog open={deleteState.isOpen} onOpenChange={closeDeleteDialog} title={t('Delete Feedback')} message={deleteState.message} confirmText={t('Delete')} onConfirm={confirmDelete} variant="destructive" />
        </AuthenticatedLayout>
    );
}
