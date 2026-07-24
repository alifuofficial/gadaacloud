import { NavItem } from '@/types';
import { Calendar } from 'lucide-react';

export const ethiopianCalendarMenu = (t: (key: string) => string): NavItem[] => [
    {
        title: t('Ethiopian Calendar'),
        href: route('settings.index'),
        icon: Calendar,
        permission: 'manage-settings',
        order: 2987,
    },
];
