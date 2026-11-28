import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useForm } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RichTextEditor } from "@/components/ui/rich-text-editor";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import InputError from "@/components/ui/input-error";

interface CreateProps {
    onSuccess: () => void;
    categories: any[];
    hrmContext?: any;
}

export default function Create({ onSuccess, categories = [], hrmContext }: CreateProps) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, transform } = useForm({
        category_id: '',
        department_id: 'none',
        designation_id: 'none',
        name: '',
        description: '',
        measurement_unit: '',
        target_value: '',
        weight: '0',
        goal_direction: 'higher_is_better',
        kpi_source: 'manual',
        status: 'active',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((form) => ({
            ...form,
            department_id: form.department_id === 'none' ? '' : form.department_id,
            designation_id: form.designation_id === 'none' ? '' : form.designation_id,
        }));
        post(route('performance.indicators.store'), {
            onSuccess: () => onSuccess(),
        });
    };

    return (
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{t('Create Performance Indicator')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label htmlFor="name" required>{t('Name')}</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        <InputError message={errors.name} />
                    </div>

                    <div>
                        <Label htmlFor="category_id" required>{t('Category')}</Label>
                        <Select value={data.category_id} onValueChange={(value) => setData('category_id', value)} required>
                            <SelectTrigger><SelectValue placeholder={t('Select category')} /></SelectTrigger>
                            <SelectContent>
                                {(categories || []).length > 0 ? (
                                    (categories || []).map((category) => (
                                        <SelectItem key={category.id} value={category.id.toString()}>{category.name}</SelectItem>
                                    ))
                                ) : (
                                    <SelectItem value="no-categories" disabled>{t('No categories available')}</SelectItem>
                                )}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.category_id} />
                    </div>
                </div>

                {hrmContext?.hrm_active && (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <Label>{t('Department (Optional)')}</Label>
                            <Select value={data.department_id} onValueChange={(v) => setData('department_id', v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">{t('Company-Wide')}</SelectItem>
                                    {(hrmContext?.departments || []).map((d: any) => (
                                        <SelectItem key={d.id} value={d.id.toString()}>{d.department_name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label>{t('Designation (Optional)')}</Label>
                            <Select value={data.designation_id} onValueChange={(v) => setData('designation_id', v)}>
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
                    <Label htmlFor="description">{t('Description')}</Label>
                    <RichTextEditor
                        id="description"
                        variant="compact"
                        minHeight="100px"
                        content={data.description}
                        onChange={(value) => setData('description', value)}
                    />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <Label htmlFor="measurement_unit" required>{t('Measurement Unit')}</Label>
                        <Input id="measurement_unit" value={data.measurement_unit} onChange={(e) => setData('measurement_unit', e.target.value)} placeholder="%, hours, ETB" required />
                        <InputError message={errors.measurement_unit} />
                    </div>
                    <div>
                        <Label htmlFor="target_value">{t('Target Value')}</Label>
                        <Input id="target_value" value={data.target_value} onChange={(e) => setData('target_value', e.target.value)} placeholder="90%" />
                    </div>
                    <div>
                        <Label htmlFor="weight">{t('Scoring Weight (%)')}</Label>
                        <Input id="weight" type="number" min="0" max="100" step="0.5" value={data.weight} onChange={(e) => setData('weight', e.target.value)} />
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label>{t('Optimization Direction')}</Label>
                        <Select value={data.goal_direction} onValueChange={(v) => setData('goal_direction', v)}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="higher_is_better">{t('Higher is Better')}</SelectItem>
                                <SelectItem value="lower_is_better">{t('Lower is Better')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label>{t('Status')}</Label>
                        <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">{t('Active')}</SelectItem>
                                <SelectItem value="inactive">{t('Inactive')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="flex justify-end gap-2 pt-4">
                    <Button type="button" variant="outline" onClick={onSuccess}>{t('Cancel')}</Button>
                    <Button type="submit" disabled={processing}>{processing ? t('Creating...') : t('Create')}</Button>
                </div>
            </form>
        </DialogContent>
    );
}
