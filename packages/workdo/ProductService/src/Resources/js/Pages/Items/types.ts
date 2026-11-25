import { PaginatedData, AuthContext, EditProps } from '@/types/common';

export interface AccountOption {
    id: number;
    account_code: string;
    account_name: string;
}

export interface Item {
    id: number;
    name: string;
    sku?: string;
    barcode?: string;
    hs_code?: string;
    tax_id?: number;
    tax_ids?: number[];
    tax_classification?: 'VAT15' | 'VAT0' | 'EXEMPT' | 'EXCISE';
    excise_rate?: number;
    is_tax_inclusive?: boolean;
    category_id?: number;
    description?: string;
    long_description?: string;
    is_active: boolean;
    created_at: string;
    sale_price?: number;
    min_sale_price?: number;
    wholesale_price?: number;
    wholesale_min_qty?: number;
    purchase_price?: number;
    sale_chartaccount_id?: number | null;
    expense_chartaccount_id?: number | null;
    inventory_chartaccount_id?: number | null;
    purchase_unit_id?: number | null;
    unit_conversion_factor?: number;
    reorder_level?: number;
    quantity?: number;
    total_quantity?: number;
    type?: string;
    inventory_type?: 'standard' | 'serialized' | 'batch';
    category?: Category;
    unit?: string;
    unit_relation?: {
        id?: number;
        unit_name: string;
    };
    purchase_unit?: {
        id: number;
        unit_name: string;
    };
    image?: string;
    images?: string[] | string;
}

export interface ItemFormData {
    name: string;
    sku?: string;
    barcode?: string;
    hs_code?: string;
    tax_ids?: string[];
    tax_classification?: string;
    excise_rate?: string;
    is_tax_inclusive?: boolean;
    category_id?: string;
    description?: string;
    long_description?: string;
    sale_price?: string;
    min_sale_price?: string;
    wholesale_price?: string;
    wholesale_min_qty?: string;
    purchase_price?: string;
    sale_chartaccount_id?: string;
    expense_chartaccount_id?: string;
    inventory_chartaccount_id?: string;
    unit?: string;
    purchase_unit_id?: string;
    unit_conversion_factor?: string;
    reorder_level?: string;
    quantity?: string;
    image?: string;
    images?: string[];
    warehouse_id?: string;
    type?: string;
    inventory_type?: 'standard' | 'serialized' | 'batch';
    is_active?: boolean;
    custom_fields?: Record<string, any>;
    has_warranty?: number;
    warranty_type?: string;
    warranty_duration?: string;
    warranty_terms?: string;
}

export interface Tax {
    id: number;
    tax_name: string;
    rate: number;
}

export interface Category {
    id: number;
    name: string;
    type?: string;
}

export interface Unit {
    id: number;
    unit_name: string;
}

export interface Warehouse {
    id: number;
    name: string;
}

export interface EditItemProps extends EditProps<Item> {}

export interface ItemFilters {
    name: string;
    type: string;
    category_id: string;
    is_active?: string;
}

export type PaginatedItems = PaginatedData<Item>;

export interface ItemsIndexProps {
    items: PaginatedItems;
    categories: Category[];
    auth: AuthContext;
    [key: string]: unknown;
}

export interface CreateItemPageProps {
    taxes: Tax[];
    categories: Category[];
    units: Unit[];
    warehouses: Warehouse[];
    hasAccountModule?: boolean;
    revenueAccounts?: AccountOption[];
    expenseAccounts?: AccountOption[];
    assetAccounts?: AccountOption[];
    auth: AuthContext;
    [key: string]: unknown;
}

export interface EditItemPageProps {
    item: Item;
    taxes: Tax[];
    categories: Category[];
    units: Unit[];
    warehouses: Warehouse[];
    hasAccountModule?: boolean;
    revenueAccounts?: AccountOption[];
    expenseAccounts?: AccountOption[];
    assetAccounts?: AccountOption[];
    auth: AuthContext;
    [key: string]: unknown;
}
