import { NavItem } from '@/types';
import { ShieldCheck, LayoutGrid, Receipt, CloudOff, Settings, FileText } from 'lucide-react';

export const eimsMenu = (t: (key: string) => string): NavItem[] => {
    let dashboardHref = '/eims';
    let invoicesHref = '/eims/invoices';
    let offlineQueueHref = '/eims/offline-queue';
    let settingsHref = '/eims/settings';
    let auditLogsHref = '/eims/audit-logs';

    try {
        if (typeof route === 'function') {
            dashboardHref = route('eims.dashboard');
            invoicesHref = route('eims.invoices.index');
            offlineQueueHref = route('eims.offline-queue.index');
            settingsHref = route('eims.settings.index');
            auditLogsHref = route('eims.audit-logs.index');
        }
    } catch (e) {}

    return [
        {
            title: t('E-Invoicing (EIMS)'),
            icon: ShieldCheck,
            permission: 'manage-eims',
            order: 42,
            children: [
                {
                    title: t('EIMS Dashboard'),
                    href: dashboardHref,
                    icon: LayoutGrid,
                    permission: 'manage-eims',
                },
                {
                    title: t('Cleared Invoices'),
                    href: invoicesHref,
                    icon: Receipt,
                    permission: 'view-eims-invoices',
                },
                {
                    title: t('72h Offline Queue'),
                    href: offlineQueueHref,
                    icon: CloudOff,
                    permission: 'manage-eims-offline-queue',
                },
                {
                    title: t('MoR Settings'),
                    href: settingsHref,
                    icon: Settings,
                    permission: 'manage-eims-settings',
                },
                {
                    title: t('INSA Audit Logs'),
                    href: auditLogsHref,
                    icon: FileText,
                    permission: 'manage-eims-settings',
                },
            ],
        },
    ];
};
