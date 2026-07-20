import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    GitBranch, ShieldCheck, CheckCircle2, XCircle, Clock,
    AlertTriangle, TrendingUp, Activity, Zap, Plus, ListTodo,
    ArrowRight, User, ChevronRight
} from 'lucide-react';

interface WorkflowHealth {
    id: number;
    name: string;
    target_model: string;
    is_active: boolean;
    total: number;
    approved: number;
    rejected: number;
    pending: number;
    approval_rate: number;
}

interface ActivityEntry {
    user_name: string;
    action: string;
    workflow_name: string;
    comment: string | null;
    date: string;
}

interface Stats {
    total_workflows: number;
    active_workflows: number;
    total_pending: number;
    total_approved: number;
    total_rejected: number;
    my_action_count: number;
    overdue_count: number;
}

interface DashboardProps {
    stats: Stats;
    workflowHealth: WorkflowHealth[];
    recentActivity: ActivityEntry[];
}

export default function Dashboard({ stats, workflowHealth, recentActivity }: DashboardProps) {
    const { t } = useTranslation();

    const getModelLabel = (cls: string) => cls.split('\\').pop()?.replace(/([A-Z])/g, ' $1').trim() ?? cls;

    const getActionStyle = (action: string) => {
        switch (action) {
            case 'approve':   return 'bg-emerald-100 text-emerald-700';
            case 'reject':    return 'bg-red-100 text-red-700';
            case 'submitted': return 'bg-blue-100 text-blue-700';
            case 'delegated': return 'bg-purple-100 text-purple-700';
            case 'skipped':   return 'bg-amber-100 text-amber-700';
            default:          return 'bg-gray-100 text-gray-600';
        }
    };

    const getApprovalRateColor = (rate: number) => {
        if (rate >= 80) return 'text-emerald-600';
        if (rate >= 50) return 'text-amber-600';
        return 'text-red-600';
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('GadaaCloud Studio') }, { label: t('Dashboard') }]}
            pageTitle={t('GadaaCloud Studio')}
        >
            <Head title={t('Studio Dashboard')} />

            {/* Hero Banner */}
            <div className="relative rounded-2xl overflow-hidden mb-8 bg-gradient-to-br from-indigo-600 via-indigo-700 to-purple-700 text-white p-8">
                <div className="absolute inset-0 opacity-10" style={{ backgroundImage: 'radial-gradient(circle at 70% 50%, white 1px, transparent 1px)', backgroundSize: '24px 24px' }} />
                <div className="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                    <div>
                        <div className="flex items-center gap-3 mb-2">
                            <div className="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                                <GitBranch className="h-5 w-5 text-white" />
                            </div>
                            <h1 className="text-2xl font-extrabold tracking-tight">{t('GadaaCloud Studio')}</h1>
                        </div>
                        <p className="text-indigo-200 text-sm max-w-lg">
                            {t('Design multi-step approval workflows across all your modules. Track every document from submission to final sign-off.')}
                        </p>
                    </div>
                    <div className="flex gap-3 shrink-0">
                        <Button asChild size="sm" className="bg-white text-indigo-700 hover:bg-indigo-50 shadow-lg font-semibold">
                            <Link href={route('gadaacloud-studio.workflows.create')}>
                                <Plus className="h-4 w-4 mr-1.5" />
                                {t('New Workflow')}
                            </Link>
                        </Button>
                        <Button asChild size="sm" variant="outline" className="border-white/30 text-white hover:bg-white/10">
                            <Link href={route('gadaacloud-studio.approvals.index')}>
                                <ShieldCheck className="h-4 w-4 mr-1.5" />
                                {t('Approval Center')}
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>

            {/* KPI Stats */}
            <div className="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-4 mb-8">
                <KpiCard icon={<GitBranch />} label={t('Total Workflows')} value={stats.total_workflows} tone="indigo" />
                <KpiCard icon={<Activity />} label={t('Active')} value={stats.active_workflows} tone="emerald" />
                <KpiCard icon={<Clock />} label={t('Pending')} value={stats.total_pending} tone="amber" />
                <KpiCard icon={<CheckCircle2 />} label={t('Approved')} value={stats.total_approved} tone="green" />
                <KpiCard icon={<XCircle />} label={t('Rejected')} value={stats.total_rejected} tone="red" />
                <KpiCard
                    icon={<ListTodo />}
                    label={t('My Actions')}
                    value={stats.my_action_count}
                    tone={stats.my_action_count > 0 ? 'rose' : 'gray'}
                    href={route('gadaacloud-studio.approvals.index')}
                    highlight={stats.my_action_count > 0}
                />
                <KpiCard
                    icon={<AlertTriangle />}
                    label={t('Overdue')}
                    value={stats.overdue_count}
                    tone={stats.overdue_count > 0 ? 'red' : 'gray'}
                    highlight={stats.overdue_count > 0}
                />
            </div>

            <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                {/* Workflow Health Table */}
                <div className="xl:col-span-2">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between border-b border-gray-100/60 pb-4">
                            <div>
                                <CardTitle className="text-base font-semibold flex items-center gap-2">
                                    <TrendingUp className="h-4 w-4 text-indigo-500" />
                                    {t('Workflow Health Overview')}
                                </CardTitle>
                                <CardDescription>{t('Approval rates and active request counts per workflow')}</CardDescription>
                            </div>
                            <Button asChild size="sm" variant="outline" className="border-gray-200 gap-1">
                                <Link href={route('gadaacloud-studio.workflows.index')}>
                                    {t('Manage All')}
                                    <ChevronRight className="h-3.5 w-3.5" />
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="p-0">
                            {workflowHealth.length === 0 ? (
                                <div className="text-center py-12 text-muted-foreground">
                                    <GitBranch className="h-10 w-10 mx-auto mb-3 opacity-20" />
                                    <p className="font-medium">{t('No workflows yet')}</p>
                                    <p className="text-sm mt-1">{t('Create your first workflow to get started.')}</p>
                                    <Button asChild size="sm" className="mt-4">
                                        <Link href={route('gadaacloud-studio.workflows.create')}>
                                            <Plus className="h-4 w-4 mr-1.5" />
                                            {t('Create Workflow')}
                                        </Link>
                                    </Button>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="bg-gray-50/50 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider border-b">
                                                <th className="p-4 pl-5">{t('Workflow')}</th>
                                                <th className="p-4 hidden sm:table-cell">{t('Document Type')}</th>
                                                <th className="p-4 text-center">{t('Total')}</th>
                                                <th className="p-4 text-center">{t('Pending')}</th>
                                                <th className="p-4 text-center">{t('Approved')}</th>
                                                <th className="p-4 text-center pr-5">{t('Rate')}</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {workflowHealth.map((wf) => (
                                                <tr key={wf.id} className="hover:bg-gray-50/30 transition-colors">
                                                    <td className="p-4 pl-5">
                                                        <div className="flex items-center gap-2">
                                                            <div className={`w-2 h-2 rounded-full ${wf.is_active ? 'bg-emerald-500' : 'bg-gray-300'}`} />
                                                            <Link
                                                                href={route('gadaacloud-studio.workflows.edit', wf.id)}
                                                                className="font-semibold text-gray-900 hover:text-indigo-600 transition-colors"
                                                            >
                                                                {wf.name}
                                                            </Link>
                                                        </div>
                                                    </td>
                                                    <td className="p-4 hidden sm:table-cell">
                                                        <span className="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded font-mono">
                                                            {getModelLabel(wf.target_model)}
                                                        </span>
                                                    </td>
                                                    <td className="p-4 text-center font-medium">{wf.total}</td>
                                                    <td className="p-4 text-center">
                                                        {wf.pending > 0 ? (
                                                            <span className="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-100 text-amber-700 text-xs font-bold">
                                                                {wf.pending}
                                                            </span>
                                                        ) : (
                                                            <span className="text-gray-300">—</span>
                                                        )}
                                                    </td>
                                                    <td className="p-4 text-center text-emerald-600 font-medium">{wf.approved}</td>
                                                    <td className="p-4 text-center pr-5">
                                                        <div className="flex items-center justify-center gap-1.5">
                                                            <div className="w-16 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                                                <div
                                                                    className="h-full bg-emerald-500 rounded-full transition-all"
                                                                    style={{ width: `${wf.approval_rate}%` }}
                                                                />
                                                            </div>
                                                            <span className={`text-xs font-bold ${getApprovalRateColor(wf.approval_rate)}`}>
                                                                {wf.approval_rate}%
                                                            </span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Recent Activity Feed */}
                <div>
                    <Card className="h-full">
                        <CardHeader className="border-b border-gray-100/60 pb-4">
                            <CardTitle className="text-base font-semibold flex items-center gap-2">
                                <Zap className="h-4 w-4 text-amber-500" />
                                {t('Recent Activity')}
                            </CardTitle>
                            <CardDescription>{t('Latest approval actions across all workflows')}</CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            {recentActivity.length === 0 ? (
                                <div className="text-center py-10 text-muted-foreground">
                                    <Activity className="h-8 w-8 mx-auto mb-2 opacity-20" />
                                    <p className="text-sm">{t('No activity recorded yet.')}</p>
                                </div>
                            ) : (
                                <div className="divide-y divide-gray-100 max-h-[480px] overflow-y-auto">
                                    {recentActivity.map((entry, idx) => (
                                        <div key={idx} className="p-3.5 flex items-start gap-3 hover:bg-gray-50/30 transition-colors">
                                            <div className="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center shrink-0 mt-0.5">
                                                <User className="h-3.5 w-3.5 text-gray-500" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-2 flex-wrap">
                                                    <span className="font-semibold text-xs text-gray-900">{entry.user_name}</span>
                                                    <span className={`px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase ${getActionStyle(entry.action)}`}>
                                                        {t(entry.action)}
                                                    </span>
                                                </div>
                                                <p className="text-xs text-gray-500 mt-0.5 truncate">{entry.workflow_name}</p>
                                                {entry.comment && (
                                                    <p className="text-[11px] text-gray-400 italic mt-0.5 line-clamp-1">"{entry.comment}"</p>
                                                )}
                                                <p className="text-[10px] text-gray-300 mt-1">{entry.date}</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Quick Actions */}
            <div className="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <QuickAction
                    href={route('gadaacloud-studio.workflows.create')}
                    icon={<Plus className="h-5 w-5" />}
                    label={t('Build New Workflow')}
                    description={t('Design a custom approval chain from scratch')}
                    tone="indigo"
                />
                <QuickAction
                    href={route('gadaacloud-studio.templates')}
                    icon={<GitBranch className="h-5 w-5" />}
                    label={t('Browse Templates')}
                    description={t('Start from a pre-built workflow template')}
                    tone="purple"
                />
                <QuickAction
                    href={route('gadaacloud-studio.approvals.index')}
                    icon={<ShieldCheck className="h-5 w-5" />}
                    label={t('Open Approval Center')}
                    description={t('Review and act on pending approvals')}
                    tone="emerald"
                />
            </div>
        </AuthenticatedLayout>
    );
}

function KpiCard({ icon, label, value, tone, href, highlight }: {
    icon: React.ReactNode;
    label: string;
    value: number;
    tone: string;
    href?: string;
    highlight?: boolean;
}) {
    const TONES: Record<string, string> = {
        indigo:  'bg-indigo-50  border-indigo-100  text-indigo-700  dark:bg-indigo-950/20',
        emerald: 'bg-emerald-50 border-emerald-100 text-emerald-700 dark:bg-emerald-950/20',
        amber:   'bg-amber-50   border-amber-100   text-amber-700   dark:bg-amber-950/20',
        green:   'bg-green-50   border-green-100   text-green-700   dark:bg-green-950/20',
        red:     'bg-red-50     border-red-100     text-red-700     dark:bg-red-950/20',
        rose:    'bg-rose-50    border-rose-100    text-rose-700    dark:bg-rose-950/20',
        gray:    'bg-gray-50    border-gray-100    text-gray-500    dark:bg-gray-900/20',
        purple:  'bg-purple-50  border-purple-100  text-purple-700  dark:bg-purple-950/20',
    };

    const classes = `${TONES[tone] || TONES.gray} border rounded-xl p-4 flex items-center gap-3 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-sm ${highlight ? 'ring-2 ring-offset-1 ring-current' : ''}`;

    const content = (
        <>
            <div className="w-9 h-9 rounded-lg bg-current/10 flex items-center justify-center shrink-0 opacity-70">
                {icon}
            </div>
            <div>
                <div className="text-2xl font-black leading-none">{value}</div>
                <div className="text-[11px] opacity-70 mt-0.5 leading-tight">{label}</div>
            </div>
        </>
    );

    if (href) {
        return <Link href={href} className={classes}>{content}</Link>;
    }
    return <div className={classes}>{content}</div>;
}

function QuickAction({ href, icon, label, description, tone }: {
    href: string;
    icon: React.ReactNode;
    label: string;
    description: string;
    tone: string;
}) {
    const TONES: Record<string, string> = {
        indigo:  'from-indigo-500  to-indigo-600',
        purple:  'from-purple-500  to-purple-600',
        emerald: 'from-emerald-500 to-emerald-600',
    };
    return (
        <Link
            href={href}
            className={`group relative overflow-hidden rounded-xl bg-gradient-to-br ${TONES[tone] || TONES.indigo} text-white p-5 flex items-start gap-4 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200`}
        >
            <div className="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0 group-hover:bg-white/30 transition-colors">
                {icon}
            </div>
            <div>
                <div className="font-bold text-sm">{label}</div>
                <div className="text-xs opacity-80 mt-0.5">{description}</div>
            </div>
            <ArrowRight className="h-4 w-4 absolute right-4 top-1/2 -translate-y-1/2 opacity-40 group-hover:opacity-100 group-hover:translate-x-1 transition-all" />
        </Link>
    );
}
