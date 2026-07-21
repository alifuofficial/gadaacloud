import React from 'react';
import { 
    FolderKanban, Calculator, Users2, HeartHandshake, CreditCard, Headphones, 
    Layers, Target, CalendarDays, ShoppingBag, Globe, ShieldAlert, BadgeInfo 
} from 'lucide-react';

interface ModuleIconProps {
    moduleName: string;
    className?: string;
}

export default function ModuleIcon({ moduleName, className = "w-5 h-5" }: ModuleIconProps) {
    const name = moduleName.toLowerCase().replace(/[^a-z0-9]/g, '');
    
    switch (name) {
        case 'project':
            return <FolderKanban className={`${className} text-blue-500`} />;
        case 'accounting':
            return <Calculator className={`${className} text-emerald-500`} />;
        case 'hrm':
            return <Users2 className={`${className} text-orange-500`} />;
        case 'crm':
            return <HeartHandshake className={`${className} text-red-500`} />;
        case 'pos':
            return <ShoppingBag className={`${className} text-indigo-500`} />;
        case 'supportticket':
        case 'support':
            return <Headphones className={`${className} text-purple-500`} />;
        case 'doubleentry':
            return <Layers className={`${className} text-teal-500`} />;
        case 'financialgoal':
            return <Target className={`${className} text-amber-500`} />;
        case 'budgetplanner':
            return <CalendarDays className={`${className} text-pink-500`} />;
        default:
            return <BadgeInfo className={`${className} text-gray-500`} />;
    }
}
