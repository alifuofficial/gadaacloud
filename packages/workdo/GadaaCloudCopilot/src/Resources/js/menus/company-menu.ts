import { NavItem } from '@/types';
import { Bot } from 'lucide-react';

export const gadaaCloudCopilotMenu = (t: (key: string) => string): NavItem[] => {
    let copilotHref = '/settings/copilot';
    try {
        if (typeof route === 'function') {
            copilotHref = route('settings.copilot.index');
        }
    } catch (e) {}

    return [
        {
            title: t('GadaaCloud Copilot'),
            href: copilotHref,
            icon: Bot,
            permission: 'manage-settings',
            order: 5,
        },
    ];
};
