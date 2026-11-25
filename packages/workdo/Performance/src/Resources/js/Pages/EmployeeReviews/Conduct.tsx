import { useState } from 'react';
import { Head, usePage, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Checkbox } from "@/components/ui/checkbox";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Star, Lock, Save, CheckCircle2 } from "lucide-react";
import InputError from "@/components/ui/input-error";

interface ConductProps {
    employeeReview: any;
    performanceIndicators: { [categoryName: string]: any[] };
    competencies?: any[];
    employeeGoals?: any[];
    existingRatings: { [key: string]: number };
    existingSelfRatings?: { [key: string]: number };
    existingCompetencyRatings?: { [key: string]: number };
    isReviewedEmployee?: boolean;
}

export default function Conduct() {
    const { t } = useTranslation();
    const {
        employeeReview,
        performanceIndicators = {},
        competencies = [],
        employeeGoals = [],
        existingRatings = {},
        existingSelfRatings = {},
        existingCompetencyRatings = {},
        isReviewedEmployee = false,
    } = usePage<ConductProps>().props;

    const [activeTab, setActiveTab] = useState<'manager' | 'self'>(
        isReviewedEmployee && employeeReview?.workflow_stage === 'self_assessment' ? 'self' : 'manager'
    );

    const managerForm = useForm({
        ratings: existingRatings || {},
        competency_ratings: existingCompetencyRatings || {},
        pros: employeeReview?.pros || '',
        cons: employeeReview?.cons || '',
        submission_mode: 'submit' as 'draft' | 'submit',
        pip_recommended: Boolean(employeeReview?.pip_recommended),
        promotion_recommended: Boolean(employeeReview?.promotion_recommended),
        bonus_recommendation_amount: employeeReview?.bonus_recommendation_amount?.toString() || '',
        bonus_recommendation_type: employeeReview?.bonus_recommendation_type || 'fixed',
    });

    const selfForm = useForm({
        self_ratings: existingSelfRatings || {},
        self_pros: employeeReview?.self_pros || '',
        self_cons: employeeReview?.self_cons || '',
    });

    const renderStars = (currentRating: number, onSelect: (star: number) => void, disabled = false) => (
        <div className="flex gap-1">
            {[1, 2, 3, 4, 5].map((star) => (
                <button
                    key={star}
                    type="button"
                    disabled={disabled}
                    onClick={() => onSelect(star)}
                    className="focus:outline-none disabled:opacity-60"
                >
                    <Star
                        className={`h-6 w-6 ${
                            star <= currentRating ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'
                        }`}
                    />
                </button>
            ))}
        </div>
    );

    const handleManagerSubmit = (mode: 'draft' | 'submit') => {
        managerForm.transform((data) => ({ ...data, submission_mode: mode }));
        managerForm.post(route('performance.employee-reviews.conduct.store', employeeReview.id));
    };

    const handleSelfSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        selfForm.post(route('performance.employee-reviews.self-assess', employeeReview.id));
    };

    const indicatorEntries = Object.entries(performanceIndicators || {});
    const isLocked = Boolean(employeeReview?.is_locked);

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Performance') },
                { label: t('Employee Reviews'), url: route('performance.employee-reviews.index') },
                { label: t('Conduct Review') },
            ]}
            pageTitle={t('Conduct Performance Appraisal')}
            backUrl={route('performance.employee-reviews.index')}
        >
            <Head title={t('Conduct Review')} />

            <div className="space-y-6">
                {isLocked && (
                    <div className="p-4 rounded-lg bg-amber-50 border border-amber-200 flex items-center gap-2 text-amber-900 text-sm">
                        <Lock className="h-4 w-4" />
                        {t('This appraisal has been calibrated and locked by HR. Ratings are read-only.')}
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Appraisal Context')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <span className="font-medium text-muted-foreground">{t('Employee')}:</span>
                                <p className="font-semibold">{employeeReview?.user?.name || '-'}</p>
                            </div>
                            <div>
                                <span className="font-medium text-muted-foreground">{t('Reviewer')}:</span>
                                <p className="font-semibold">{employeeReview?.reviewer?.name || '-'}</p>
                            </div>
                            <div>
                                <span className="font-medium text-muted-foreground">{t('Review Cycle')}:</span>
                                <p className="font-semibold">{employeeReview?.review_cycle?.name || '-'}</p>
                            </div>
                            <div>
                                <span className="font-medium text-muted-foreground">{t('Template / Goals')}:</span>
                                <p className="font-semibold">
                                    {employeeReview?.review_template?.name || t('Standard KPI Set')} ({(employeeGoals || []).length} {t('Goals')})
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Tabs value={activeTab} onValueChange={(v) => setActiveTab(v as 'manager' | 'self')}>
                    <TabsList>
                        <TabsTrigger value="manager">{t('Manager Evaluation')}</TabsTrigger>
                        <TabsTrigger value="self">{t('Employee Self-Assessment')}</TabsTrigger>
                    </TabsList>

                    <TabsContent value="manager" className="space-y-6 mt-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('KPI Indicator Ratings')}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                {indicatorEntries.map(([categoryName, indicators]) => (
                                    <div key={categoryName} className="space-y-3">
                                        <h3 className="text-base font-semibold text-gray-900">
                                            {categoryName || t('Uncategorized')}
                                        </h3>
                                        {(indicators || []).map((indicator) => (
                                            <div key={indicator.id} className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                                <div className="flex-1">
                                                    <div className="text-sm font-medium text-gray-800">{indicator.name}</div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {indicator.target_value ? `${t('Target')}: ${indicator.target_value} ${indicator.measurement_unit || ''}` : indicator.measurement_unit}
                                                        {indicator.pivot?.weight || indicator.weight ? ` • ${t('Weight')}: ${indicator.pivot?.weight || indicator.weight}%` : ''}
                                                        {selfForm.data.self_ratings?.[indicator.id] ? ` • ${t('Employee Self Rating')}: ${selfForm.data.self_ratings[indicator.id]}/5` : ''}
                                                    </div>
                                                </div>
                                                <div className="ml-4">
                                                    {renderStars(
                                                        managerForm.data.ratings?.[indicator.id] || 0,
                                                        (star) =>
                                                            managerForm.setData('ratings', {
                                                                ...managerForm.data.ratings,
                                                                [indicator.id]: star,
                                                            }),
                                                        isLocked
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ))}

                                {(competencies || []).length > 0 && (
                                    <div className="space-y-3 pt-4 border-t">
                                        <h3 className="text-base font-semibold text-gray-900">{t('Behavioral Competency Ratings')}</h3>
                                        {(competencies || []).map((comp: any) => (
                                            <div key={comp.id} className="flex items-center justify-between p-3 bg-blue-50/40 rounded-lg">
                                                <div>
                                                    <div className="text-sm font-medium">{comp.name}</div>
                                                    <div className="text-xs text-muted-foreground">{comp.category?.name}</div>
                                                </div>
                                                {renderStars(
                                                    managerForm.data.competency_ratings?.[comp.id] || 0,
                                                    (star) =>
                                                        managerForm.setData('competency_ratings', {
                                                            ...managerForm.data.competency_ratings,
                                                            [comp.id]: star,
                                                        }),
                                                    isLocked
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
                                    <div>
                                        <Label>{t('Strengths / Pros')}</Label>
                                        <RichTextEditor
                                            content={managerForm.data.pros}
                                            onChange={(content) => managerForm.setData('pros', content)}
                                            placeholder={t('Enter positive feedback')}
                                        />
                                        <InputError message={managerForm.errors.pros} />
                                    </div>
                                    <div>
                                        <Label>{t('Development Areas / Cons')}</Label>
                                        <RichTextEditor
                                            content={managerForm.data.cons}
                                            onChange={(content) => managerForm.setData('cons', content)}
                                            placeholder={t('Enter areas for improvement')}
                                        />
                                        <InputError message={managerForm.errors.cons} />
                                    </div>
                                </div>

                                {/* HRM Outcomes Recommendations */}
                                <div className="p-4 border rounded-lg bg-muted/20 space-y-3">
                                    <div className="text-sm font-semibold">{t('Outcome Recommendations (for HR Review)')}</div>
                                    <div className="flex flex-wrap gap-6 items-center">
                                        <label className="flex items-center gap-2 text-sm cursor-pointer">
                                            <Checkbox
                                                checked={managerForm.data.promotion_recommended}
                                                onCheckedChange={(v) => managerForm.setData('promotion_recommended', Boolean(v))}
                                                disabled={isLocked}
                                            />
                                            {t('Recommend for Promotion')}
                                        </label>
                                        <label className="flex items-center gap-2 text-sm cursor-pointer">
                                            <Checkbox
                                                checked={managerForm.data.pip_recommended}
                                                onCheckedChange={(v) => managerForm.setData('pip_recommended', Boolean(v))}
                                                disabled={isLocked}
                                            />
                                            {t('Recommend PIP / Coaching')}
                                        </label>
                                        <div className="flex items-center gap-2">
                                            <Label className="text-xs whitespace-nowrap">{t('Recommended Bonus')}:</Label>
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                className="w-32 h-8"
                                                disabled={isLocked}
                                                value={managerForm.data.bonus_recommendation_amount}
                                                onChange={(e) => managerForm.setData('bonus_recommendation_amount', e.target.value)}
                                                placeholder="0.00"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div className="flex justify-end gap-2 pt-4">
                                    <Button type="button" variant="outline" onClick={() => window.history.back()}>
                                        {t('Cancel')}
                                    </Button>
                                    {!isLocked && (
                                        <>
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                disabled={managerForm.processing}
                                                onClick={() => handleManagerSubmit('draft')}
                                            >
                                                <Save className="h-4 w-4 mr-1" /> {t('Save Draft')}
                                            </Button>
                                            <Button
                                                type="button"
                                                disabled={managerForm.processing}
                                                onClick={() => handleManagerSubmit('submit')}
                                            >
                                                <CheckCircle2 className="h-4 w-4 mr-1" />
                                                {managerForm.processing ? t('Submitting...') : t('Submit Appraisal')}
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="self" className="space-y-6 mt-4">
                        <form onSubmit={handleSelfSubmit}>
                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('Employee Self-Assessment')}</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    {indicatorEntries.map(([categoryName, indicators]) => (
                                        <div key={categoryName} className="space-y-3">
                                            <h3 className="text-base font-semibold">{categoryName || t('Uncategorized')}</h3>
                                            {(indicators || []).map((indicator) => (
                                                <div key={indicator.id} className="flex items-center justify-between p-4 bg-indigo-50/40 rounded-lg">
                                                    <span className="text-sm font-medium">{indicator.name}</span>
                                                    {renderStars(
                                                        selfForm.data.self_ratings?.[indicator.id] || 0,
                                                        (star) =>
                                                            selfForm.setData('self_ratings', {
                                                                ...selfForm.data.self_ratings,
                                                                [indicator.id]: star,
                                                            }),
                                                        isLocked
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    ))}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <Label>{t('Key Achievements (Self Reflection)')}</Label>
                                            <RichTextEditor
                                                content={selfForm.data.self_pros}
                                                onChange={(content) => selfForm.setData('self_pros', content)}
                                                placeholder={t('Highlight your achievements...')}
                                            />
                                        </div>
                                        <div>
                                            <Label>{t('Challenges & Support Needed')}</Label>
                                            <RichTextEditor
                                                content={selfForm.data.self_cons}
                                                onChange={(content) => selfForm.setData('self_cons', content)}
                                                placeholder={t('Describe blockers or training needs...')}
                                            />
                                        </div>
                                    </div>
                                    {!isLocked && (
                                        <div className="flex justify-end gap-2">
                                            <Button type="submit" disabled={selfForm.processing}>
                                                {selfForm.processing ? t('Submitting...') : t('Submit Self-Assessment')}
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </form>
                    </TabsContent>
                </Tabs>
            </div>
        </AuthenticatedLayout>
    );
}
