import { SettingMenuItem } from './menus/superadmin-setting';
import { getSuperAdminSettings } from './menus/superadmin-setting';
import { getCompanySettings } from './menus/company-setting';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

// Get role-based core settings items
const getCoreSettingsItems = (userRoles: string[], t: (key: string) => string): SettingMenuItem[] => {
    if (userRoles.includes('superadmin')) {
        return getSuperAdminSettings(t);
    }
    return getCompanySettings(t);
};

// Auto-load package settings based on activated packages
const getPackageSettingsItems = (userRoles: string[], activatedPackages: string[], t: (key: string) => string): SettingMenuItem[] => {
    const menuItems: SettingMenuItem[] = [];
    const settingType = userRoles.includes('superadmin') ? 'superadmin-setting' : 'company-setting';

    const allModules = userRoles.includes('superadmin')
        ? import.meta.glob('../../../packages/workdo/*/src/Resources/js/settings/superadmin-setting.ts', { eager: true })
        : import.meta.glob('../../../packages/workdo/*/src/Resources/js/settings/company-setting.ts', { eager: true });

    (Array.isArray(activatedPackages) ? activatedPackages : []).forEach(packageName => {
        const settingPath = `../../../packages/workdo/${packageName}/src/Resources/js/settings/${settingType}.ts`;
        const module = allModules[settingPath] as any;

        if (module) {
            Object.values(module).forEach((item: any) => {
                const result = typeof item === 'function' ? item(t) : item;
                const items = Array.isArray(result) ? result : [result];
                menuItems.push(...items);
            });
        }
    });

    return menuItems;
};

// Filter settings items based on permissions
const filterByPermission = (items: SettingMenuItem[], userPermissions: string[]): SettingMenuItem[] => {
    return items.filter(item => userPermissions.includes(item.permission));
};

// Main function to get filtered settings items
export const allSettingsItems = (): SettingMenuItem[] => {
    const { auth } = usePage().props as any;
    const { t } = useTranslation();
    
    // Safety check permissions/roles type formats
    const rawPermissions = auth?.user?.permissions || [];
    const userPermissions = Array.isArray(rawPermissions) 
        ? rawPermissions 
        : typeof rawPermissions === 'object' && rawPermissions !== null 
            ? Object.values(rawPermissions) as string[]
            : [];

    const rawRoles = auth?.user?.roles || [];
    const userRoles = Array.isArray(rawRoles) 
        ? rawRoles 
        : typeof rawRoles === 'object' && rawRoles !== null 
            ? Object.values(rawRoles) as string[]
            : [];

    const rawPackages = auth?.user?.activatedPackages || [];
    const activatedPackages = Array.isArray(rawPackages) 
        ? rawPackages 
        : typeof rawPackages === 'object' && rawPackages !== null 
            ? Object.values(rawPackages) as string[]
            : [];

    const coreSettingsItems = getCoreSettingsItems(userRoles, t);
    const packageSettingsItems = getPackageSettingsItems(userRoles, activatedPackages, t);

    const allItems = [...coreSettingsItems, ...packageSettingsItems];

    // Sort by order
    let sortedItems = allItems.sort((a, b) => (a.order || 999) - (b.order || 999));

    // Limit to only basic company settings if not subscribed or plan expired
    const user = auth?.user;
    const isSuperAdmin = userRoles.includes('superadmin');
    if (user && !isSuperAdmin) {
        const isExpired = user.plan_expire_date ? new Date(user.plan_expire_date) < new Date() : false;
        const isInactive = !user.active_plan || user.active_plan === 0 || user.active_plan === '0';
        if (isInactive || isExpired) {
            sortedItems = sortedItems.filter(item => item.component === 'company-settings');
        }
    }

    return filterByPermission(sortedItems, userPermissions);
};