import { NavItem } from '@/types';
import { Bot } from 'lucide-react';

export const gadaaCloudCopilotMenu = (t: (key: string) => string): NavItem[] => [
    {
        title: t('GadaaCloud Copilot'),
        href: route('settings.copilot.index'),
        icon: Bot,
        permission: 'manage-settings',
        order: 5,
    },
];
