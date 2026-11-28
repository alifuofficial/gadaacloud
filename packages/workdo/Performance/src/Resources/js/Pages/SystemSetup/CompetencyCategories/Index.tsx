import { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useDeleteHandler } from '@/hooks/useDeleteHandler';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import { stripHtml } from '@/utils/helpers';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Plus, Edit, Trash2, Award } from 'lucide-react';
import NoRecordsFound from '@/components/no-records-found';
import SystemSetupSidebar from '../SystemSetupSidebar';

export default function Index() {
    const { t } = useTranslation();
    const { categories = [] } = usePage<any>().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<any>(null);

    const form = useForm({ name: '', description: '', status: 'active' });
    const { deleteState, openDeleteDialog, closeDeleteDialog, confirmDelete } = useDeleteHandler({
        routeName: 'performance.competency-categories.destroy',
        defaultMessage: t('Are you sure you want to delete this competency category?'),
    });

    const openCreate = () => { setEditing(null); form.setData({ name: '', description: '', status: 'active' }); setModalOpen(true); };
    const openEdit = (item: any) => { setEditing(item); form.setData({ name: item.name, description: item.description || '', status: item.status || 'active' }); setModalOpen(true); };
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editing) form.put(route('performance.competency-categories.update', editing.id), { onSuccess: () => setModalOpen(false) });
        else form.post(route('performance.competency-categories.store'), { onSuccess: () => setModalOpen(false) });
    };

    const columns = [
        { key: 'name', header: t('Name') },
        { key: 'description', header: t('Description'), render: (v: string) => stripHtml(v || '') || '-' },
        { key: 'competencies_count', header: t('Competencies'), render: (v: any) => v ?? 0 },
        {
            key: 'status',
            header: t('Status'),
            render: (v: string) => <span className={`px-2 py-1 rounded-full text-xs ${v === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>{v}</span>,
        },
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
        <AuthenticatedLayout breadcrumbs={[{ label: t('Performance') }, { label: t('System Setup') }, { label: t('Competency Categories') }]} pageTitle={t('Competency Categories')}>
            <Head title={t('Competency Categories')} />
            <div className="flex flex-col md:flex-row gap-6">
                <div className="md:w-64 flex-shrink-0"><SystemSetupSidebar activeItem="competency-categories" /></div>
                <div className="flex-1">
                    <Card>
                        <div className="p-4 border-b flex justify-between items-center">
                            <h3 className="font-semibold">{t('Competency Categories')}</h3>
                            <Button size="sm" onClick={openCreate}><Plus className="h-4 w-4 mr-1" /> {t('Add')}</Button>
                        </div>
                        <CardContent className="p-0">
                            <DataTable data={categories || []} columns={columns} emptyState={<NoRecordsFound icon={Award} title={t('No competency categories')} description={t('Organize competencies into categories.')} onCreateClick={openCreate} />} />
                        </CardContent>
                    </Card>
                </div>
            </div>
            <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader><DialogTitle>{editing ? t('Edit Competency Category') : t('Create Competency Category')}</DialogTitle></DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div><Label required>{t('Name')}</Label><Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required /></div>
                        <div><Label>{t('Description')}</Label><RichTextEditor variant="compact" minHeight="90px" content={form.data.description} onChange={(value) => form.setData('description', value)} /></div>
                        <div>
                            <Label>{t('Status')}</Label>
                            <Select value={form.data.status} onValueChange={(v) => form.setData('status', v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent><SelectItem value="active">{t('Active')}</SelectItem><SelectItem value="inactive">{t('Inactive')}</SelectItem></SelectContent>
                            </Select>
                        </div>
                        <div className="flex justify-end gap-2"><Button type="button" variant="outline" onClick={() => setModalOpen(false)}>{t('Cancel')}</Button><Button type="submit" disabled={form.processing}>{t('Save')}</Button></div>
                    </form>
                </DialogContent>
            </Dialog>
            <ConfirmationDialog open={deleteState.isOpen} onOpenChange={closeDeleteDialog} title={t('Delete Category')} message={deleteState.message} confirmText={t('Delete')} onConfirm={confirmDelete} variant="destructive" />
        </AuthenticatedLayout>
    );
}
