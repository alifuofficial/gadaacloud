import { NavItem } from '@/types';
import { ArrowRightLeft, Database, Sparkles, Layers, History } from 'lucide-react';

export const erpMigrateMenu = (t: (key: string) => string): NavItem[] => {
    let dashboardHref = '/erp-migrate';
    let wizardHref = '/erp-migrate/wizard';
    let connectionsHref = '/erp-migrate/connections';
    let historyHref = '/erp-migrate/history';

    try {
        if (typeof route === 'function') {
            dashboardHref = route('erp-migrate.index');
            wizardHref = route('erp-migrate.wizard');
            connectionsHref = route('erp-migrate.connections');
            historyHref = route('erp-migrate.history');
        }
    } catch (e) {}

    return [
        {
            title: t('ERP Data Migration'),
            icon: ArrowRightLeft,
            permission: 'manage-settings',
            order: 2955,
            children: [
                {
                    title: t('Migration Studio'),
                    href: dashboardHref,
                    icon: Layers,
                    permission: 'manage-settings',
                },
                {
                    title: t('Start Migration Wizard'),
                    href: wizardHref,
                    icon: Sparkles,
                    permission: 'manage-settings',
                },
                {
                    title: t('ERP API Connections'),
                    href: connectionsHref,
                    icon: Database,
                    permission: 'manage-settings',
                },
                {
                    title: t('Audit Logs & Rollback'),
                    href: historyHref,
                    icon: History,
                    permission: 'manage-settings',
                },
            ],
        },
    ];
};
