import { useState } from 'react';
import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useTranslation } from 'react-i18next';
import { useForm, usePage } from '@inertiajs/react';
import { Star, FileText, Lock, CheckCircle2, Award, AlertTriangle, TrendingUp, DollarSign } from "lucide-react";
import { formatDate, isRichTextEmpty } from '@/utils/helpers';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RichTextEditor } from "@/components/ui/rich-text-editor";
import { RichTextViewer } from "@/components/ui/rich-text-viewer";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";

interface PerformanceIndicator {
    id: number;
    name: string;
    user_rating: number;
    employee_self_rating?: number;
    weight?: number;
}

interface ShowProps {
    employeeReview: any;
    performanceIndicators: { [categoryName: string]: PerformanceIndicator[] };
    averageRating: number | null;
    competencies?: any[];
    employeeGoals?: any[];
    peerFeedback?: any[];
    nineBox?: { performance_tier: string; potential_tier: string; box_label: string };
    hrmContext?: any;
}

/**
 * Strip unsafe tags/attributes client-side before rendering sanitized HTML feedback.
 */
function toSafePlainText(html?: string | null): string {
    if (!html) return '';
    try {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        return doc.body.textContent || '';
    } catch {
        return html.replace(/<[^>]*>/g, '');
    }
}

export default function Show({
    employeeReview,
    performanceIndicators = {},
    averageRating,
    competencies = [],
    employeeGoals = [],
    peerFeedback = [],
    nineBox,
    hrmContext,
}: ShowProps) {
    const { t } = useTranslation();
    const { auth } = usePage<any>().props;
    const permissions: string[] = auth?.user?.permissions || [];
    const currentUserId = auth?.user?.id;

    const [hrActionType, setHrActionType] = useState<string>('');
    const hrForm = useForm({
        action: '',
        reason: '',
        award_type_id: 'none',
        warning_type_id: 'none',
        severity: 'medium',
        subject: 'Performance Improvement Plan (PIP)',
        allowance_type_id: 'none',
        bonus_type: 'fixed',
        amount: '',
        branch_id: 'none',
        department_id: 'none',
        designation_id: 'none',
    });

    const calibrateForm = useForm({
        weighted_rating: employeeReview?.weighted_rating?.toString() || (averageRating?.toString() || ''),
        calibration_notes: employeeReview?.calibration_notes || '',
        is_locked: Boolean(employeeReview?.is_locked),
    });

    const ackForm = useForm({
        employee_acknowledgment_notes: employeeReview?.employee_acknowledgment_notes || '',
    });

    const getStatusColor = (status: string) => {
        const colors: { [key: string]: string } = {
            pending: 'bg-yellow-100 text-yellow-800',
            self_submitted: 'bg-indigo-100 text-indigo-800',
            in_progress: 'bg-blue-100 text-blue-800',
            calibration: 'bg-purple-100 text-purple-800',
            completed: 'bg-green-100 text-green-800',
            cancelled: 'bg-red-100 text-red-800',
        };
        return colors[status] || 'bg-gray-100 text-gray-800';
    };

    const renderStars = (rating: number) => (
        <div className="flex gap-1">
            {[1, 2, 3, 4, 5].map((star) => (
                <Star
                    key={star}
                    className={`h-4 w-4 ${star <= Math.round(rating) ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`}
                />
            ))}
        </div>
    );

    const submitCalibration = (e: React.FormEvent, lock: boolean) => {
        e.preventDefault();
        calibrateForm.transform((data) => ({ ...data, is_locked: lock }));
        calibrateForm.post(route('performance.employee-reviews.calibrate', employeeReview.id));
    };

    const submitAck = (e: React.FormEvent) => {
        e.preventDefault();
        ackForm.post(route('performance.employee-reviews.acknowledge', employeeReview.id));
    };

    const submitHrAction = (e: React.FormEvent) => {
        e.preventDefault();
        hrForm.transform((data) => ({
            ...data,
            action: hrActionType,
            award_type_id: data.award_type_id === 'none' ? undefined : data.award_type_id,
            warning_type_id: data.warning_type_id === 'none' ? undefined : data.warning_type_id,
            allowance_type_id: data.allowance_type_id === 'none' ? undefined : data.allowance_type_id,
            branch_id: data.branch_id === 'none' ? undefined : data.branch_id,
            department_id: data.department_id === 'none' ? undefined : data.department_id,
            designation_id: data.designation_id === 'none' ? undefined : data.designation_id,
        }));
        hrForm.post(route('performance.employee-reviews.hr-action', employeeReview.id), {
            onSuccess: () => setHrActionType(''),
        });
    };

    const canCalibrate = permissions.includes('calibrate-employee-reviews') || permissions.includes('edit-employee-reviews');
    const indicatorGroups = Object.entries(performanceIndicators || {});

    return (
        <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
            <DialogHeader className="pb-4 border-b">
                <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <div className="p-2 bg-primary/10 rounded-lg">
                            <FileText className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <DialogTitle className="text-xl font-semibold flex items-center gap-2">
                                {t('Employee Appraisal Summary')}
                                {employeeReview?.is_locked && (
                                    <Badge variant="secondary" className="gap-1 text-xs">
                                        <Lock className="h-3 w-3" /> {t('Locked')}
                                    </Badge>
                                )}
                            </DialogTitle>
                            <p className="text-sm text-muted-foreground">
                                {employeeReview?.user?.name || '-'} — {employeeReview?.review_cycle?.name || '-'}
                            </p>
                        </div>
                    </div>
                    {employeeReview?.rating_band && (
                        <Badge className="bg-emerald-100 text-emerald-800 border border-emerald-300">
                            {employeeReview.rating_band}
                        </Badge>
                    )}
                </div>
            </DialogHeader>

            <div className="overflow-y-auto flex-1 p-2 space-y-6">
                {/* Summary Grid */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-muted/40 rounded-lg">
                    <div>
                        <span className="text-xs font-medium text-muted-foreground">{t('Employee')}</span>
                        <p className="text-sm font-semibold">{employeeReview?.user?.name || '-'}</p>
                    </div>
                    <div>
                        <span className="text-xs font-medium text-muted-foreground">{t('Reviewer')}</span>
                        <p className="text-sm font-semibold">{employeeReview?.reviewer?.name || '-'}</p>
                    </div>
                    <div>
                        <span className="text-xs font-medium text-muted-foreground">{t('Review Date')}</span>
                        <p className="text-sm">{employeeReview?.review_date ? formatDate(employeeReview.review_date) : '-'}</p>
                    </div>
                    <div>
                        <span className="text-xs font-medium text-muted-foreground">{t('Status')}</span>
                        <p className="mt-0.5">
                            <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${getStatusColor(employeeReview?.status)}`}>
                                {employeeReview?.status}
                            </span>
                        </p>
                    </div>
                </div>

                {/* Composite Weighted Scorecards */}
                <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <div className="p-3 border rounded-lg bg-white">
                        <div className="text-xs text-muted-foreground">{t('KPI Score')}</div>
                        <div className="text-lg font-bold">{employeeReview?.kpi_score != null ? `${Number(employeeReview.kpi_score).toFixed(1)}%` : '-'}</div>
                    </div>
                    <div className="p-3 border rounded-lg bg-white">
                        <div className="text-xs text-muted-foreground">{t('Goal Score')}</div>
                        <div className="text-lg font-bold">{employeeReview?.goal_score != null ? `${Number(employeeReview.goal_score).toFixed(1)}%` : '-'}</div>
                    </div>
                    <div className="p-3 border rounded-lg bg-white">
                        <div className="text-xs text-muted-foreground">{t('Competency')}</div>
                        <div className="text-lg font-bold">{employeeReview?.competency_score != null ? `${Number(employeeReview.competency_score).toFixed(1)}%` : '-'}</div>
                    </div>
                    <div className="p-3 border rounded-lg bg-white">
                        <div className="text-xs text-muted-foreground">{t('Weighted Rating')}</div>
                        <div className="text-lg font-bold flex items-center gap-1">
                            {averageRating != null ? `${Number(averageRating).toFixed(1)}/5` : '-'}
                        </div>
                    </div>
                    <div className="p-3 border rounded-lg bg-blue-50/50 border-blue-200">
                        <div className="text-xs text-blue-700 font-medium">{t('9-Box Placement')}</div>
                        <div className="text-xs font-semibold text-blue-900 mt-1">{nineBox?.box_label || '-'}</div>
                    </div>
                </div>

                {/* KPI Indicator Breakdown */}
                {indicatorGroups.length > 0 && (
                    <div>
                        <h3 className="text-sm font-semibold mb-2">{t('KPI Indicator Ratings')}</h3>
                        <div className="space-y-3">
                            {indicatorGroups.map(([categoryName, indicators]) => (
                                <div key={categoryName}>
                                    <h4 className="text-xs font-medium text-muted-foreground uppercase tracking-wider mb-1.5">
                                        {categoryName}
                                    </h4>
                                    <div className="space-y-1.5">
                                        {(indicators || []).map((indicator) => (
                                            <div key={indicator.id} className="flex items-center justify-between p-2.5 bg-gray-50 rounded-md">
                                                <div>
                                                    <span className="text-sm font-medium text-gray-900">{indicator.name}</span>
                                                    {indicator.weight && Number(indicator.weight) > 0 ? (
                                                        <span className="ml-2 text-xs text-muted-foreground">({indicator.weight}%)</span>
                                                    ) : null}
                                                </div>
                                                <div className="flex items-center gap-4">
                                                    {indicator.employee_self_rating ? (
                                                        <span className="text-xs text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded">
                                                            {t('Self')}: {indicator.employee_self_rating}/5
                                                        </span>
                                                    ) : null}
                                                    <div className="flex items-center gap-1">
                                                        {renderStars(indicator.user_rating)}
                                                        <span className="text-xs text-gray-700 ml-1 font-medium">{indicator.user_rating}/5</span>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Safe Feedback Rendering via DOMPurify-sanitized RichTextViewer */}
                {(employeeReview?.pros || employeeReview?.cons || employeeReview?.self_pros || employeeReview?.self_cons) && (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {!isRichTextEmpty(employeeReview?.pros) && (
                            <div className="p-3 bg-green-50 rounded border-l-4 border-green-500">
                                <div className="text-xs font-semibold text-green-800 mb-1">{t('Manager Strengths / Pros')}</div>
                                <RichTextViewer content={employeeReview.pros} compact />
                            </div>
                        )}
                        {!isRichTextEmpty(employeeReview?.cons) && (
                            <div className="p-3 bg-amber-50 rounded border-l-4 border-amber-500">
                                <div className="text-xs font-semibold text-amber-800 mb-1">{t('Manager Growth Areas / Cons')}</div>
                                <RichTextViewer content={employeeReview.cons} compact />
                            </div>
                        )}
                        {!isRichTextEmpty(employeeReview?.self_pros) && (
                            <div className="p-3 bg-indigo-50 rounded border-l-4 border-indigo-400">
                                <div className="text-xs font-semibold text-indigo-800 mb-1">{t('Employee Self-Assessment Highlights')}</div>
                                <RichTextViewer content={employeeReview.self_pros} compact />
                            </div>
                        )}
                        {!isRichTextEmpty(employeeReview?.self_cons) && (
                            <div className="p-3 bg-slate-50 rounded border-l-4 border-slate-400">
                                <div className="text-xs font-semibold text-slate-700 mb-1">{t('Employee Self-Identified Support Needs')}</div>
                                <RichTextViewer content={employeeReview.self_cons} compact />
                            </div>
                        )}
                    </div>
                )}

                {/* Employee Goals & 360 Peer Context */}
                {(employeeGoals || []).length > 0 && (
                    <div>
                        <h3 className="text-sm font-semibold mb-2">{t('Linked Employee Goals')}</h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                            {(employeeGoals || []).map((g: any) => (
                                <div key={g.id} className="p-2.5 border rounded flex items-center justify-between text-sm">
                                    <span className="font-medium truncate mr-2">{g.title}</span>
                                    <Badge variant="outline">{Number(g.progress_percentage ?? g.progress ?? 0).toFixed(0)}%</Badge>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {(peerFeedback || []).length > 0 && (
                    <div>
                        <h3 className="text-sm font-semibold mb-2">{t('360° Peer & Team Feedback')}</h3>
                        <div className="space-y-2">
                            {(peerFeedback || []).map((fb: any) => (
                                <div key={fb.id} className="p-2.5 bg-muted/30 rounded text-sm">
                                    <div className="flex justify-between text-xs text-muted-foreground mb-1">
                                        <span>
                                            {fb.is_anonymous ? t('Anonymous Peer') : fb.giver?.name || t('Team Member')} ({fb.relationship_type})
                                        </span>
                                        <span>{fb.rating ? `${fb.rating}/5 ★` : ''}</span>
                                    </div>
                                    <RichTextViewer content={fb.content} compact />
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Employee Acknowledgment */}
                {Number(currentUserId) === Number(employeeReview?.user_id) && !employeeReview?.acknowledged_at && (
                    <form onSubmit={submitAck} className="p-4 border rounded-lg bg-slate-50 space-y-3">
                        <div className="flex items-center gap-2 text-sm font-semibold">
                            <CheckCircle2 className="h-4 w-4 text-green-600" />
                            {t('Employee Acknowledgment Sign-Off')}
                        </div>
                        <RichTextEditor
                            variant="compact"
                            minHeight="90px"
                            content={ackForm.data.employee_acknowledgment_notes}
                            onChange={(value) => ackForm.setData('employee_acknowledgment_notes', value)}
                            placeholder={t('Optional acknowledgment remarks...')}
                        />
                        <Button type="submit" size="sm" disabled={ackForm.processing}>
                            {t('Acknowledge Appraisal')}
                        </Button>
                    </form>
                )}

                {/* HR Calibration & Lock */}
                {canCalibrate && (
                    <div className="p-4 border rounded-lg bg-purple-50/40 space-y-3">
                        <div className="text-sm font-semibold text-purple-900">{t('HR Calibration & Final Lock')}</div>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <Label className="text-xs">{t('Calibrated Score (1-5)')}</Label>
                                <Input
                                    type="number"
                                    step="0.1"
                                    min="1"
                                    max="5"
                                    value={calibrateForm.data.weighted_rating}
                                    onChange={(e) => calibrateForm.setData('weighted_rating', e.target.value)}
                                />
                            </div>
                            <div className="md:col-span-2">
                                <Label className="text-xs">{t('Calibration Justification')}</Label>
                                <Input
                                    value={calibrateForm.data.calibration_notes}
                                    onChange={(e) => calibrateForm.setData('calibration_notes', e.target.value)}
                                    placeholder={t('Committee adjustment notes...')}
                                />
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <Button type="button" size="sm" variant="outline" disabled={calibrateForm.processing} onClick={(e) => submitCalibration(e, false)}>
                                {t('Save Calibration')}
                            </Button>
                            <Button type="button" size="sm" disabled={calibrateForm.processing} onClick={(e) => submitCalibration(e, !employeeReview?.is_locked)}>
                                <Lock className="h-3.5 w-3.5 mr-1" />
                                {employeeReview?.is_locked ? t('Unlock Appraisal') : t('Calibrate & Lock')}
                            </Button>
                        </div>
                    </div>
                )}

                {/* Phase 3: Direct HRM Lifecycle Actions */}
                {canCalibrate && hrmContext?.hrm_active && (
                    <div className="p-4 border rounded-lg bg-emerald-50/30 space-y-3">
                        <div className="flex items-center justify-between flex-wrap gap-2">
                            <span className="text-sm font-semibold text-emerald-950">{t('HRM Lifecycle Actions (Payroll / Promotions / PIP)')}</span>
                            <div className="flex flex-wrap gap-1.5">
                                <Button type="button" size="sm" variant={hrActionType === 'promotion' ? 'default' : 'outline'} onClick={() => setHrActionType('promotion')}>
                                    <TrendingUp className="h-3.5 w-3.5 mr-1" /> {t('Nominate Promotion')}
                                </Button>
                                <Button type="button" size="sm" variant={hrActionType === 'award' ? 'default' : 'outline'} onClick={() => setHrActionType('award')}>
                                    <Award className="h-3.5 w-3.5 mr-1" /> {t('Grant Award')}
                                </Button>
                                <Button type="button" size="sm" variant={hrActionType === 'bonus_allowance' ? 'default' : 'outline'} onClick={() => setHrActionType('bonus_allowance')}>
                                    <DollarSign className="h-3.5 w-3.5 mr-1" /> {t('Merit Bonus')}
                                </Button>
                                <Button type="button" size="sm" variant={hrActionType === 'warning' ? 'destructive' : 'outline'} onClick={() => setHrActionType('warning')}>
                                    <AlertTriangle className="h-3.5 w-3.5 mr-1" /> {t('Initiate PIP')}
                                </Button>
                            </div>
                        </div>

                        {hrActionType && (
                            <form onSubmit={submitHrAction} className="space-y-3 pt-2 border-t">
                                {hrActionType === 'award' && (
                                    <div>
                                        <Label className="text-xs">{t('Award Type')}</Label>
                                        <Select value={hrForm.data.award_type_id} onValueChange={(v) => hrForm.setData('award_type_id', v)}>
                                            <SelectTrigger><SelectValue placeholder={t('Select award type')} /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">{t('Select award type')}</SelectItem>
                                                {(hrmContext?.award_types || []).map((a: any) => (
                                                    <SelectItem key={a.id} value={a.id.toString()}>{a.name}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}
                                {hrActionType === 'warning' && (
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <Label className="text-xs">{t('Warning / PIP Type')}</Label>
                                            <Select value={hrForm.data.warning_type_id} onValueChange={(v) => hrForm.setData('warning_type_id', v)}>
                                                <SelectTrigger><SelectValue placeholder={t('Select warning type')} /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="none">{t('Select warning type')}</SelectItem>
                                                    {(hrmContext?.warning_types || []).map((w: any) => (
                                                        <SelectItem key={w.id} value={w.id.toString()}>{w.warning_type_name}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div>
                                            <Label className="text-xs">{t('Subject')}</Label>
                                            <Input value={hrForm.data.subject} onChange={(e) => hrForm.setData('subject', e.target.value)} />
                                        </div>
                                    </div>
                                )}
                                {hrActionType === 'bonus_allowance' && (
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <div>
                                            <Label className="text-xs">{t('Allowance Type')}</Label>
                                            <Select value={hrForm.data.allowance_type_id} onValueChange={(v) => hrForm.setData('allowance_type_id', v)}>
                                                <SelectTrigger><SelectValue placeholder={t('Select allowance type')} /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="none">{t('Select allowance type')}</SelectItem>
                                                    {(hrmContext?.allowance_types || []).map((tItem: any) => (
                                                        <SelectItem key={tItem.id} value={tItem.id.toString()}>{tItem.name}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div>
                                            <Label className="text-xs">{t('Type')}</Label>
                                            <Select value={hrForm.data.bonus_type} onValueChange={(v) => hrForm.setData('bonus_type', v)}>
                                                <SelectTrigger><SelectValue /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="fixed">{t('Fixed Amount')}</SelectItem>
                                                    <SelectItem value="percentage">{t('Percentage (%)')}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div>
                                            <Label className="text-xs">{t('Amount')}</Label>
                                            <Input type="number" min="0" step="0.01" value={hrForm.data.amount} onChange={(e) => hrForm.setData('amount', e.target.value)} required />
                                        </div>
                                    </div>
                                )}
                                {hrActionType === 'promotion' && (
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <Label className="text-xs">{t('Target Department')}</Label>
                                            <Select value={hrForm.data.department_id} onValueChange={(v) => hrForm.setData('department_id', v)}>
                                                <SelectTrigger><SelectValue placeholder={t('Keep current department')} /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="none">{t('Keep current department')}</SelectItem>
                                                    {(hrmContext?.departments || []).map((d: any) => (
                                                        <SelectItem key={d.id} value={d.id.toString()}>{d.department_name}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div>
                                            <Label className="text-xs">{t('Target Designation')}</Label>
                                            <Select value={hrForm.data.designation_id} onValueChange={(v) => hrForm.setData('designation_id', v)}>
                                                <SelectTrigger><SelectValue placeholder={t('Keep current designation')} /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="none">{t('Keep current designation')}</SelectItem>
                                                    {(hrmContext?.designations || []).map((d: any) => (
                                                        <SelectItem key={d.id} value={d.id.toString()}>{d.designation_name}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                )}
                                <div>
                                    <Label className="text-xs">{t('Justification / Notes')}</Label>
                                    <Input value={hrForm.data.reason} onChange={(e) => hrForm.setData('reason', e.target.value)} placeholder={t('Describe HR action reason...')} />
                                </div>
                                <div className="flex gap-2">
                                    <Button type="submit" size="sm" disabled={hrForm.processing}>{t('Execute HRM Action')}</Button>
                                    <Button type="button" size="sm" variant="ghost" onClick={() => setHrActionType('')}>{t('Cancel')}</Button>
                                </div>
                            </form>
                        )}
                    </div>
                )}
            </div>
        </DialogContent>
    );
}
