import { NavItem } from '@/types';
import { Ship } from 'lucide-react';

export const importExportMenu = (t: (key: string) => string): NavItem[] => [
    {
        title: t('Import & Export'),
        href: route('settings.import-export.index'),
        icon: Ship,
        permission: 'manage-settings',
        order: 2985,
    },
];
