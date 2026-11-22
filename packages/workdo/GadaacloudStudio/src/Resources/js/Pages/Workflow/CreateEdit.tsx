import { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Plus, Trash2, ArrowLeft, GitCommit, Shield, User, ChevronUp, ChevronDown, SlidersHorizontal, AlertCircle } from 'lucide-react';

interface RoleOption {
    id: number;
    name: string;
    label: string;
}

interface UserOption {
    id: number;
    name: string;
    email: string;
}

interface ModelOption {
    class: string;
    name: string;
    group: string;
    exists: boolean;
}

interface StepForm {
    id?: number;
    name: string;
    approver_type: 'role' | 'user';
    approver_role: string;
    approver_user_id: string;
    condition_field?: string;
    condition_operator?: 'gt' | 'gte' | 'lt' | 'lte' | 'eq' | 'neq';
    condition_value?: string;
    skip_if_condition_fails?: boolean;
    show_condition?: boolean;
}

interface WorkflowData {
    id: number;
    name: string;
    target_model: string;
    description: string | null;
    steps: {
        id: number;
        name: string;
        approver_type: 'role' | 'user';
        approver_role: string | null;
        approver_user_id: number | null;
        sequence: number;
        condition_field?: string | null;
        condition_operator?: 'gt' | 'gte' | 'lt' | 'lte' | 'eq' | 'neq' | null;
        condition_value?: string | null;
        skip_if_condition_fails?: boolean | null;
    }[];
}

interface CreateEditProps {
    roles: RoleOption[];
    users: UserOption[];
    targetModels: ModelOption[];
    workflow: WorkflowData | null;
}

export default function CreateEdit({ roles, users, targetModels, workflow }: CreateEditProps) {
    const { t } = useTranslation();
    const isEdit = !!workflow;

    const [name, setName] = useState('');
    const [targetModel, setTargetModel] = useState('');
    const [description, setDescription] = useState('');
    const [steps, setSteps] = useState<StepForm[]>([
        {
            name: t('First Stage Review'),
            approver_type: 'role',
            approver_role: '',
            approver_user_id: '',
            condition_field: '',
            condition_operator: 'gte',
            condition_value: '',
            skip_if_condition_fails: false,
            show_condition: false,
        }
    ]);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (workflow) {
            setName(workflow.name);
            setTargetModel(workflow.target_model);
            setDescription(workflow.description || '');
            setSteps(workflow.steps.map(s => ({
                id: s.id,
                name: s.name,
                approver_type: s.approver_type,
                approver_role: s.approver_role || '',
                approver_user_id: s.approver_user_id ? String(s.approver_user_id) : '',
                condition_field: s.condition_field || '',
                condition_operator: s.condition_operator || 'gte',
                condition_value: s.condition_value !== null && s.condition_value !== undefined ? String(s.condition_value) : '',
                skip_if_condition_fails: !!s.skip_if_condition_fails,
                show_condition: !!(s.condition_field || s.condition_value),
            })));
        }
    }, [workflow]);

    const addStep = () => {
        setSteps([
            ...steps,
            {
                name: '',
                approver_type: 'role',
                approver_role: '',
                approver_user_id: '',
                condition_field: '',
                condition_operator: 'gte',
                condition_value: '',
                skip_if_condition_fails: false,
                show_condition: false,
            }
        ]);
    };

    const removeStep = (index: number) => {
        if (steps.length === 1) return;
        setSteps(steps.filter((_, i) => i !== index));
    };

    const handleStepChange = (index: number, key: keyof StepForm, value: any) => {
        const newSteps = [...steps];
        newSteps[index] = { ...newSteps[index], [key]: value };
        setSteps(newSteps);
    };

    const moveStep = (index: number, direction: 'up' | 'down') => {
        if (direction === 'up' && index === 0) return;
        if (direction === 'down' && index === steps.length - 1) return;

        const newIndex = direction === 'up' ? index - 1 : index + 1;
        const newSteps = [...steps];
        const temp = newSteps[index];
        newSteps[index] = newSteps[newIndex];
        newSteps[newIndex] = temp;
        setSteps(newSteps);
    };

    const validateForm = () => {
        const errs: Record<string, string> = {};
        if (!name.trim()) errs.name = t('Workflow name is required');
        if (!targetModel) errs.target_model = t('Target document model is required');

        steps.forEach((step, idx) => {
            if (!step.name.trim()) errs[`step_${idx}_name`] = t('Step name is required');
            if (step.approver_type === 'role' && !step.approver_role) {
                errs[`step_${idx}_approver`] = t('Role is required');
            }
            if (step.approver_type === 'user' && !step.approver_user_id) {
                errs[`step_${idx}_approver`] = t('User is required');
            }
        });

        setErrors(errs);
        return Object.keys(errs).length === 0;
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!validateForm()) return;

        const data = {
            name,
            target_model: targetModel,
            description,
            steps: steps.map(s => ({
                id: s.id ?? null,
                name: s.name,
                approver_type: s.approver_type,
                approver_role: s.approver_type === 'role' ? s.approver_role : null,
                approver_user_id: s.approver_type === 'user' ? parseInt(s.approver_user_id) : null,
                condition_field: s.condition_field?.trim() || null,
                condition_operator: s.condition_field?.trim() ? (s.condition_operator || 'gte') : null,
                condition_value: s.condition_field?.trim() ? (s.condition_value ?? null) : null,
                skip_if_condition_fails: !!s.skip_if_condition_fails,
            }))
        };

        if (isEdit) {
            router.post(route('gadaacloud-studio.workflows.update', workflow.id), data);
        } else {
            router.post(route('gadaacloud-studio.workflows.store'), data);
        }
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('GadaaCloud Studio'), url: route('gadaacloud-studio.workflows.index') },
                { label: isEdit ? t('Edit Workflow') : t('Create Workflow') }
            ]}
            pageTitle={isEdit ? t('Modify Approval Path') : t('Build Workflow Path')}
        >
            <Head title={isEdit ? t('Edit Workflow') : t('Create Workflow')} />

            <div className="max-w-4xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <Button asChild variant="outline" size="sm" className="gap-1 border-gray-200">
                        <Link href={route('gadaacloud-studio.workflows.index')}>
                            <ArrowLeft className="h-4 w-4" />
                            {t('Back to list')}
                        </Link>
                    </Button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Settings */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base font-semibold">{t('General Information')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="name">{t('Workflow Name')}</Label>
                                    <Input
                                        id="name"
                                        value={name}
                                        onChange={(e) => setName(e.target.value)}
                                        placeholder={t('e.g., Executive Payment Clearance')}
                                    />
                                    {errors.name && <p className="text-xs text-red-500">{errors.name}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="target_model">{t('Target Document')}</Label>
                                    <Select value={targetModel} onValueChange={setTargetModel}>
                                        <SelectTrigger id="target_model">
                                            <SelectValue placeholder={t('Select target document...')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {targetModels.map((m) => (
                                                <SelectItem key={m.class} value={m.class}>
                                                    {t(m.name)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.target_model && <p className="text-xs text-red-500">{errors.target_model}</p>}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">{t('Description')}</Label>
                                <Textarea
                                    id="description"
                                    value={description}
                                    onChange={(e) => setDescription(e.target.value)}
                                    placeholder={t('Explain the scope of this approval process...')}
                                    rows={3}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Step Builder */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between border-b border-gray-100/60 pb-5">
                            <div>
                                <CardTitle className="text-base font-semibold">{t('Stages Flow')}</CardTitle>
                                <p className="text-xs text-muted-foreground mt-0.5">{t('Add and reorder stages for the sequential approvals.')}</p>
                            </div>
                            <Button type="button" size="sm" variant="outline" onClick={addStep} className="gap-1 border-gray-200">
                                <Plus className="h-4 w-4" />
                                {t('Add Stage')}
                            </Button>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="divide-y divide-gray-100">
                                {steps.map((step, idx) => (
                                    <div key={idx} className="p-5 space-y-4 hover:bg-gray-50/20 transition-colors">
                                        <div className="flex items-start gap-4">
                                            {/* Step Indicator */}
                                            <div className="flex flex-col items-center justify-center shrink-0">
                                                <div className="w-8 h-8 rounded-full bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 font-bold text-xs flex items-center justify-center border border-indigo-100">
                                                    {idx + 1}
                                                </div>
                                                <div className="flex flex-col items-center mt-1">
                                                    <button type="button" disabled={idx === 0} onClick={() => moveStep(idx, 'up')} className="p-0.5 text-gray-400 hover:text-gray-600 disabled:opacity-30">
                                                        <ChevronUp className="h-3.5 w-3.5" />
                                                    </button>
                                                    <button type="button" disabled={idx === steps.length - 1} onClick={() => moveStep(idx, 'down')} className="p-0.5 text-gray-400 hover:text-gray-600 disabled:opacity-30">
                                                        <ChevronDown className="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                            </div>

                                            {/* Step Fields */}
                                            <div className="flex-1 grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <div className="space-y-1.5 col-span-1">
                                                    <Label className="text-xs">{t('Stage Name')}</Label>
                                                    <Input
                                                        value={step.name}
                                                        onChange={(e) => handleStepChange(idx, 'name', e.target.value)}
                                                        placeholder={t('e.g., Review, Final Approval')}
                                                        className="h-9"
                                                    />
                                                    {errors[`step_${idx}_name`] && <p className="text-[10px] text-red-500">{errors[`step_${idx}_name`]}</p>}
                                                </div>

                                                <div className="space-y-1.5 col-span-1">
                                                    <Label className="text-xs">{t('Approver Type')}</Label>
                                                    <Select
                                                        value={step.approver_type}
                                                        onValueChange={(val: 'role' | 'user') => handleStepChange(idx, 'approver_type', val)}
                                                    >
                                                        <SelectTrigger className="h-9">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="role">🔑 {t('Spatie Role')}</SelectItem>
                                                            <SelectItem value="user">👤 {t('Specific User')}</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>

                                                <div className="space-y-1.5 col-span-1">
                                                    <Label className="text-xs">{t('Assigned Approver')}</Label>
                                                    {step.approver_type === 'role' ? (
                                                        <Select
                                                            value={step.approver_role}
                                                            onValueChange={(val) => handleStepChange(idx, 'approver_role', val)}
                                                        >
                                                            <SelectTrigger className="h-9">
                                                                <SelectValue placeholder={t('Choose Role...')} />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {roles.map((r) => (
                                                                    <SelectItem key={r.name} value={r.name}>
                                                                        {r.label}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    ) : (
                                                        <Select
                                                            value={step.approver_user_id}
                                                            onValueChange={(val) => handleStepChange(idx, 'approver_user_id', val)}
                                                        >
                                                            <SelectTrigger className="h-9">
                                                                <SelectValue placeholder={t('Choose User...')} />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {users.map((u) => (
                                                                    <SelectItem key={u.id} value={String(u.id)}>
                                                                        {u.name}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    )}
                                                    {errors[`step_${idx}_approver`] && <p className="text-[10px] text-red-500">{errors[`step_${idx}_approver`]}</p>}
                                                </div>
                                            </div>

                                            {/* Actions */}
                                            <div className="flex items-center gap-1 shrink-0 self-center">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => handleStepChange(idx, 'show_condition', !step.show_condition)}
                                                    className={`h-8 w-8 ${step.show_condition || step.condition_field ? 'text-indigo-600 bg-indigo-50' : 'text-gray-400 hover:text-gray-600'}`}
                                                    title={t('Conditional Rule')}
                                                >
                                                    <SlidersHorizontal className="h-4 w-4" />
                                                </Button>

                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    disabled={steps.length === 1}
                                                    onClick={() => removeStep(idx)}
                                                    className="h-8 w-8 hover:bg-red-50 text-gray-400 hover:text-red-500"
                                                    title={t('Delete Stage')}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>

                                        {/* Collapsible Conditional Threshold Form */}
                                        {(step.show_condition || step.condition_field) && (
                                            <div className="ml-12 p-3.5 bg-indigo-50/40 rounded-xl border border-indigo-100/70 space-y-3">
                                                <div className="flex items-center gap-2 text-xs font-semibold text-indigo-800">
                                                    <SlidersHorizontal className="h-3.5 w-3.5 text-indigo-600" />
                                                    <span>{t('Condition Threshold Rule (Optional)')}</span>
                                                </div>

                                                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                    <div className="space-y-1">
                                                        <Label className="text-[11px] text-gray-600">{t('Field Name')}</Label>
                                                        <Input
                                                            value={step.condition_field || ''}
                                                            onChange={(e) => handleStepChange(idx, 'condition_field', e.target.value)}
                                                            placeholder={t('e.g., total_amount, amount, quantity')}
                                                            className="h-8 text-xs bg-white"
                                                        />
                                                    </div>

                                                    <div className="space-y-1">
                                                        <Label className="text-[11px] text-gray-600">{t('Operator')}</Label>
                                                        <Select
                                                            value={step.condition_operator || 'gte'}
                                                            onValueChange={(val) => handleStepChange(idx, 'condition_operator', val)}
                                                        >
                                                            <SelectTrigger className="h-8 text-xs bg-white">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="gte">≥ {t('Greater or Equal')}</SelectItem>
                                                                <SelectItem value="gt">&gt; {t('Greater Than')}</SelectItem>
                                                                <SelectItem value="lte">≤ {t('Less or Equal')}</SelectItem>
                                                                <SelectItem value="lt">&lt; {t('Less Than')}</SelectItem>
                                                                <SelectItem value="eq">= {t('Equals')}</SelectItem>
                                                                <SelectItem value="neq">≠ {t('Not Equal')}</SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>

                                                    <div className="space-y-1">
                                                        <Label className="text-[11px] text-gray-600">{t('Threshold Value')}</Label>
                                                        <Input
                                                            value={step.condition_value || ''}
                                                            onChange={(e) => handleStepChange(idx, 'condition_value', e.target.value)}
                                                            placeholder={t('e.g., 50000')}
                                                            className="h-8 text-xs bg-white"
                                                        />
                                                    </div>
                                                </div>

                                                <div className="flex items-center justify-between pt-1 border-t border-indigo-100/50">
                                                    <div className="flex items-center gap-2">
                                                        <Switch
                                                            checked={!!step.skip_if_condition_fails}
                                                            onCheckedChange={(val) => handleStepChange(idx, 'skip_if_condition_fails', val)}
                                                        />
                                                        <span className="text-xs text-gray-600">
                                                            {t('Auto-skip this stage if document value does not meet the condition')}
                                                        </span>
                                                    </div>
                                                    {step.condition_field && (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                handleStepChange(idx, 'condition_field', '');
                                                                handleStepChange(idx, 'condition_value', '');
                                                                handleStepChange(idx, 'skip_if_condition_fails', false);
                                                                handleStepChange(idx, 'show_condition', false);
                                                            }}
                                                            className="text-xs text-red-500 hover:underline"
                                                        >
                                                            {t('Clear Condition')}
                                                        </button>
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-3 pt-2">
                        <Button asChild variant="outline" className="border-gray-200">
                            <Link href={route('gadaacloud-studio.workflows.index')}>{t('Cancel')}</Link>
                        </Button>
                        <Button type="submit" className="shadow-md">
                            {isEdit ? t('Save Workflow') : t('Launch Workflow')}
                        </Button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
