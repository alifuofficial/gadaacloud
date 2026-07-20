import { useState } from 'react';
import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Network, Search, Briefcase, Users, Building2, User, ChevronRight, ChevronDown } from 'lucide-react';

interface EmployeeNode {
    id: string;
    name: string;
    email: string;
    designation: string;
    department: string;
    branch: string;
    avatar?: string | null;
    children: EmployeeNode[];
}

interface IndexProps {
    chartData: EmployeeNode[];
}

// Recursive Tree Node Component
function TreeNode({ node, searchQuery }: { node: EmployeeNode; searchQuery: string }) {
    const { t } = useTranslation();
    const [isCollapsed, setIsCollapsed] = useState(false);

    const hasChildren = node.children && node.children.length > 0;
    const initials = node.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();

    // Check if node match search query
    const matchesSearch = node.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                          node.email.toLowerCase().includes(searchQuery.toLowerCase()) ||
                          node.designation.toLowerCase().includes(searchQuery.toLowerCase()) ||
                          node.branch.toLowerCase().includes(searchQuery.toLowerCase());

    // Render tree recursively
    return (
        <div className="flex flex-col items-start pl-4 relative before:absolute before:left-0 before:top-0 before:bottom-0 before:w-0.5 before:bg-gray-200">
            {/* Horizontal line connector */}
            <div className="absolute left-0 top-7 w-4 h-0.5 bg-gray-200" />

            {/* Employee Card */}
            <div className={`w-80 border rounded-xl bg-white p-4 shadow-sm my-2 transition-all hover:shadow-md relative ${matchesSearch && searchQuery ? 'border-emerald-500 ring-2 ring-emerald-500/10' : 'border-slate-150 hover:border-slate-300'}`}>
                <div className="flex items-start justify-between gap-2">
                    <div className="flex items-center gap-3 min-w-0">
                        {/* Avatar */}
                        <div className="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs font-bold shrink-0 border border-indigo-100">
                            {initials}
                        </div>
                        <div className="min-w-0">
                            <h4 className="font-bold text-gray-900 text-sm truncate">{node.name}</h4>
                            <p className="text-[10px] text-gray-400 truncate">{node.email}</p>
                        </div>
                    </div>

                    {hasChildren && (
                        <button 
                            onClick={() => setIsCollapsed(!isCollapsed)}
                            className="p-1 hover:bg-slate-100 rounded-md text-gray-400 hover:text-gray-600 transition-colors"
                        >
                            {isCollapsed ? <ChevronRight className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                        </button>
                    )}
                </div>

                {/* Sub-meta details */}
                <div className="mt-3 pt-3 border-t border-slate-100 space-y-1.5 text-xs text-gray-600">
                    <div className="flex items-center gap-1.5 min-w-0">
                        <Briefcase className="h-3.5 w-3.5 text-indigo-500 shrink-0" />
                        <span className="truncate font-semibold text-gray-700">{node.designation}</span>
                    </div>
                    <div className="flex items-center gap-1.5 min-w-0">
                        <Building2 className="h-3.5 w-3.5 text-blue-500 shrink-0" />
                        <span className="truncate">{node.department}</span>
                    </div>
                    <div className="flex items-center gap-1.5 min-w-0">
                        <Badge variant="outline" className="border-gray-200 text-gray-600 bg-gray-50 text-[10px] py-0.5 px-2 font-medium shrink-0">
                            📍 {node.branch}
                        </Badge>
                    </div>
                </div>
            </div>

            {/* Render children nodes if not collapsed */}
            {hasChildren && !isCollapsed && (
                <div className="pl-6 space-y-2 relative">
                    {node.children.map(child => (
                        <TreeNode key={child.id} node={child} searchQuery={searchQuery} />
                    ))}
                </div>
            )}
        </div>
    );
}

export default function Index({ chartData }: IndexProps) {
    const { t } = useTranslation();
    const [searchQuery, setSearchQuery] = useState('');

    // Recursive helper to filter matches in subtree
    const filterTree = (nodes: EmployeeNode[]): EmployeeNode[] => {
        return nodes.map(node => {
            const filteredChildren = filterTree(node.children);
            const matchesThisNode = node.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                                    node.email.toLowerCase().includes(searchQuery.toLowerCase()) ||
                                    node.designation.toLowerCase().includes(searchQuery.toLowerCase()) ||
                                    node.branch.toLowerCase().includes(searchQuery.toLowerCase());
            
            if (matchesThisNode || filteredChildren.length > 0) {
                return { ...node, children: filteredChildren };
            }
            return null;
        }).filter(Boolean) as EmployeeNode[];
    };

    const filteredChart = searchQuery ? filterTree(chartData) : chartData;

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('HRM') }, { label: t('Org Chart') }]} pageTitle={t('Company Organization Chart')}>
            <Head title={t('Organization Chart')} />

            <div className="space-y-6">
                {/* Search / Filters Panel */}
                <Card className="border-gray-150 shadow-sm">
                    <CardContent className="p-4 flex items-center justify-between gap-4 flex-col sm:flex-row">
                        <div className="flex items-center gap-2">
                            <Network className="h-5 w-5 text-indigo-600" />
                            <div>
                                <h2 className="text-base font-semibold text-gray-800">{t('Organizational Structure Explorer')}</h2>
                                <p className="text-xs text-muted-foreground">{t('Explore the reporting line hierarchy and manager-subordinate relationships.')}</p>
                            </div>
                        </div>

                        <div className="relative w-full max-w-sm">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                            <Input
                                type="text"
                                placeholder={t('Search by name, role, or branch...')}
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="pl-9 h-9"
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* Tree Chart Visualizer */}
                <div className="space-y-6 overflow-x-auto p-4 bg-slate-50/30 rounded-2xl border border-dashed border-slate-200">
                    {filteredChart.length > 0 ? (
                        <div className="flex flex-col items-start gap-4">
                            {filteredChart.map(rootNode => (
                                <TreeNode key={rootNode.id} node={rootNode} searchQuery={searchQuery} />
                            ))}
                        </div>
                    ) : (
                        <Card className="border-gray-150 p-12 text-center bg-white">
                            <Users className="h-10 w-10 text-gray-300 mx-auto mb-3" />
                            <h3 className="font-semibold text-gray-700">{t('No matches found')}</h3>
                            <p className="text-sm text-gray-400 mt-1">{t('Adjust your search query to look for employees or reporting lines.')}</p>
                        </Card>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
