import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';
import {
    ArrowLeft, CheckCircle2, XCircle, Clock, ShieldCheck, User,
    GitBranch, AlertTriangle, CalendarDays, Tag, Send, Users, ChevronRight
} from 'lucide-react';

interface DocumentField { label: string; value: string; }
interface Step {
    id: number;
    name: string;
    sequence: number;
    approver_type: string;
    approver_role: string | null;
    approver_user_name: string | null;
    condition_label: string;
    skip_if_condition_fails: boolean;
}
interface Log {
    user_name: string;
    step_name: string;
    action: string;
    comment: string | null;
    date: string;
}
interface Delegation {
    delegated_by: string;
    delegated_to: string;
    reason: string | null;
    valid_until: string | null;
    is_active: boolean;
}
interface UserOption { id: number; name: string; email: string; }

interface ApprovalRequest {
    id: number;
    workflow_name: string;
    target_model_name: string;
    document_title: string;
    document_details: string;
    document_fields: DocumentField[];
    status: 'pending' | 'approved' | 'rejected';
    priority: 'low' | 'medium' | 'high' | 'urgent';
    deadline_at: string | null;
    days_until_deadline: number | null;
    is_overdue: boolean;
    current_step_name: string;
    submitter_note: string | null;
    can_action: boolean;
    delegated_to: string | null;
    logs: Log[];
    steps: Step[];
    delegations: Delegation[];
}

interface ShowProps {
    request: ApprovalRequest;
    users: UserOption[];
}

export default function Show({ request: req, users }: ShowProps) {
    const { t } = useTranslation();
    const [comment, setComment] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showDelegate, setShowDelegate] = useState(false);
    const [delegateTo, setDelegateTo] = useState('');
    const [delegateReason, setDelegateReason] = useState('');
    const [delegateUntil, setDelegateUntil] = useState('');

    const handleAction = (action: 'approve' | 'reject') => {
        setIsSubmitting(true);
        const routeName = action === 'approve'
            ? 'gadaacloud-studio.approvals.approve'
            : 'gadaacloud-studio.approvals.reject';
        router.post(route(routeName, req.id), { comment }, {
            onSuccess: () => { setIsSubmitting(false); setComment(''); },
            onError: () => setIsSubmitting(false),
        });
    };

    const handleDelegate = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(route('gadaacloud-studio.approvals.delegate', req.id), {
            delegate_to_user_id: delegateTo,
            reason: delegateReason,
            valid_until: delegateUntil || undefined,
        }, {
            onSuccess: () => setShowDelegate(false),
        });
    };

    const priorityColors: Record<string, string> = {
        urgent: 'bg-red-100 text-red-700 border-red-200',
        high:   'bg-orange-100 text-orange-700 border-orange-200',
        medium: 'bg-amber-100 text-amber-700 border-amber-200',
        low:    'bg-gray-100 text-gray-600 border-gray-200',
    };

    const statusConfig = {
        pending:  { label: t('Pending'),  cls: 'border-amber-400 bg-amber-50 text-amber-700',  icon: <Clock className="h-3.5 w-3.5" /> },
        approved: { label: t('Approved'), cls: 'border-green-500 bg-green-50 text-green-700',   icon: <CheckCircle2 className="h-3.5 w-3.5" /> },
        rejected: { label: t('Rejected'), cls: 'border-red-500 bg-red-50 text-red-700',         icon: <XCircle className="h-3.5 w-3.5" /> },
    }[req.status];

    const actionLogStyle = (action: string): string => {
        switch (action) {
            case 'approve':   return 'text-emerald-600 bg-emerald-50';
            case 'reject':    return 'text-red-600 bg-red-50';
            case 'delegated': return 'text-purple-600 bg-purple-50';
            case 'skipped':   return 'text-amber-600 bg-amber-50';
            case 'submitted': return 'text-blue-600 bg-blue-50';
            default:          return 'text-gray-600 bg-gray-50';
        }
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('GadaaCloud Studio'), url: route('gadaacloud-studio.dashboard') },
                { label: t('Approval Center'), url: route('gadaacloud-studio.approvals.index') },
                { label: req.document_title },
            ]}
            pageTitle={t('Approval Request Detail')}
        >
            <Head title={req.document_title} />

            <div className="max-w-4xl mx-auto space-y-6">
                {/* Back */}
                <Button asChild variant="outline" size="sm" className="gap-1 border-gray-200">
                    <Link href={route('gadaacloud-studio.approvals.index')}>
                        <ArrowLeft className="h-4 w-4" />
                        {t('Back to Approval Center')}
                    </Link>
                </Button>

                {/* Header Card */}
                <Card>
                    <CardContent className="p-6">
                        <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                            <div className="space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h2 className="text-xl font-bold text-gray-900">{req.document_title}</h2>
                                    <Badge variant="outline" className={statusConfig.cls + ' flex items-center gap-1 font-semibold'}>
                                        {statusConfig.icon}
                                        {statusConfig.label}
                                    </Badge>
                                    <Badge variant="outline" className={`text-xs ${priorityColors[req.priority]} font-semibold`}>
                                        <Tag className="h-3 w-3 mr-1" />
                                        {t(req.priority.charAt(0).toUpperCase() + req.priority.slice(1))}
                                    </Badge>
                                    {req.is_overdue && (
                                        <Badge variant="destructive" className="flex items-center gap-1">
                                            <AlertTriangle className="h-3 w-3" />
                                            {t('Overdue')}
                                        </Badge>
                                    )}
                                </div>
                                <div className="text-sm text-muted-foreground space-y-1">
                                    <div className="flex flex-wrap gap-4">
                                        <span className="flex items-center gap-1.5">
                                            <GitBranch className="h-3.5 w-3.5 text-indigo-400" />
                                            {t('Workflow')}: <strong>{req.workflow_name}</strong>
                                        </span>
                                        <span className="flex items-center gap-1.5">
                                            <span className="font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded">{req.target_model_name}</span>
                                        </span>
                                    </div>
                                    {req.deadline_at && (
                                        <div className={`flex items-center gap-1.5 text-xs ${req.is_overdue ? 'text-red-600 font-semibold' : 'text-gray-500'}`}>
                                            <CalendarDays className="h-3.5 w-3.5" />
                                            {t('Deadline')}: {req.deadline_at}
                                            {req.days_until_deadline !== null && (
                                                <span>({req.days_until_deadline >= 0 ? `${req.days_until_deadline}d left` : `${Math.abs(req.days_until_deadline)}d overdue`})</span>
                                            )}
                                        </div>
                                    )}
                                    {req.delegated_to && (
                                        <div className="flex items-center gap-1.5 text-xs text-purple-600">
                                            <Users className="h-3.5 w-3.5" />
                                            {t('Delegated to')}: <strong>{req.delegated_to}</strong>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {req.can_action && (
                                <div className="flex gap-2 shrink-0">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="gap-1 text-purple-600 border-purple-200 hover:bg-purple-50"
                                        onClick={() => setShowDelegate(true)}
                                    >
                                        <Users className="h-4 w-4" />
                                        {t('Delegate')}
                                    </Button>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* Document Fields */}
                    <Card>
                        <CardHeader className="border-b pb-4">
                            <CardTitle className="text-sm font-semibold">{t('Document Details')}</CardTitle>
                        </CardHeader>
                        <CardContent className="p-5 space-y-3">
                            {req.document_fields.length > 0 ? (
                                req.document_fields.map((f, i) => (
                                    <div key={i} className="flex items-center justify-between py-1.5 border-b border-gray-50 last:border-0">
                                        <span className="text-xs text-gray-500 font-medium">{f.label}</span>
                                        <span className="text-sm font-semibold text-gray-900">{f.value}</span>
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-muted-foreground">{req.document_details || '—'}</p>
                            )}
                            {req.submitter_note && (
                                <div className="mt-3 pt-3 border-t border-dashed">
                                    <p className="text-xs text-gray-500 font-semibold mb-1">{t('Submitter Note')}</p>
                                    <p className="text-sm text-gray-700 italic bg-gray-50 rounded-lg p-2.5">"{req.submitter_note}"</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Approval Stages Progress */}
                    <Card>
                        <CardHeader className="border-b pb-4">
                            <CardTitle className="text-sm font-semibold">{t('Approval Stages')}</CardTitle>
                        </CardHeader>
                        <CardContent className="p-5 space-y-3">
                            {req.steps.map((step, idx) => {
                                const isCompleted = req.logs.some(l => l.step_name === step.name && l.action === 'approve');
                                const isSkipped   = req.logs.some(l => l.step_name === step.name && l.action === 'skipped');
                                const isCurrent   = req.status === 'pending' && req.current_step_name === step.name;
                                const isRejected  = req.status === 'rejected' && req.logs.some(l => l.step_name === step.name && l.action === 'reject');

                                return (
                                    <div key={step.id} className="flex items-start gap-3">
                                        {/* Indicator */}
                                        <div className={`w-7 h-7 rounded-full flex items-center justify-center shrink-0 text-xs font-bold border-2 mt-0.5
                                            ${isCompleted ? 'bg-emerald-500 border-emerald-500 text-white' :
                                              isSkipped   ? 'bg-gray-200 border-gray-200 text-gray-400' :
                                              isRejected  ? 'bg-red-500 border-red-500 text-white' :
                                              isCurrent   ? 'bg-indigo-100 border-indigo-500 text-indigo-600 animate-pulse' :
                                                            'bg-white border-gray-200 text-gray-400'}`}
                                        >
                                            {isCompleted ? '✓' : isSkipped ? '↷' : isRejected ? '✗' : idx + 1}
                                        </div>
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <span className={`text-sm font-semibold ${isCurrent ? 'text-indigo-600' : isCompleted ? 'text-emerald-700' : 'text-gray-700'}`}>
                                                    {step.name}
                                                </span>
                                                <span className="text-xs text-gray-400">
                                                    {step.approver_type === 'role' ? `Role: ${step.approver_role}` : step.approver_user_name}
                                                </span>
                                            </div>
                                            {step.condition_label !== 'No condition' && (
                                                <p className="text-[10px] text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded mt-0.5 inline-block">
                                                    {t('Condition')}: {step.condition_label}
                                                    {step.skip_if_condition_fails && <span className="ml-1 opacity-70">({t('skip if not met')})</span>}
                                                </p>
                                            )}
                                        </div>
                                        {idx < req.steps.length - 1 && (
                                            <div className="absolute left-3.5 mt-7 w-0.5 h-4 bg-gray-200" style={{ position: 'relative', left: '-calc(50% - 1px)' }} />
                                        )}
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                </div>

                {/* Audit Log */}
                <Card>
                    <CardHeader className="border-b pb-4">
                        <CardTitle className="text-sm font-semibold">{t('Audit Trail')}</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0 divide-y divide-gray-100">
                        {req.logs.length === 0 ? (
                            <p className="text-sm text-muted-foreground p-5">{t('No actions recorded yet.')}</p>
                        ) : req.logs.map((log, i) => (
                            <div key={i} className="flex items-start gap-4 p-4 hover:bg-gray-50/30 transition-colors">
                                <div className="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center shrink-0">
                                    <User className="h-4 w-4 text-gray-500" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className="font-semibold text-sm text-gray-900">{log.user_name}</span>
                                        <span className={`px-2 py-0.5 rounded text-xs font-semibold ${actionLogStyle(log.action)}`}>
                                            {t(log.action)}
                                        </span>
                                        <span className="text-xs text-gray-400">{t('at step')}: <em>{log.step_name}</em></span>
                                    </div>
                                    {log.comment && (
                                        <p className="mt-1 text-sm text-gray-600 italic bg-gray-50 p-2 rounded-lg">"{log.comment}"</p>
                                    )}
                                    <p className="text-[11px] text-gray-400 mt-1">{log.date}</p>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                {/* Take Action */}
                {req.can_action && req.status === 'pending' && (
                    <Card className="border-indigo-100 bg-indigo-50/30">
                        <CardHeader className="border-b border-indigo-100 pb-4">
                            <CardTitle className="text-sm font-semibold flex items-center gap-2 text-indigo-700">
                                <ShieldCheck className="h-4 w-4" />
                                {t('Take Action on')} "{req.current_step_name}"
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-5 space-y-4">
                            <div>
                                <Label htmlFor="action_comment" className="text-xs font-semibold text-gray-600">
                                    {t('Comment (Optional)')}
                                </Label>
                                <Textarea
                                    id="action_comment"
                                    value={comment}
                                    onChange={e => setComment(e.target.value)}
                                    placeholder={t('Add your review notes or feedback...')}
                                    rows={3}
                                    className="mt-1.5"
                                />
                            </div>
                            <div className="flex items-center justify-end gap-3">
                                <Button
                                    variant="destructive"
                                    disabled={isSubmitting}
                                    onClick={() => handleAction('reject')}
                                    className="gap-1.5 shadow"
                                >
                                    <XCircle className="h-4 w-4" />
                                    {t('Reject')}
                                </Button>
                                <Button
                                    disabled={isSubmitting}
                                    onClick={() => handleAction('approve')}
                                    className="bg-emerald-600 hover:bg-emerald-700 gap-1.5 shadow"
                                >
                                    <CheckCircle2 className="h-4 w-4" />
                                    {t('Approve Step')}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Active Delegations */}
                {req.delegations && req.delegations.length > 0 && (
                    <Card>
                        <CardHeader className="border-b pb-4">
                            <CardTitle className="text-sm font-semibold">{t('Delegation History')}</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0 divide-y divide-gray-100">
                            {req.delegations.map((d, i) => (
                                <div key={i} className="flex items-center justify-between p-4 gap-3">
                                    <div className="text-sm">
                                        <span className="font-semibold text-gray-900">{d.delegated_by}</span>
                                        <span className="text-gray-400 mx-1.5">→</span>
                                        <span className="font-semibold text-purple-700">{d.delegated_to}</span>
                                        {d.reason && <p className="text-xs text-gray-500 italic mt-0.5">"{d.reason}"</p>}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {d.valid_until && <span className="text-xs text-gray-400">Until {d.valid_until}</span>}
                                        <span className={`text-xs px-2 py-0.5 rounded-full font-semibold ${d.is_active ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-400'}`}>
                                            {d.is_active ? t('Active') : t('Expired')}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Delegate Dialog */}
            <Dialog open={showDelegate} onOpenChange={setShowDelegate}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Users className="h-4.5 w-4.5 text-purple-500" />
                            {t('Delegate Approval Step')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('Assign this step to another user temporarily. You can revoke this at any time.')}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleDelegate} className="space-y-4 pt-2">
                        <div>
                            <Label htmlFor="delegate_to">{t('Delegate To')}</Label>
                            <Select value={delegateTo} onValueChange={setDelegateTo} required>
                                <SelectTrigger id="delegate_to" className="mt-1">
                                    <SelectValue placeholder={t('Select user...')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {users.map(u => (
                                        <SelectItem key={u.id} value={String(u.id)}>
                                            {u.name} ({u.email})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="delegate_reason">{t('Reason (Optional)')}</Label>
                            <Textarea
                                id="delegate_reason"
                                value={delegateReason}
                                onChange={e => setDelegateReason(e.target.value)}
                                placeholder={t('e.g., on leave, conflict of interest...')}
                                rows={2}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <Label htmlFor="delegate_until">{t('Valid Until (Optional)')}</Label>
                            <input
                                id="delegate_until"
                                type="date"
                                value={delegateUntil}
                                onChange={e => setDelegateUntil(e.target.value)}
                                className="mt-1 w-full rounded-md border border-input px-3 py-2 text-sm shadow-sm"
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setShowDelegate(false)}>{t('Cancel')}</Button>
                            <Button type="submit" disabled={!delegateTo} className="gap-1.5 bg-purple-600 hover:bg-purple-700">
                                <Send className="h-4 w-4" />
                                {t('Delegate')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
