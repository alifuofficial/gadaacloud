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
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Pagination } from '@/components/ui/pagination';
import { Plus, Edit, Trash2, FileText } from 'lucide-react';
import NoRecordsFound from '@/components/no-records-found';

export default function Index() {
    const { t } = useTranslation();
    const { templates, indicators = [], hrmContext } = usePage<any>().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<any>(null);

    const form = useForm({
        name: '',
        description: '',
        branch_id: 'none',
        department_id: 'none',
        designation_id: 'none',
        kpi_weight: '60',
        goal_weight: '20',
        competency_weight: '20',
        include_self_assessment: true,
        indicator_ids: [] as number[],
        status: 'active',
    });

    const { deleteState, openDeleteDialog, closeDeleteDialog, confirmDelete } = useDeleteHandler({
        routeName: 'performance.review-templates.destroy',
        defaultMessage: t('Are you sure you want to delete this review template?'),
    });

    const openCreate = () => {
        setEditing(null);
        form.setData({
            name: '',
            description: '',
            branch_id: 'none',
            department_id: 'none',
            designation_id: 'none',
            kpi_weight: '60',
            goal_weight: '20',
            competency_weight: '20',
            include_self_assessment: true,
            indicator_ids: [],
            status: 'active',
        });
        setModalOpen(true);
    };

    const openEdit = (item: any) => {
        setEditing(item);
        form.setData({
            name: item.name || '',
            description: item.description || '',
            branch_id: item.branch_id ? item.branch_id.toString() : 'none',
            department_id: item.department_id ? item.department_id.toString() : 'none',
            designation_id: item.designation_id ? item.designation_id.toString() : 'none',
            kpi_weight: item.kpi_weight?.toString() || '60',
            goal_weight: item.goal_weight?.toString() || '20',
            competency_weight: item.competency_weight?.toString() || '20',
            include_self_assessment: Boolean(item.include_self_assessment),
            indicator_ids: (item.indicators || []).map((i: any) => i.id),
            status: item.status || 'active',
        });
        setModalOpen(true);
    };

    const toggleIndicator = (id: number) => {
        const exists = form.data.indicator_ids.includes(id);
        form.setData(
            'indicator_ids',
            exists ? form.data.indicator_ids.filter((x) => x !== id) : [...form.data.indicator_ids, id]
        );
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((f) => ({
            ...f,
            branch_id: f.branch_id === 'none' ? '' : f.branch_id,
            department_id: f.department_id === 'none' ? '' : f.department_id,
            designation_id: f.designation_id === 'none' ? '' : f.designation_id,
        }));
        if (editing) {
            form.put(route('performance.review-templates.update', editing.id), { onSuccess: () => setModalOpen(false) });
        } else {
            form.post(route('performance.review-templates.store'), { onSuccess: () => setModalOpen(false) });
        }
    };

    const columns = [
        { key: 'name', header: t('Template Name'), sortable: true },
        { key: 'department', header: t('Department'), render: (_: any, r: any) => r.department?.department_name || t('All') },
        {
            key: 'weights',
            header: t('Weight Split (KPI/Goal/Comp)'),
            render: (_: any, r: any) => `${r.kpi_weight}% / ${r.goal_weight}% / ${r.competency_weight}%`,
        },
        { key: 'indicators', header: t('Indicators'), render: (v: any) => `${(v || []).length}` },
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
                    <Button variant="ghost" size="sm" onClick={() => openEdit(item)} className="h-8 w-8 p-0 text-blue-600"><Edit className="h-4 w-4" /></Button>
                    <Button variant="ghost" size="sm" onClick={() => openDeleteDialog(item.id)} className="h-8 w-8 p-0 text-destructive"><Trash2 className="h-4 w-4" /></Button>
                </div>
            ),
        },
    ];

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Performance') }, { label: t('Review Templates') }]}
            pageTitle={t('Appraisal Review Templates')}
            pageActions={<Button size="sm" onClick={openCreate}><Plus className="h-4 w-4 mr-1" /> {t('New Template')}</Button>}
        >
            <Head title={t('Review Templates')} />
            <Card>
                <CardContent className="p-0">
                    <DataTable
                        data={templates?.data || []}
                        columns={columns}
                        emptyState={<NoRecordsFound icon={FileText} title={t('No review templates defined')} description={t('Configure role-based weighted appraisal scorecards.')} onCreateClick={openCreate} createButtonText={t('New Template')} />}
                    />
                </CardContent>
                <CardContent className="px-4 py-2 border-t">
                    <Pagination data={templates || { data: [], links: [], meta: {} }} routeName="performance.review-templates.index" filters={{}} />
                </CardContent>
            </Card>

            <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader><DialogTitle>{editing ? t('Edit Review Template') : t('Create Review Template')}</DialogTitle></DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <Label required>{t('Template Name')}</Label>
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                        </div>
                        <div className="grid grid-cols-3 gap-3">
                            <div>
                                <Label>{t('KPI Weight (%)')}</Label>
                                <Input type="number" min="0" max="100" value={form.data.kpi_weight} onChange={(e) => form.setData('kpi_weight', e.target.value)} />
                            </div>
                            <div>
                                <Label>{t('Goal Weight (%)')}</Label>
                                <Input type="number" min="0" max="100" value={form.data.goal_weight} onChange={(e) => form.setData('goal_weight', e.target.value)} />
                            </div>
                            <div>
                                <Label>{t('Competency Weight (%)')}</Label>
                                <Input type="number" min="0" max="100" value={form.data.competency_weight} onChange={(e) => form.setData('competency_weight', e.target.value)} />
                            </div>
                        </div>
                        {hrmContext?.hrm_active && (
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label>{t('Department Scope')}</Label>
                                    <Select value={form.data.department_id} onValueChange={(v) => form.setData('department_id', v)}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">{t('All Departments')}</SelectItem>
                                            {(hrmContext?.departments || []).map((d: any) => (<SelectItem key={d.id} value={d.id.toString()}>{d.department_name}</SelectItem>))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <Label>{t('Designation Scope')}</Label>
                                    <Select value={form.data.designation_id} onValueChange={(v) => form.setData('designation_id', v)}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">{t('All Designations')}</SelectItem>
                                            {(hrmContext?.designations || []).map((d: any) => (<SelectItem key={d.id} value={d.id.toString()}>{d.designation_name}</SelectItem>))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        )}
                        <div>
                            <Label>{t('Included KPI Indicators')}</Label>
                            <div className="mt-1.5 max-h-40 overflow-y-auto border rounded-md p-2 space-y-1.5">
                                {(indicators || []).map((ind: any) => (
                                    <label key={ind.id} className="flex items-center gap-2 text-sm cursor-pointer">
                                        <Checkbox
                                            checked={form.data.indicator_ids.includes(ind.id)}
                                            onCheckedChange={() => toggleIndicator(ind.id)}
                                        />
                                        <span>{ind.name}</span>
                                    </label>
                                ))}
                            </div>
                        </div>
                        <label className="flex items-center gap-2 text-sm cursor-pointer">
                            <Checkbox checked={form.data.include_self_assessment} onCheckedChange={(v) => form.setData('include_self_assessment', Boolean(v))} />
                            {t('Require Employee Self-Assessment stage before manager evaluation')}
                        </label>
                        <div>
                            <Label>{t('Description')}</Label>
                            <RichTextEditor
                                variant="compact"
                                minHeight="100px"
                                content={form.data.description}
                                onChange={(value) => form.setData('description', value)}
                            />
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>{t('Cancel')}</Button>
                            <Button type="submit" disabled={form.processing}>{t('Save Template')}</Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmationDialog open={deleteState.isOpen} onOpenChange={closeDeleteDialog} title={t('Delete Template')} message={deleteState.message} confirmText={t('Delete')} onConfirm={confirmDelete} variant="destructive" />
        </AuthenticatedLayout>
    );
}
