import { Head, Link, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { BarChart3, CheckCircle2, Target, Users, AlertTriangle, Award, Calendar, Star } from 'lucide-react';
import { formatDate } from '@/utils/helpers';

export default function Index() {
    const { t } = useTranslation();
    const {
        stats = {},
        ratingDistribution = [],
        workflowBreakdown = [],
        nineBoxGrid = [],
        overdueGoals = [],
        recentReviews = [],
        ethiopianFiscalYear,
    } = usePage<any>().props;

    const maxBandCount = Math.max(1, ...(ratingDistribution || []).map((r: any) => Number(r.count || 0)));

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Performance') }, { label: t('Executive Dashboard') }]}
            pageTitle={t('Performance Intelligence Dashboard')}
            pageActions={
                <div className="flex items-center gap-2">
                    {ethiopianFiscalYear && (
                        <Badge variant="outline" className="bg-amber-50 text-amber-900 border-amber-200 gap-1">
                            <Calendar className="h-3.5 w-3.5" /> FY {ethiopianFiscalYear} E.C.
                        </Badge>
                    )}
                    <Button asChild size="sm">
                        <Link href={route('performance.employee-reviews.index')}>{t('Manage Appraisals')}</Link>
                    </Button>
                </div>
            }
        >
            <Head title={t('Performance Dashboard')} />

            <div className="space-y-6">
                {/* KPI Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card>
                        <CardContent className="p-5 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground uppercase">{t('Appraisal Completion')}</p>
                                <p className="text-2xl font-bold mt-1">{stats.completion_rate ?? 0}%</p>
                                <p className="text-xs text-muted-foreground mt-1">
                                    {stats.completed_reviews ?? 0} / {stats.total_reviews ?? 0} {t('completed')}
                                </p>
                            </div>
                            <div className="p-3 bg-emerald-100 rounded-xl text-emerald-700">
                                <CheckCircle2 className="h-6 w-6" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-5 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground uppercase">{t('Average Rating')}</p>
                                <p className="text-2xl font-bold mt-1">{Number(stats.average_rating ?? 0).toFixed(1)} / 5</p>
                                <p className="text-xs text-muted-foreground mt-1">
                                    {stats.active_cycles ?? 0} {t('active cycle(s)')}
                                </p>
                            </div>
                            <div className="p-3 bg-amber-100 rounded-xl text-amber-700">
                                <Star className="h-6 w-6" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-5 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground uppercase">{t('Employee Goals')}</p>
                                <p className="text-2xl font-bold mt-1">{stats.completed_goals ?? 0} / {stats.total_goals ?? 0}</p>
                                <p className="text-xs text-red-600 mt-1">
                                    {stats.overdue_goals_count ?? 0} {t('overdue')}
                                </p>
                            </div>
                            <div className="p-3 bg-blue-100 rounded-xl text-blue-700">
                                <Target className="h-6 w-6" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-5 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-medium text-muted-foreground uppercase">{t('360° & Competencies')}</p>
                                <p className="text-2xl font-bold mt-1">{stats.feedback_count ?? 0}</p>
                                <p className="text-xs text-muted-foreground mt-1">
                                    {stats.competencies_count ?? 0} {t('competencies')} • {stats.templates_count ?? 0} {t('templates')}
                                </p>
                            </div>
                            <div className="p-3 bg-purple-100 rounded-xl text-purple-700">
                                <Award className="h-6 w-6" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Distribution & Workflow Stage Breakdown */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <BarChart3 className="h-4 w-4 text-primary" /> {t('Rating Calibration Distribution')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {(ratingDistribution || []).map((item: any) => {
                                const pct = Math.round(((item.count || 0) / maxBandCount) * 100);
                                return (
                                    <div key={item.band} className="space-y-1">
                                        <div className="flex justify-between text-xs font-medium">
                                            <span>{item.band}</span>
                                            <span>{item.count}</span>
                                        </div>
                                        <div className="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                                            <div className="h-full bg-primary rounded-full transition-all" style={{ width: `${pct}%` }} />
                                        </div>
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2">
                                <Users className="h-4 w-4 text-primary" /> {t('Appraisal Pipeline Stages')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {(workflowBreakdown || []).map((stage: any) => (
                                <div key={stage.stage} className="flex items-center justify-between p-3 bg-muted/40 rounded-lg">
                                    <span className="text-sm font-medium">{stage.stage}</span>
                                    <Badge variant="secondary">{stage.count}</Badge>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                {/* 9-Box Talent Matrix */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{t('9-Box Talent Calibration Grid (Performance vs. Competency)')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                            {(nineBoxGrid || []).map((box: any) => (
                                <div
                                    key={box.key}
                                    className={`p-3 rounded-lg border ${
                                        box.performance_tier === 'high' && box.potential_tier === 'high'
                                            ? 'bg-emerald-50 border-emerald-200'
                                            : box.performance_tier === 'low' && box.potential_tier === 'low'
                                            ? 'bg-red-50 border-red-200'
                                            : 'bg-gray-50/60 border-gray-200'
                                    }`}
                                >
                                    <div className="flex justify-between items-center mb-1.5">
                                        <span className="text-xs font-semibold uppercase tracking-wide text-gray-700">
                                            Perf: {box.performance_tier} • Comp: {box.potential_tier}
                                        </span>
                                        <Badge variant="outline">{box.count}</Badge>
                                    </div>
                                    {(box.employees || []).length > 0 ? (
                                        <div className="space-y-1">
                                            {(box.employees || []).map((emp: any) => (
                                                <div key={emp.id} className="text-xs flex justify-between text-gray-800">
                                                    <span className="truncate">{emp.name}</span>
                                                    <span className="font-medium">{emp.rating ?? '-'}★</span>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">{t('No employees in tier')}</p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Overdue Goals & Recent Appraisals */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base flex items-center gap-2 text-red-700">
                                <AlertTriangle className="h-4 w-4" /> {t('Overdue Goals Watchlist')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {(overdueGoals || []).length > 0 ? (
                                <div className="space-y-2">
                                    {(overdueGoals || []).map((goal: any) => (
                                        <div key={goal.id} className="flex items-center justify-between p-2.5 border rounded-lg text-sm">
                                            <div>
                                                <div className="font-medium">{goal.title}</div>
                                                <div className="text-xs text-muted-foreground">
                                                    {goal.employee?.name} • {t('Due')}: {goal.end_date ? formatDate(goal.end_date) : '-'}
                                                </div>
                                            </div>
                                            <Badge variant="destructive">{goal.progress_percentage ?? goal.progress ?? 0}%</Badge>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">{t('All tracked goals are currently on schedule.')}</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('Recent Employee Appraisals')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {(recentReviews || []).length > 0 ? (
                                <div className="space-y-2">
                                    {(recentReviews || []).map((review: any) => (
                                        <div key={review.id} className="flex items-center justify-between p-2.5 border rounded-lg text-sm">
                                            <div>
                                                <div className="font-medium">{review.user?.name || '-'}</div>
                                                <div className="text-xs text-muted-foreground">
                                                    {review.review_cycle?.name || '-'} • {t('Reviewer')}: {review.reviewer?.name || '-'}
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {review.average_rating != null && (
                                                    <span className="text-xs font-semibold">{review.average_rating}/5 ★</span>
                                                )}
                                                <Badge variant="outline">{review.status}</Badge>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">{t('No appraisals recorded yet.')}</p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
