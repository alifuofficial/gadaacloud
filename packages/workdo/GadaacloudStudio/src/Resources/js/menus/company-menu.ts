import { Workflow } from 'lucide-react';

declare global {
    function route(name: string): string;
}

export const gadaacloudStudioCompanyMenu = (t: (key: string) => string) => [
    {
        title: t('GadaaCloud Studio'),
        icon: Workflow,
        permission: 'manage-dashboard',
        order: 2800,
        children: [
            {
                title: t('Approval Center'),
                href: route('gadaacloud-studio.approvals.index'),
                permission: 'manage-dashboard',
            },
            {
                title: t('Workflows'),
                href: route('gadaacloud-studio.workflows.index'),
                permission: 'manage-settings',
            }
        ]
    }
];
