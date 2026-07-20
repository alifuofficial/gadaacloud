import { useState, useMemo } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { SearchInput } from "@/components/ui/search-input";
import NoRecordsFound from '@/components/no-records-found';
import { Settings, Plus, Edit2, Trash2, GitBranch, Shield, User, ArrowRight, Activity, Play, Pause } from 'lucide-react';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";

interface WorkflowStep {
    id: number;
    name: string;
    approver_type: 'role' | 'user';
    approver_role: string | null;
    approver_user_id: number | null;
    approver_user?: {
        name: string;
    };
    sequence: number;
}

interface Workflow {
    id: number;
    name: string;
    target_model: string;
    description: string | null;
    is_active: boolean;
    steps: WorkflowStep[];
}

interface IndexProps {
    workflows: Workflow[];
}

export default function Index({ workflows }: IndexProps) {
    const { t } = useTranslation();
    const [search, setSearch] = useState('');

    const filteredWorkflows = useMemo(() => {
        const query = search.trim().toLowerCase();
        return workflows.filter(w =>
            w.name.toLowerCase().includes(query) ||
            (w.description && w.description.toLowerCase().includes(query)) ||
            w.target_model.toLowerCase().includes(query)
        );
    }, [workflows, search]);

    const handleToggleStatus = (id: number) => {
        router.post(route('gadaacloud-studio.workflows.toggle', id), {}, { preserveState: true });
    };

    const handleDelete = (id: number) => {
        if (confirm(t('Are you sure you want to delete this workflow?'))) {
            router.delete(route('gadaacloud-studio.workflows.destroy', id));
        }
    };

    const getModelFriendlyName = (fullClass: string) => {
        const parts = fullClass.split('\\');
        const base = parts[parts.length - 1];
        // Split camelCase
        return base.replace(/([A-Z])/g, ' $1').trim();
    };

    const activeCount = workflows.filter(w => w.is_active).length;
    const totalSteps = workflows.reduce((acc, w) => acc + w.steps.length, 0);

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('GadaaCloud Studio') }, { label: t('Workflows') }]} pageTitle={t('Workflows Studio')}>
            <Head title={t('Workflow Builder')} />

            {/* Stats Header */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <Card className="border-emerald-100 bg-emerald-50/40 dark:border-emerald-950 dark:bg-emerald-950/15">
                    <CardContent className="p-5 flex items-center gap-4">
                        <div className="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                            <GitBranch className="h-6 w-6" />
                        </div>
                        <div>
                            <div className="text-3xl font-extrabold tracking-tight text-emerald-700 dark:text-emerald-400">{workflows.length}</div>
                            <div className="text-sm font-medium text-emerald-600 dark:text-emerald-500/80 mt-0.5">{t('Total Workflows')}</div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-blue-100 bg-blue-50/40 dark:border-blue-950 dark:bg-blue-950/15">
                    <CardContent className="p-5 flex items-center gap-4">
                        <div className="w-12 h-12 rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <Activity className="h-6 w-6" />
                        </div>
                        <div>
                            <div className="text-3xl font-extrabold tracking-tight text-blue-700 dark:text-blue-400">{activeCount}</div>
                            <div className="text-sm font-medium text-blue-600 dark:text-blue-500/80 mt-0.5">{t('Active Workflows')}</div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-amber-100 bg-amber-50/40 dark:border-amber-950 dark:bg-amber-950/15">
                    <CardContent className="p-5 flex items-center gap-4">
                        <div className="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                            <Settings className="h-6 w-6" />
                        </div>
                        <div>
                            <div className="text-3xl font-extrabold tracking-tight text-amber-700 dark:text-amber-400">{totalSteps}</div>
                            <div className="text-sm font-medium text-amber-600 dark:text-amber-500/80 mt-0.5">{t('Total Approval Stages')}</div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card className="overflow-hidden">
                <CardHeader className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 border-b border-gray-100/60 pb-5">
                    <div className="flex-1">
                        <SearchInput 
                            value={search} 
                            onChange={setSearch} 
                            onSearch={() => {}} 
                            placeholder={t('Search workflows by name or target model...')} 
                            className="w-full max-w-md"
                        />
                    </div>
                    <Button asChild className="gap-1.5 shadow-md self-start sm:self-auto">
                        <Link href={route('gadaacloud-studio.workflows.create')}>
                            <Plus className="h-4 w-4" />
                            {t('Create Workflow')}
                        </Link>
                    </Button>
                </CardHeader>

                <CardContent className="p-0">
                    {filteredWorkflows.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-gray-50/50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider border-b">
                                        <th className="p-4 pl-6">{t('Workflow')}</th>
                                        <th className="p-4">{t('Target Document')}</th>
                                        <th className="p-4 hidden lg:table-cell">{t('Approval Flow')}</th>
                                        <th className="p-4 text-center">{t('Status')}</th>
                                        <th className="p-4 text-right pr-6">{t('Actions')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {filteredWorkflows.map((w) => (
                                        <tr key={w.id} className="hover:bg-gray-50/20 transition-colors">
                                            <td className="p-4 pl-6">
                                                <div className="font-semibold text-gray-900">{w.name}</div>
                                                {w.description && (
                                                    <div className="text-xs text-gray-400 mt-0.5 line-clamp-1 max-w-xs">{w.description}</div>
                                                )}
                                            </td>
                                            <td className="p-4">
                                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-950/30 dark:text-blue-400">
                                                    {t(getModelFriendlyName(w.target_model))}
                                                </span>
                                            </td>
                                            <td className="p-4 hidden lg:table-cell">
                                                <div className="flex items-center gap-2 flex-wrap">
                                                    {w.steps.map((step, idx) => (
                                                        <div key={step.id} className="flex items-center gap-2">
                                                            <div className="flex items-center gap-1.5 bg-gray-100 hover:bg-gray-200/80 px-2.5 py-1 rounded-lg border text-xs font-medium text-gray-700 transition-colors">
                                                                {step.approver_type === 'role' ? (
                                                                    <>
                                                                        <Shield className="h-3.5 w-3.5 text-indigo-500" />
                                                                        <span>{step.name} ({step.approver_role})</span>
                                                                    </>
                                                                ) : (
                                                                    <>
                                                                        <User className="h-3.5 w-3.5 text-emerald-500" />
                                                                        <span>{step.name} ({step.approver_user?.name || t('User')})</span>
                                                                    </>
                                                                )}
                                                            </div>
                                                            {idx < w.steps.length - 1 && (
                                                                <ArrowRight className="h-3 w-3 text-gray-300 shrink-0" />
                                                            )}
                                                        </div>
                                                    ))}
                                                </div>
                                            </td>
                                            <td className="p-4 text-center">
                                                <div className="flex items-center justify-center gap-2">
                                                    <Switch
                                                        checked={w.is_active}
                                                        onCheckedChange={() => handleToggleStatus(w.id)}
                                                    />
                                                    <span className={`text-xs font-semibold ${w.is_active ? 'text-emerald-600' : 'text-gray-400'}`}>
                                                        {w.is_active ? t('Active') : t('Inactive')}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="p-4 text-right pr-6">
                                                <div className="flex items-center justify-end gap-1.5">
                                                    <TooltipProvider>
                                                        <Tooltip delayDuration={0}>
                                                            <TooltipTrigger asChild>
                                                                <Button asChild variant="outline" size="icon" className="h-8 w-8 hover:bg-gray-50 border-gray-200">
                                                                    <Link href={route('gadaacloud-studio.workflows.edit', w.id)}>
                                                                        <Edit2 className="h-3.5 w-3.5 text-gray-500" />
                                                                    </Link>
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent><p>{t('Edit Workflow')}</p></TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>

                                                    <TooltipProvider>
                                                        <Tooltip delayDuration={0}>
                                                            <TooltipTrigger asChild>
                                                                <Button variant="outline" size="icon" onClick={() => handleDelete(w.id)} className="h-8 w-8 hover:bg-red-50 hover:text-red-600 border-gray-200 hover:border-red-200">
                                                                    <Trash2 className="h-3.5 w-3.5 text-gray-500 hover:text-red-500" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent><p>{t('Delete Workflow')}</p></TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <NoRecordsFound
                            icon={GitBranch}
                            title={t('No workflows defined')}
                            description={search ? t('No workflows match your search query.') : t('Build custom step-by-step document approval pathways.')}
                            hasFilters={!!search}
                            onClearFilters={() => setSearch('')}
                        />
                    )}
                </CardContent>
            </Card>
        </AuthenticatedLayout>
    );
}
