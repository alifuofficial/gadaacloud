import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import {
    ArrowLeft, GitBranch, Receipt, ShoppingCart, Calendar,
    FileText, CreditCard, Package, FileCheck, Users,
    ArrowRight, Shield, ChevronRight, Copy, Check
} from 'lucide-react';

interface TemplateStep {
    name: string;
    approver_type: 'role' | 'user';
    approver_role?: string;
    condition_field?: string;
    condition_operator?: string;
    condition_value?: string;
    skip_if_condition_fails?: boolean;
}

interface Template {
    id: string;
    name: string;
    description: string;
    icon: string;
    target_model: string;
    steps: TemplateStep[];
}

interface ModelOption {
    class: string;
    name: string;
    group: string;
    exists: boolean;
}

interface RoleOption {
    id: number;
    name: string;
    label: string;
}

interface TemplatesProps {
    templates: Template[];
    targetModels: ModelOption[];
    roles: RoleOption[];
}

const ICON_MAP: Record<string, React.ReactNode> = {
    'receipt':       <Receipt className="h-6 w-6" />,
    'shopping-cart': <ShoppingCart className="h-6 w-6" />,
    'calendar':      <Calendar className="h-6 w-6" />,
    'file-text':     <FileText className="h-6 w-6" />,
    'credit-card':   <CreditCard className="h-6 w-6" />,
    'package':       <Package className="h-6 w-6" />,
    'file-check':    <FileCheck className="h-6 w-6" />,
    'users':         <Users className="h-6 w-6" />,
};

const TEMPLATE_GRADIENTS: Record<number, string> = {
    0: 'from-blue-500 to-blue-600',
    1: 'from-indigo-500 to-indigo-600',
    2: 'from-emerald-500 to-emerald-600',
    3: 'from-purple-500 to-purple-600',
    4: 'from-amber-500 to-amber-600',
    5: 'from-teal-500 to-teal-600',
    6: 'from-rose-500 to-rose-600',
    7: 'from-cyan-500 to-cyan-600',
};

export default function Templates({ templates, targetModels, roles }: TemplatesProps) {
    const { t } = useTranslation();
    const [selectedTemplate, setSelectedTemplate] = useState<Template | null>(null);
    const [customName, setCustomName] = useState('');
    const [customModel, setCustomModel] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [clonedId, setClonedId] = useState<string | null>(null);

    const availableModels = targetModels.filter(m => m.exists || true); // show all, greyed if not exists

    const handleClone = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedTemplate) return;
        setIsSubmitting(true);
        router.post(route('gadaacloud-studio.templates.clone'), {
            template_id: selectedTemplate.id,
            name: customName,
            target_model: customModel,
        }, {
            onSuccess: () => {
                setClonedId(selectedTemplate.id);
                setIsSubmitting(false);
                setSelectedTemplate(null);
            },
            onError: () => setIsSubmitting(false),
        });
    };

    const openClone = (tpl: Template) => {
        setSelectedTemplate(tpl);
        setCustomName(tpl.name);
        setCustomModel(tpl.target_model);
    };

    const getConditionLabel = (step: TemplateStep) => {
        if (!step.condition_field) return null;
        const OPS: Record<string, string> = { gt: '>', gte: '≥', lt: '<', lte: '≤', eq: '=', neq: '≠' };
        return `${step.condition_field} ${OPS[step.condition_operator ?? ''] ?? ''} ${step.condition_value}`;
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('GadaaCloud Studio'), url: route('gadaacloud-studio.dashboard') },
                { label: t('Workflow Templates') }
            ]}
            pageTitle={t('Templates Library')}
        >
            <Head title={t('Workflow Templates')} />

            <div className="flex items-center justify-between mb-6">
                <div>
                    <h2 className="text-lg font-bold text-gray-900">{t('Pre-built Workflow Templates')}</h2>
                    <p className="text-sm text-muted-foreground mt-0.5">
                        {t('Clone any template and customize it to fit your company\'s approval process.')}
                    </p>
                </div>
                <Button asChild variant="outline" size="sm" className="gap-1 border-gray-200">
                    <Link href={route('gadaacloud-studio.workflows.index')}>
                        <ArrowLeft className="h-4 w-4" />
                        {t('Back to Workflows')}
                    </Link>
                </Button>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
                {templates.map((tpl, idx) => (
                    <Card key={tpl.id} className="group overflow-hidden hover:shadow-lg transition-all duration-300 hover:-translate-y-0.5 border-gray-200">
                        {/* Colored Header */}
                        <div className={`bg-gradient-to-br ${TEMPLATE_GRADIENTS[idx % 8]} p-5 text-white`}>
                            <div className="flex items-start justify-between">
                                <div className="w-11 h-11 rounded-xl bg-white/20 flex items-center justify-center">
                                    {ICON_MAP[tpl.icon] ?? <GitBranch className="h-6 w-6" />}
                                </div>
                                {clonedId === tpl.id && (
                                    <span className="flex items-center gap-1 text-xs bg-white/20 px-2 py-1 rounded-full font-semibold">
                                        <Check className="h-3 w-3" />
                                        {t('Cloned')}
                                    </span>
                                )}
                            </div>
                            <h3 className="font-bold text-base mt-3 leading-tight">{t(tpl.name)}</h3>
                        </div>

                        <CardContent className="p-5 space-y-4">
                            <p className="text-sm text-muted-foreground leading-relaxed">{t(tpl.description)}</p>

                            {/* Steps Preview */}
                            <div>
                                <div className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">
                                    {t('Approval Stages')} ({tpl.steps.length})
                                </div>
                                <div className="flex flex-wrap items-center gap-1.5">
                                    {tpl.steps.map((step, i) => (
                                        <div key={i} className="flex items-center gap-1">
                                            <div className="flex items-center gap-1 bg-gray-100 px-2 py-1 rounded-lg text-xs font-medium text-gray-700">
                                                <Shield className="h-3 w-3 text-indigo-400" />
                                                <span>{step.name}</span>
                                                {getConditionLabel(step) && (
                                                    <span className="text-[9px] text-amber-600 bg-amber-50 px-1 rounded">
                                                        {t('conditional')}
                                                    </span>
                                                )}
                                            </div>
                                            {i < tpl.steps.length - 1 && (
                                                <ChevronRight className="h-3 w-3 text-gray-300" />
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Default Target Model */}
                            <div className="flex items-center justify-between pt-1 border-t border-gray-100">
                                <span className="text-xs text-gray-400 font-mono bg-gray-50 px-2 py-0.5 rounded">
                                    {tpl.target_model.split('\\').pop()}
                                </span>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    className="gap-1.5 border-gray-200 hover:border-indigo-300 hover:text-indigo-600 transition-colors"
                                    onClick={() => openClone(tpl)}
                                >
                                    <Copy className="h-3.5 w-3.5" />
                                    {t('Clone & Use')}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* Clone Dialog */}
            <Dialog open={!!selectedTemplate} onOpenChange={(o) => !o && setSelectedTemplate(null)}>
                <DialogContent className="max-w-lg">
                    {selectedTemplate && (
                        <>
                            <DialogHeader>
                                <DialogTitle className="flex items-center gap-2">
                                    <Copy className="h-4.5 w-4.5 text-indigo-500" />
                                    {t('Clone Template: :name', { name: selectedTemplate.name })}
                                </DialogTitle>
                                <DialogDescription>
                                    {t('Customize the name and target document type before cloning. You can further edit steps after creation.')}
                                </DialogDescription>
                            </DialogHeader>
                            <form onSubmit={handleClone} className="space-y-4 pt-2">
                                <div>
                                    <Label htmlFor="clone_name">{t('Workflow Name')}</Label>
                                    <Input
                                        id="clone_name"
                                        value={customName}
                                        onChange={e => setCustomName(e.target.value)}
                                        placeholder={t('Enter a descriptive name...')}
                                        required
                                        className="mt-1"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="clone_model">{t('Target Document Type')}</Label>
                                    <Select value={customModel} onValueChange={setCustomModel}>
                                        <SelectTrigger id="clone_model" className="mt-1">
                                            <SelectValue placeholder={t('Select target document...')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {availableModels.map(m => (
                                                <SelectItem
                                                    key={m.class}
                                                    value={m.class}
                                                    disabled={!m.exists}
                                                >
                                                    <span className={!m.exists ? 'opacity-40' : ''}>
                                                        {t(m.name)}
                                                        {!m.exists ? ' (module inactive)' : ''}
                                                    </span>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {/* Steps Preview */}
                                <div className="bg-gray-50 rounded-xl p-3">
                                    <div className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                                        {t('Stages that will be created')}
                                    </div>
                                    <div className="space-y-1.5">
                                        {selectedTemplate.steps.map((step, i) => (
                                            <div key={i} className="flex items-center gap-2 text-xs">
                                                <span className="w-5 h-5 rounded-full bg-indigo-100 text-indigo-600 font-bold flex items-center justify-center shrink-0">
                                                    {i + 1}
                                                </span>
                                                <span className="font-medium">{step.name}</span>
                                                <span className="text-gray-400">({step.approver_role ?? 'user'})</span>
                                                {getConditionLabel(step) && (
                                                    <span className="text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded text-[10px]">
                                                        {t('if')} {getConditionLabel(step)}
                                                    </span>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                <div className="flex justify-end gap-2 pt-2 border-t">
                                    <Button type="button" variant="outline" onClick={() => setSelectedTemplate(null)}>
                                        {t('Cancel')}
                                    </Button>
                                    <Button type="submit" disabled={isSubmitting || !customName || !customModel} className="gap-1.5">
                                        {isSubmitting ? t('Cloning...') : (
                                            <>
                                                <Copy className="h-4 w-4" />
                                                {t('Clone & Edit')}
                                            </>
                                        )}
                                    </Button>
                                </div>
                            </form>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
