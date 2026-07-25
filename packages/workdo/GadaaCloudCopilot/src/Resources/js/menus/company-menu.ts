import { NavItem } from '@/types';
import { Bot, Cpu, LayoutDashboard } from 'lucide-react';

export const gadaaCloudCopilotMenu = (t: (key: string) => string): NavItem[] => {
    let indexHref = '/settings/copilot';
    let setupHref = '/settings/copilot/setup';

    try {
        if (typeof route === 'function') {
            indexHref = route('settings.copilot.index');
            setupHref = route('settings.copilot.setup');
        }
    } catch (e) {}

    // Check if current user is superadmin from global page props if available
    let isSuperAdmin = false;
    try {
        const auth = (window as any).page?.props?.auth || {};
        isSuperAdmin = auth?.user?.type === 'superadmin';
    } catch (e) {}

    const children: NavItem[] = [
        {
            title: t('Overview & AI Insights'),
            href: indexHref,
            icon: LayoutDashboard,
            permission: 'manage-settings',
        },
    ];

    if (isSuperAdmin) {
        children.push({
            title: t('Global AI Setup & Token Pricing'),
            href: setupHref,
            icon: Cpu,
            permission: 'manage-settings',
        });
    }

    return [
        {
            title: t('GadaaCloud Copilot'),
            icon: Bot,
            permission: 'manage-settings',
            order: 5,
            children: children,
        },
    ];
};
