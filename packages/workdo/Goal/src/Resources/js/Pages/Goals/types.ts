import { PaginatedData, ModalState, AuthContext } from '@/types/common';

export interface Goal {
    id: number;
    goal_name: string;
    goal_description?: string;
    category_id: number;
    goal_type: 'savings' | 'debt_reduction' | 'expense_reduction' | 'revenue_target' | 'investment' | 'revenue';
    target_amount: number;
    current_amount: number;
    start_date: string;
    target_date: string;
    ethiopian_fiscal_year?: string;
    ethiopian_quarter?: string;
    priority: 'low' | 'medium' | 'high' | 'critical';
    status: 'draft' | 'active' | 'paused' | 'completed' | 'cancelled';
    account_id?: number;
    bank_account_id?: number;
    category?: {
        id: number;
        category_name: string;
        color_code?: string;
    };
    account?: {
        id: number;
        account_name?: string;
        account_code?: string;
        name?: string;
        normal_balance?: string;
    };
    bank_account?: {
        id: number;
        bank_name: string;
        holder_name: string;
    };
    milestones?: any[];
    contributions?: any[];
    created_at: string;
}

export interface CreateGoalFormData {
    goal_name: string;
    goal_description: string;
    category_id: number;
    goal_type: 'savings' | 'debt_reduction' | 'expense_reduction' | 'revenue_target';
    target_amount: number;
    current_amount: number;
    start_date: string;
    target_date: string;
    ethiopian_fiscal_year?: string;
    ethiopian_quarter?: string;
    priority: 'low' | 'medium' | 'high' | 'critical';
    status: 'draft' | 'active' | 'paused' | 'completed' | 'cancelled';
    account_id?: number;
    bank_account_id?: number;
}

export interface EditGoalFormData {
    goal_name: string;
    goal_description: string;
    category_id: number;
    goal_type: 'savings' | 'debt_reduction' | 'expense_reduction' | 'revenue_target';
    target_amount: number;
    current_amount: number;
    start_date: string;
    target_date: string;
    ethiopian_fiscal_year?: string;
    ethiopian_quarter?: string;
    priority: 'low' | 'medium' | 'high' | 'critical';
    status: 'draft' | 'active' | 'paused' | 'completed' | 'cancelled';
    account_id?: number;
    bank_account_id?: number;
}

export interface GoalFilters {
    goal_name: string;
    goal_type: string;
    status: string;
    priority: string;
    category_id: string;
}

export interface GoalsIndexProps {
    goals: PaginatedData<Goal>;
    categories: Array<{ id: number; category_name: string; is_active: boolean }>;
    chartOfAccounts: Array<{ id: number; account_code: string; account_name: string; normal_balance: string }>;
    bankAccounts?: Array<{ id: number; bank_name: string; holder_name: string; opening_balance: number }>;
    auth: AuthContext;
}

export interface GoalModalState extends ModalState {
    data?: Goal | null;
}

export interface CreateGoalProps {
    onSuccess: () => void;
}

export interface EditGoalProps {
    goal: Goal;
    onSuccess: () => void;
}

export interface ViewGoalProps {
    goal: Goal;
    onClose: () => void;
}
