import { TrendingUp } from 'lucide-react';

declare global {
    function route(name: string): string;
}

export const performanceCompanyMenu = (t: (key: string) => string) => [
    {
        title: t('Performance'),
        icon: TrendingUp,
        permission: 'manage-performance',
        parent: '',
        order: 451,
        children: [
            {
                title: t('Dashboard'),
                href: route('performance.dashboard'),
                permission: 'manage-performance-dashboard',
            },
            {
                title: t('Performance Indicators'),
                href: route('performance.indicators.index'),
                permission: 'manage-performance-indicators',
            },
            {
                title: t('Employee Goals'),
                href: route('performance.employee-goals.index'),
                permission: 'manage-employee-goals',
            },
            {
                title: t('Review Cycles'),
                href: route('performance.review-cycles.index'),
                permission: 'manage-review-cycles',
            },
            {
                title: t('Review Templates'),
                href: route('performance.review-templates.index'),
                permission: 'manage-review-templates',
            },
            {
                title: t('Employee Reviews'),
                href: route('performance.employee-reviews.index'),
                permission: 'manage-employee-reviews',
            },
            {
                title: t('Competencies'),
                href: route('performance.competencies.index'),
                permission: 'manage-competencies',
            },
            {
                title: t('Competency Assessments'),
                href: route('performance.employee-competencies.index'),
                permission: 'manage-employee-competencies',
            },
            {
                title: t('360° Feedback'),
                href: route('performance.feedback.index'),
                permission: 'manage-employee-feedback',
            },
            {
                title: t('System Setup'),
                href: route('performance.indicator-categories.index'),
                permission: 'manage-performance-system-setup',
                activePaths: [
                    route('performance.goal-types.index'),
                    route('performance.competency-categories.index'),
                    route('performance.feedback-types.index'),
                ],
            },
        ],
    },
];
