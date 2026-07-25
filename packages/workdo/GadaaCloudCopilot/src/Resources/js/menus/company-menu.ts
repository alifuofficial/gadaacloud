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

    return [
        {
            title: t('GadaaCloud Copilot'),
            icon: Bot,
            permission: 'manage-settings',
            order: 5,
            children: [
                {
                    title: t('Overview & AI Insights'),
                    href: indexHref,
                    icon: LayoutDashboard,
                    permission: 'manage-settings',
                },
                {
                    title: t('AI Model Setup'),
                    href: setupHref,
                    icon: Cpu,
                    permission: 'manage-settings',
                },
            ],
        },
    ];
};
