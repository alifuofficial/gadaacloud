import { Calendar } from 'lucide-react';
import { NavItem } from '@/types';

export const ethiopianCalendarMenu = (t: (key: string) => string): NavItem[] => [
    {
        title: t('Ethiopian Calendar'),
        href: route('ethiopian-calendar.index'),
        icon: Calendar,
        permission: 'manage-ethiopian-calendar',
        order: 2987,
    },
];
