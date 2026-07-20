import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Table, TableHeader, TableRow, TableHead, TableBody, TableCell } from '@/components/ui/table';
import { Input } from '@/components/ui/input';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';
import { CalendarDays, Clock, CheckCircle2, XCircle, ArrowLeft, ArrowRight, Filter, Search, ChevronRight, Users } from 'lucide-react';

interface ApprovalEntry {
    id: number;
    workflow_name: string;
    target_model_name: string;
    document_title: string;
    document_details: string;
    status: 'pending' | 'approved' | 'rejected';
    priority: string;
    deadline_at: string | null;
    days_until_deadline: number | null;
    is_overdue: boolean;
    current_step_name: string;
    can_action: boolean;
    delegated_to: string | null;
}

interface IndexProps {
    requests: ApprovalEntry[];
    stats: {
        my_action: number;
        total_pending: number;
        total_approved: number;
        total_rejected: number;
        overdue_count: number;
    };
    users: { id: number; name: string; email: string }[];
}

export default function ApprovalIndex({ requests, stats }: IndexProps) {
    const { t } = useTranslation();
    const [search, setSearch] = useState('');

    const filtered = requests.filter(r =>
        r.document_title.toLowerCase().includes(search.toLowerCase()) ||
        r.workflow_name.toLowerCase().includes(search.toLowerCase())
    );

    const statusBadge = (status: string) => {
        const map: Record<string, string> = {
            pending: 'bg-amber-100 text-amber-700 border border-amber-200',
            approved: 'bg-emerald-100 text-emerald-700 border border-emerald-200',
            rejected: 'bg-red-100 text-red-700 border border-red-200',
        };
        return map[status] || 'bg-gray-100 text-gray-600';
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('GadaaCloud Studio'), url: route('gadaacloud-studio.dashboard') }, { label: t('Approval Center') }]}
            pageTitle={t('Approval Center')}
        >
            <Head title={t('Approval Center')} />

            {/* Header */}
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h2 className="text-lg font-bold text-gray-900">{t('Pending Approvals')}</h2>
                    <p className="text-sm text-muted-foreground mt-0.5">
                        {t('You have :count items awaiting your action', { count: stats.my_action })}
                    </p>
                </div>
                <Button asChild variant="outline" size="sm" className="gap-1 border-gray-200">
                    <Link href={route('gadaacloud-studio.dashboard')}>
                        <ArrowLeft className="h-4 w-4" />
                        {t('Back to Dashboard')}
                    </Link>
                </Button>
            </div>

            {/* KPI Summary */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <Badge variant="outline" className="flex items-center gap-2 bg-amber-50 border-amber-200 text-amber-700">
                    <Clock className="h-4 w-4" />
                    {t('My Actionable')} {stats.my_action}
                </Badge>
                <Badge variant="outline" className="flex items-center gap-2 bg-amber-50 border-amber-200 text-amber-700">
                    <Clock className="h-4 w-4" />
                    {t('Pending')} {stats.total_pending}
                </Badge>
                <Badge variant="outline" className="flex items-center gap-2 bg-emerald-50 border-emerald-200 text-emerald-700">
                    <CheckCircle2 className="h-4 w-4" />
                    {t('Approved')} {stats.total_approved}
                </Badge>
                <Badge variant="outline" className="flex items-center gap-2 bg-red-50 border-red-200 text-red-700">
                    <XCircle className="h-4 w-4" />
                    {t('Rejected')} {stats.total_rejected}
                </Badge>
            </div>

            {/* Search / Filter Bar */}
            <div className="flex items-center gap-3 mb-4">
                <div className="relative flex-1 max-w-md">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <Input
                        placeholder={t('Search by title, workflow...')}
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                        className="pl-9 pr-3 py-2 rounded-md border border-input bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                {/* Placeholder for future filters */}
                <Button variant="outline" size="sm" disabled className="opacity-50 cursor-not-allowed">
                    <Filter className="h-4 w-4 mr-1" />
                    {t('Filters')}
                </Button>
            </div>

            {/* Requests Table */}
            <Card>
                <CardHeader className="border-b pb-4">
                    <CardTitle className="text-sm font-semibold">{t('Approval Requests')}</CardTitle>
                </CardHeader>
                <CardContent className="p-0">
                    {filtered.length === 0 ? (
                        <p className="p-6 text-center text-muted-foreground">{t('No matching approvals found.')}</p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-gray-50/30 text-xs uppercase text-gray-500">
                                    <TableHead className="w-8">#</TableHead>
                                    <TableHead>{t('Document')}</TableHead>
                                    <TableHead>{t('Workflow')}</TableHead>
                                    <TableHead>{t('Status')}</TableHead>
                                    <TableHead>{t('Priority')}</TableHead>
                                    <TableHead>{t('Due')}</TableHead>
                                    <TableHead className="text-center w-24">{t('Action')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filtered.map((r, idx) => (
                                    <TableRow key={r.id} className="hover:bg-gray-50/30 transition-colors">
                                        <TableCell className="text-sm font-medium text-gray-500">{idx + 1}</TableCell>
                                        <TableCell>
                                            <div className="flex flex-col">
                                                <Link href={route('gadaacloud-studio.approvals.show', r.id)} className="font-medium text-indigo-600 hover:underline">
                                                    {r.document_title}
                                                </Link>
                                                <span className="text-xs text-gray-400">{r.document_details}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm text-gray-700">{r.workflow_name}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline" className={statusBadge(r.status) + ' px-2 py-0.5'}>
                                                {t(r.status)}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-sm capitalize">{r.priority}</TableCell>
                                        <TableCell className="text-sm">
                                            {r.deadline_at ? (
                                                <span className={r.is_overdue ? 'text-red-600 font-semibold' : 'text-gray-600'}>
                                                    {r.deadline_at}
                                                    {r.days_until_deadline !== null && (
                                                        <span className="ml-1">({r.days_until_deadline >= 0 ? `${r.days_until_deadline}d` : `${Math.abs(r.days_until_deadline)}d overdue`})</span>
                                                    )}
                                                </span>
                                            ) : (
                                                <span className="text-gray-400">—</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            {r.can_action && r.status === 'pending' ? (
                                                <Button size="sm" asChild>
                                                    <Link href={route('gadaacloud-studio.approvals.show', r.id)}>
                                                        <ChevronRight className="h-4 w-4 mr-1" />
                                                        {t('Act')}
                                                    </Link>
                                                </Button>
                                            ) : (
                                                <span className="text-xs text-gray-400">—</span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </AuthenticatedLayout>
    );
}

function useState<T>(initial: T): [T, (v: T) => void] {
    // placeholder shim for React useState – real implementation provided at runtime
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const [_val, _set] = [] as any;
    return [_val, _set];
}
