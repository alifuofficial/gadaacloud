import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Head, Link, usePage } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Lock } from "lucide-react";

export default function Dashboard() {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const user = auth?.user;

    // Check if the plan is expired or inactive (active_plan === 0 or plan_expire_date is past)
    const isExpired = user?.plan_expire_date ? new Date(user.plan_expire_date) < new Date() : false;
    const isInactive = !user?.active_plan || user?.active_plan === 0 || user?.active_plan === '0';
    const isNotSubscribedOrExpired = isInactive || isExpired;

    return (
        <AuthenticatedLayout
            header={t('Dashboard')}
        >
            <Head title={t('Dashboard')} />

            {isNotSubscribedOrExpired ? (
                <div className="flex flex-col items-center justify-center min-h-[400px] p-6 bg-slate-50 dark:bg-slate-900 rounded-2xl border border-dashed border-gray-200 dark:border-gray-800 text-center animate-in fade-in duration-300">
                    <div className="w-16 h-16 bg-primary/10 dark:bg-primary/20 rounded-full flex items-center justify-center mb-6">
                        <Lock className="w-8 h-8 text-primary animate-pulse" />
                    </div>
                    <h3 className="text-xl font-bold text-gray-900 dark:text-white mb-2">
                        {t('Workspace Inactive')}
                    </h3>
                    <p className="text-sm text-gray-600 dark:text-gray-400 max-w-md mb-6 leading-relaxed">
                        {t('Subscribe to a subscription plan to get detailed analytics and unlock full management tools for your business.')}
                    </p>
                    <Link href={route('plans.index')}>
                        <Button className="bg-primary text-white hover:bg-primary/95 transition-all duration-200 shadow-md hover:shadow-lg transform hover:scale-[1.02]">
                            {t('Choose a Subscription Plan')}
                        </Button>
                    </Link>
                </div>
            ) : (
                <div className="flex flex-1 flex-col gap-4 h-full">
                    <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                        <div className="aspect-video rounded-xl bg-muted/50" />
                        <div className="aspect-video rounded-xl bg-muted/50" />
                        <div className="aspect-video rounded-xl bg-muted/50" />
                    </div>
                    <div className="flex-1 rounded-xl bg-muted/50 h-full" />
                </div>
            )}
        </AuthenticatedLayout>
    );
}