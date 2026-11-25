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
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Pagination } from '@/components/ui/pagination';
import { Plus, Edit, Trash2, Award } from 'lucide-react';
import NoRecordsFound from '@/components/no-records-found';

export default function Index() {
    const { t } = useTranslation();
    const { competencies, categories = [], hrmContext, auth } = usePage<any>().props;
    const permissions: string[] = auth?.user?.permissions || [];

    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<any>(null);

    const form = useForm({
        category_id: '',
        department_id: 'none',
        designation_id: 'none',
        name: '',
        description: '',
        weight: '0',
        status: 'active',
    });

    const { deleteState, openDeleteDialog, closeDeleteDialog, confirmDelete } = useDeleteHandler({
        routeName: 'performance.competencies.destroy',
        defaultMessage: t('Are you sure you want to delete this competency?'),
    });

    const openCreate = () => {
        setEditing(null);
        form.reset();
        form.setData({
            category_id: (categories || [])[0]?.id?.toString() || '',
            department_id: 'none',
            designation_id: 'none',
            name: '',
            description: '',
            weight: '0',
            status: 'active',
        });
        setModalOpen(true);
    };

    const openEdit = (item: any) => {
        setEditing(item);
        form.setData({
            category_id: item.category_id?.toString() || '',
            department_id: item.department_id ? item.department_id.toString() : 'none',
            designation_id: item.designation_id ? item.designation_id.toString() : 'none',
            name: item.name || '',
            description: item.description || '',
            weight: item.weight?.toString() || '0',
            status: item.status || 'active',
        });
        setModalOpen(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((f) => ({
            ...f,
            department_id: f.department_id === 'none' ? '' : f.department_id,
            designation_id: f.designation_id === 'none' ? '' : f.designation_id,
        }));
        if (editing) {
            form.put(route('performance.competencies.update', editing.id), { onSuccess: () => setModalOpen(false) });
        } else {
            form.post(route('performance.competencies.store'), { onSuccess: () => setModalOpen(false) });
        }
    };

    const columns = [
        { key: 'name', header: t('Name'), sortable: true },
        { key: 'category', header: t('Category'), render: (_: any, item: any) => item.category?.name || '-' },
        { key: 'weight', header: t('Weight (%)'), render: (v: any) => `${v ?? 0}%` },
        {
            key: 'status',
            header: t('Status'),
            render: (v: string) => (
                <span className={`px-2 py-1 rounded-full text-xs ${v === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                    {v === 'active' ? t('Active') : t('Inactive')}
                </span>
            ),
        },
        {
            key: 'actions',
            header: t('Actions'),
            render: (_: any, item: any) => (
                <div className="flex gap-1">
                    {(permissions.includes('edit-competencies') || permissions.includes('manage-competencies')) && (
                        <Button variant="ghost" size="sm" onClick={() => openEdit(item)} className="h-8 w-8 p-0 text-blue-600">
                            <Edit className="h-4 w-4" />
                        </Button>
                    )}
                    {(permissions.includes('delete-competencies') || permissions.includes('manage-competencies')) && (
                        <Button variant="ghost" size="sm" onClick={() => openDeleteDialog(item.id)} className="h-8 w-8 p-0 text-destructive">
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Performance') }, { label: t('Competencies') }]}
            pageTitle={t('Behavioral Competencies')}
            pageActions={
                <Button size="sm" onClick={openCreate}>
                    <Plus className="h-4 w-4 mr-1" /> {t('Add Competency')}
                </Button>
            }
        >
            <Head title={t('Competencies')} />

            <Card>
                <CardContent className="p-0">
                    <DataTable
                        data={competencies?.data || []}
                        columns={columns}
                        emptyState={
                            <NoRecordsFound
                                icon={Award}
                                title={t('No competencies defined')}
                                description={t('Define behavioral and leadership competencies for appraisals.')}
                                onCreateClick={openCreate}
                                createButtonText={t('Add Competency')}
                            />
                        }
                    />
                </CardContent>
                <CardContent className="px-4 py-2 border-t">
                    <Pagination data={competencies || { data: [], links: [], meta: {} }} routeName="performance.competencies.index" filters={{}} />
                </CardContent>
            </Card>

            <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editing ? t('Edit Competency') : t('Create Competency')}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <Label required>{t('Name')}</Label>
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label required>{t('Category')}</Label>
                                <Select value={form.data.category_id} onValueChange={(v) => form.setData('category_id', v)} required>
                                    <SelectTrigger><SelectValue placeholder={t('Select category')} /></SelectTrigger>
                                    <SelectContent>
                                        {(categories || []).map((c: any) => (
                                            <SelectItem key={c.id} value={c.id.toString()}>{c.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label>{t('Weight (%)')}</Label>
                                <Input type="number" min="0" max="100" step="0.5" value={form.data.weight} onChange={(e) => form.setData('weight', e.target.value)} />
                            </div>
                        </div>
                        {hrmContext?.hrm_active && (
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label>{t('Department')}</Label>
                                    <Select value={form.data.department_id} onValueChange={(v) => form.setData('department_id', v)}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">{t('All Departments')}</SelectItem>
                                            {(hrmContext?.departments || []).map((d: any) => (
                                                <SelectItem key={d.id} value={d.id.toString()}>{d.department_name}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <Label>{t('Designation')}</Label>
                                    <Select value={form.data.designation_id} onValueChange={(v) => form.setData('designation_id', v)}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">{t('All Roles')}</SelectItem>
                                            {(hrmContext?.designations || []).map((d: any) => (
                                                <SelectItem key={d.id} value={d.id.toString()}>{d.designation_name}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        )}
                        <div>
                            <Label>{t('Description')}</Label>
                            <Textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} rows={2} />
                        </div>
                        <div>
                            <Label>{t('Status')}</Label>
                            <Select value={form.data.status} onValueChange={(v) => form.setData('status', v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active">{t('Active')}</SelectItem>
                                    <SelectItem value="inactive">{t('Inactive')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>{t('Cancel')}</Button>
                            <Button type="submit" disabled={form.processing}>{t('Save')}</Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmationDialog
                open={deleteState.isOpen}
                onOpenChange={closeDeleteDialog}
                title={t('Delete Competency')}
                message={deleteState.message}
                confirmText={t('Delete')}
                onConfirm={confirmDelete}
                variant="destructive"
            />
        </AuthenticatedLayout>
    );
}
