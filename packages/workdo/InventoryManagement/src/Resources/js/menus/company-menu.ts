import { NavItem } from '@/types';
import { PackageCheck } from 'lucide-react';

export const inventoryManagementMenu = (t: (key: string) => string): NavItem[] => [
    {
        title: t('Inventory Management'),
        href: route('settings.inventory.index'),
        icon: PackageCheck,
        permission: 'manage-settings',
        order: 2986,
    },
];
