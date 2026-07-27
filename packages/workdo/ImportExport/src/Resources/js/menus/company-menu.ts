import { NavItem } from '@/types';
import { Ship, LayoutDashboard, Coins, Anchor, Calculator, FileText, Award } from 'lucide-react';

export const importExportMenu = (t: (key: string) => string): NavItem[] => {
    let dashboardHref = '/import-export/dashboard';
    let indexHref = '/settings/import-export';

    try {
        if (typeof route === 'function') {
            dashboardHref = route('import-export.dashboard');
            indexHref = route('settings.import-export.index');
        }
    } catch (e) {}

    return [
        {
            title: t('Import & Export Operations'),
            icon: Ship,
            permission: 'manage-settings',
            order: 10,
            children: [
                {
                    title: t('IE Dashboard'),
                    href: dashboardHref,
                    icon: LayoutDashboard,
                    permission: 'manage-settings',
                },
                {
                    title: t('NBE Forex Allocation Queue'),
                    href: `${indexHref}?tab=forex`,
                    icon: Coins,
                    permission: 'manage-settings',
                },
                {
                    title: t('Djibouti Port Storage & Demurrage'),
                    href: `${indexHref}?tab=djibouti`,
                    icon: Anchor,
                    permission: 'manage-settings',
                },
                {
                    title: t('ECC Customs Duty Calculator'),
                    href: `${indexHref}?tab=ecc`,
                    icon: Calculator,
                    permission: 'manage-settings',
                },
                {
                    title: t('ECX & Coffee Export Contracts'),
                    href: `${indexHref}?tab=ecx`,
                    icon: Award,
                    permission: 'manage-settings',
                },
                {
                    title: t('Letters of Credit (LC) & Landed Costs'),
                    href: `${indexHref}?tab=lcs`,
                    icon: FileText,
                    permission: 'manage-settings',
                },
            ],
        },
    ];
};
