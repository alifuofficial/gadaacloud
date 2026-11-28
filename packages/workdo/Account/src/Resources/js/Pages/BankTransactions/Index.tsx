import { useState } from 'react';
import { Head, usePage, router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Card, CardContent } from "@/components/ui/card";
import { DataTable } from "@/components/ui/data-table";
import { FilterButton } from '@/components/ui/filter-button';
import { SearchInput } from "@/components/ui/search-input";
import { ListGridToggle } from '@/components/ui/list-grid-toggle';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PerPageSelector } from '@/components/ui/per-page-selector';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Pagination } from "@/components/ui/pagination";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import NoRecordsFound from '@/components/no-records-found';
import { CreditCard as CreditCardIcon, CheckCircle, Circle, Upload, Sparkles, Scale } from "lucide-react";
import { formatDate, formatCurrency, stripHtml } from '@/utils/helpers';
import { usePageButtons } from '@/hooks/usePageButtons';
import axios from 'axios';

interface BankTransaction {
    id: number;
    bank_account_id: number;
    transaction_date: string;
    transaction_type: 'debit' | 'credit';
    reference_number: string;
    description: string;
    amount: number;
    running_balance: number;
    transaction_status: 'pending' | 'cleared' | 'cancelled';
    reconciliation_status: 'unreconciled' | 'reconciled';
    matched_type?: string | null;
    matched_id?: number | null;
    bank_account?: {
        id: number;
        account_name: string;
        account_number: string;
    };
}

interface BankAccount {
    id: number;
    account_name: string;
    account_number: string;
    currency_code?: string;
}

interface BankTransactionsIndexProps {
    transactions: {
        data: BankTransaction[];
        links: any[];
        meta: any;
    };
    bankAccounts: BankAccount[];
    stats?: {
        total_transactions: number;
        reconciled_count: number;
        unreconciled_count: number;
        unreconciled_amount: number;
    };
    filters: {
        bank_account_id?: string;
        transaction_type?: string;
        search?: string;
    };
    auth: any;
}

export default function Index() {
    const { t } = useTranslation();
    const { transactions, bankAccounts = [], stats, auth } = usePage<BankTransactionsIndexProps>().props;
    const urlParams = new URLSearchParams(window.location.search);

    const [filters, setFilters] = useState({
        bank_account_id: urlParams.get('bank_account_id') || '',
        transaction_type: urlParams.get('transaction_type') || '',
        search: urlParams.get('search') || '',
    });

    const [perPage] = useState(urlParams.get('per_page') || '10');
    const [sortField, setSortField] = useState(urlParams.get('sort') || '');
    const [sortDirection, setSortDirection] = useState(urlParams.get('direction') || 'asc');
    const [viewMode, setViewMode] = useState<'list' | 'grid'>(urlParams.get('view') as 'list' | 'grid' || 'list');
    const [showFilters, setShowFilters] = useState(false);

    const [importOpen, setImportOpen] = useState(false);
    const [reconOpen, setReconOpen] = useState(false);
    const [reconReport, setReconReport] = useState<any>(null);
    const [reconLoading, setReconLoading] = useState(false);

    const importForm = useForm<{ bank_account_id: string; statement_format: string; file: File | null }>({
        bank_account_id: bankAccounts[0]?.id ? String(bankAccounts[0].id) : '',
        statement_format: 'cbe',
        file: null,
    });

    const googleDriveButtons = usePageButtons('googleDriveBtn', { module: 'Transaction', settingKey: 'GoogleDrive Transaction' });
    const oneDriveButtons = usePageButtons('oneDriveBtn', { module: 'Transaction', settingKey: 'OneDrive Transaction' });
    const dropboxBtn = usePageButtons('dropboxBtn', { module: 'Account Transaction', settingKey: 'Dropbox Account Transaction' });

    const handleFilter = () => {
        router.get(route('account.bank-transactions.index'), { ...filters, per_page: perPage, sort: sortField, direction: sortDirection, view: viewMode }, {
            preserveState: true,
            replace: true
        });
    };

    const handleSort = (field: string) => {
        const direction = sortField === field && sortDirection === 'asc' ? 'desc' : 'asc';
        setSortField(field);
        setSortDirection(direction);
        router.get(route('account.bank-transactions.index'), { ...filters, per_page: perPage, sort: field, direction, view: viewMode }, {
            preserveState: true,
            replace: true
        });
    };

    const clearFilters = () => {
        setFilters({
            bank_account_id: '',
            transaction_type: '',
            search: '',
        });
        router.get(route('account.bank-transactions.index'), { per_page: perPage, sort: sortField, direction: sortDirection, view: viewMode });
    };

    const markReconciled = (id: number) => {
        router.post(route('account.bank-transactions.mark-reconciled', id), {}, { preserveScroll: true });
    };

    const handleAutoMatch = () => {
        router.post(route('account.bank-transactions.auto-match'), {
            bank_account_id: filters.bank_account_id || undefined,
        }, { preserveScroll: true });
    };

    const submitImport = (e: React.FormEvent) => {
        e.preventDefault();
        importForm.post(route('account.bank-transactions.import-statement'), {
            forceFormData: true,
            onSuccess: () => {
                setImportOpen(false);
                importForm.reset('file');
            },
        });
    };

    const openReconciliationReport = async () => {
        setReconOpen(true);
        setReconLoading(true);
        try {
            const res = await axios.get(route('account.bank-transactions.reconciliation-report'), {
                params: { bank_account_id: filters.bank_account_id || bankAccounts[0]?.id },
            });
            setReconReport(res.data);
        } catch (error) {
            setReconReport(null);
        } finally {
            setReconLoading(false);
        }
    };

    const tableColumns = [
        {
            key: 'transaction_date',
            header: t('Date'),
            sortable: true,
            render: (value: string) => formatDate(value)
        },
        {
            key: 'bank_account',
            header: t('Bank Account'),
            sortable: false,
            render: (value: any) => value ? `${value.account_name} (${value.account_number})` : '-'
        },
        {
            key: 'reference_number',
            header: t('Reference'),
            sortable: true
        },
        {
            key: 'transaction_type',
            header: t('Type'),
            sortable: true,
            render: (value: string) => (
                <span className={`px-2 py-1 rounded-full text-sm ${
                    value === 'debit' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'
                }`}>
                    {t(value.charAt(0).toUpperCase() + value.slice(1))}
                </span>
            )
        },
        {
            key: 'amount',
            header: t('Amount'),
            sortable: true,
            render: (value: number) => formatCurrency(value)
        },
        {
            key: 'running_balance',
            header: t('Balance'),
            sortable: false,
            render: (value: number) => formatCurrency(value)
        },
        {
            key: 'description',
            header: t('Description'),
            sortable: false,
            render: (value: string, row: BankTransaction) => (
                <div>
                    <div>{stripHtml(value)}</div>
                    {row.matched_type && (
                        <Badge variant="outline" className="mt-1 text-xs">
                            {row.matched_type} #{row.matched_id}
                        </Badge>
                    )}
                </div>
            )
        },
        {
            key: 'transaction_status',
            header: t('Status'),
            sortable: true,
            render: (value: string) => (
                <span className={`px-2 py-1 rounded-full text-sm ${
                    value === 'cleared' ? 'bg-green-100 text-green-800' :
                    value === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                    'bg-red-100 text-red-800'
                }`}>
                    {t(value.charAt(0).toUpperCase() + value.slice(1))}
                </span>
            )
        },
        {
            key: 'actions',
            header: t('Actions'),
            render: (_: any, transaction: BankTransaction) => (
                <div className="flex items-center justify-center">
                    <TooltipProvider>
                        {transaction.reconciliation_status === 'unreconciled' ? (
                            auth.user?.permissions?.includes('reconcile-bank-transactions') ? (
                                <Tooltip delayDuration={0}>
                                    <TooltipTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => markReconciled(transaction.id)}
                                            className="h-8 w-8 p-0 text-green-600 hover:text-green-700"
                                        >
                                            <Circle className="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{t('Mark as Reconciled')}</p>
                                    </TooltipContent>
                                </Tooltip>
                            ) : (
                                <Circle className="h-4 w-4 text-gray-400" />
                            )
                        ) : (
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <div className="inline-flex">
                                        <CheckCircle className="h-4 w-4 text-green-600" />
                                    </div>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{t('Reconciled')}</p>
                                </TooltipContent>
                            </Tooltip>
                        )}
                    </TooltipProvider>
                </div>
            )
        }
    ];

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Accounting'), url: route('account.index') },
                { label: t('Bank Transactions') }
            ]}
            pageTitle={t('Manage Bank Transactions & Reconciliation')}
            pageActions={
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="sm" onClick={() => setImportOpen(true)} className="gap-2">
                        <Upload className="h-4 w-4" />
                        {t('Import CBE / Telebirr CSV')}
                    </Button>
                    {auth.user?.permissions?.includes('reconcile-bank-transactions') && (
                        <Button variant="outline" size="sm" onClick={handleAutoMatch} className="gap-2">
                            <Sparkles className="h-4 w-4" />
                            {t('Auto-Match')}
                        </Button>
                    )}
                    <Button size="sm" onClick={openReconciliationReport} className="gap-2">
                        <Scale className="h-4 w-4" />
                        {t('Reconciliation Statement')}
                    </Button>
                    <TooltipProvider>
                        {(googleDriveButtons || []).map((button) => (
                            <span key={button.id}>{button.component}</span>
                        ))}
                        {(oneDriveButtons || []).map((button) => (
                            <span key={button.id}>{button.component}</span>
                        ))}
                        {(dropboxBtn || []).map((button) => (
                            <span key={button.id}>{button.component}</span>
                        ))}
                    </TooltipProvider>
                </div>
            }
        >
            <Head title={t('Bank Transactions')} />

            {stats && (
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <Card><CardContent className="p-4"><div className="text-xs text-muted-foreground">{t('Total Transactions')}</div><div className="text-2xl font-bold">{stats.total_transactions}</div></CardContent></Card>
                    <Card><CardContent className="p-4"><div className="text-xs text-muted-foreground">{t('Reconciled')}</div><div className="text-2xl font-bold text-green-600">{stats.reconciled_count}</div></CardContent></Card>
                    <Card><CardContent className="p-4"><div className="text-xs text-muted-foreground">{t('Unreconciled Count')}</div><div className="text-2xl font-bold text-amber-600">{stats.unreconciled_count}</div></CardContent></Card>
                    <Card><CardContent className="p-4"><div className="text-xs text-muted-foreground">{t('Unreconciled Amount')}</div><div className="text-2xl font-bold">{formatCurrency(stats.unreconciled_amount || 0)}</div></CardContent></Card>
                </div>
            )}

            <Card className="shadow-sm">
                <CardContent className="p-6 border-b bg-gray-50/50">
                    <div className="flex items-center justify-between gap-4">
                        <div className="flex-1 max-w-md">
                            <SearchInput
                                value={filters.search}
                                onChange={(value) => setFilters({ ...filters, search: value })}
                                onSearch={handleFilter}
                                placeholder={t('Search transactions...')}
                            />
                        </div>
                        <div className="flex items-center gap-3">
                            <ListGridToggle
                                currentView={viewMode}
                                routeName="account.bank-transactions.index"
                                filters={{ ...filters, per_page: perPage }}
                            />
                            <PerPageSelector
                                routeName="account.bank-transactions.index"
                                filters={{ ...filters, view: viewMode }}
                            />
                            <div className="relative">
                                <FilterButton
                                    showFilters={showFilters}
                                    onToggle={() => setShowFilters(!showFilters)}
                                />
                                {(() => {
                                    const activeFilters = [filters.bank_account_id, filters.transaction_type].filter(f => f !== '' && f !== null && f !== undefined).length;
                                    return activeFilters > 0 && (
                                        <span className="absolute -top-2 -right-2 bg-primary text-primary-foreground text-xs rounded-full h-5 w-5 flex items-center justify-center font-medium">
                                            {activeFilters}
                                        </span>
                                    );
                                })()}
                            </div>
                        </div>
                    </div>
                </CardContent>

                {showFilters && (
                    <CardContent className="p-6 bg-blue-50/30 border-b">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">{t('Bank Account')}</label>
                                <Select value={filters.bank_account_id || 'all'} onValueChange={(value) => setFilters({ ...filters, bank_account_id: value === 'all' ? '' : value })}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Filter by Bank Account')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('All Bank Accounts')}</SelectItem>
                                        {(bankAccounts || []).map((account) => (
                                            <SelectItem key={account.id} value={account.id.toString()}>
                                                {account.account_name} ({account.account_number})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">{t('Transaction Type')}</label>
                                <Select value={filters.transaction_type || 'all'} onValueChange={(value) => setFilters({ ...filters, transaction_type: value === 'all' ? '' : value })}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Filter by Type')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('All Types')}</SelectItem>
                                        <SelectItem value="debit">{t('Debit')}</SelectItem>
                                        <SelectItem value="credit">{t('Credit')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-end gap-2">
                                <Button onClick={handleFilter} size="sm">{t('Apply')}</Button>
                                <Button variant="outline" onClick={clearFilters} size="sm">{t('Clear')}</Button>
                            </div>
                        </div>
                    </CardContent>
                )}

                <CardContent className="p-0">
                    {viewMode === 'list' ? (
                        <div className="overflow-y-auto scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100 max-h-[70vh] rounded-none w-full">
                            <div className="min-w-[800px]">
                                <DataTable
                                    data={transactions?.data || []}
                                    columns={tableColumns}
                                    onSort={handleSort}
                                    sortKey={sortField}
                                    sortDirection={sortDirection as 'asc' | 'desc'}
                                    className="rounded-none"
                                    emptyState={
                                        <NoRecordsFound
                                            icon={CreditCardIcon}
                                            title={t('No transactions found')}
                                            description={t('Bank transactions will appear here once created.')}
                                            hasFilters={!!(filters.search || filters.bank_account_id || filters.transaction_type)}
                                            onClearFilters={clearFilters}
                                            className="h-auto"
                                        />
                                    }
                                />
                            </div>
                        </div>
                    ) : (
                        <div className="overflow-auto max-h-[70vh] p-6">
                            {(transactions?.data || []).length > 0 ? (
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4">
                                    {(transactions?.data || []).map((transaction) => (
                                        <Card key={transaction.id} className="border border-gray-200 flex flex-col">
                                            <div className="p-4 flex-1">
                                                <div className="flex items-center gap-3 mb-3">
                                                    <div className="w-12 h-12 bg-primary rounded-lg border flex items-center justify-center">
                                                        <CreditCardIcon className="w-6 h-6 text-primary-foreground" />
                                                    </div>
                                                    <div className="flex-1">
                                                        <h3 className="font-semibold text-base text-gray-900">{transaction.reference_number}</h3>
                                                    </div>
                                                </div>

                                                <div className="space-y-3">
                                                    <div>
                                                        <p className="text-xs font-medium text-gray-600 mb-2">{t('Bank Account')}</p>
                                                        <p className="text-xs text-gray-900 truncate">{transaction.bank_account?.account_name} ({transaction.bank_account?.account_number})</p>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-sm text-gray-600">{t('Date')}</span>
                                                        <span className="text-sm font-medium">{formatDate(transaction.transaction_date)}</span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-sm text-gray-600">{t('Amount')}</span>
                                                        <span className="text-sm font-medium">{formatCurrency(transaction.amount)}</span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-sm text-gray-600">{t('Balance')}</span>
                                                        <span className="text-sm font-medium">{formatCurrency(transaction.running_balance)}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </Card>
                                    ))}
                                </div>
                            ) : (
                                <NoRecordsFound
                                    icon={CreditCardIcon}
                                    title={t('No transactions found')}
                                    description={t('Bank transactions will appear here once created.')}
                                    hasFilters={!!(filters.search || filters.bank_account_id || filters.transaction_type)}
                                    onClearFilters={clearFilters}
                                />
                            )}
                        </div>
                    )}
                </CardContent>

                <CardContent className="px-4 py-2 border-t bg-gray-50/30">
                    <Pagination
                        data={transactions || { data: [], links: [], meta: {} }}
                        routeName="account.bank-transactions.index"
                        filters={{ ...filters, per_page: perPage, view: viewMode }}
                    />
                </CardContent>
            </Card>

            <Dialog open={importOpen} onOpenChange={setImportOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Import CBE / Telebirr / Bank Statement')}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitImport} className="space-y-4">
                        <div>
                            <Label>{t('Bank Account')}</Label>
                            <Select value={importForm.data.bank_account_id || 'none'} onValueChange={(v) => importForm.setData('bank_account_id', v === 'none' ? '' : v)}>
                                <SelectTrigger className="mt-1">
                                    <SelectValue placeholder={t('Select Bank Account')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">{t('Select Bank Account')}</SelectItem>
                                    {(bankAccounts || []).map((acc) => (
                                        <SelectItem key={acc.id} value={String(acc.id)}>
                                            {acc.account_name} ({acc.account_number})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label>{t('Statement Format')}</Label>
                            <Select value={importForm.data.statement_format} onValueChange={(v) => importForm.setData('statement_format', v)}>
                                <SelectTrigger className="mt-1">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="cbe">{t('Commercial Bank of Ethiopia (CBE) CSV')}</SelectItem>
                                    <SelectItem value="telebirr">{t('Telebirr Merchant CSV (Auto-posts Service Fees)')}</SelectItem>
                                    <SelectItem value="generic_csv">{t('Generic Bank CSV')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label htmlFor="statement_file">{t('CSV File')}</Label>
                            <Input
                                id="statement_file"
                                type="file"
                                accept=".csv,.txt"
                                className="mt-1"
                                onChange={(e) => importForm.setData('file', e.target.files?.[0] || null)}
                                required
                            />
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => setImportOpen(false)}>{t('Cancel')}</Button>
                            <Button type="submit" disabled={importForm.processing || !importForm.data.bank_account_id || !importForm.data.file}>
                                {importForm.processing ? t('Importing...') : t('Import Statement')}
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={reconOpen} onOpenChange={setReconOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{t('Bank Reconciliation Statement')}</DialogTitle>
                    </DialogHeader>
                    {reconLoading ? (
                        <div className="py-6 text-center text-sm text-muted-foreground">{t('Loading reconciliation statement...')}</div>
                    ) : reconReport ? (
                        <div className="space-y-3 text-sm">
                            <div className="p-3 rounded bg-muted/50">
                                <div className="font-semibold">{reconReport.bank_account?.name}</div>
                                <div className="text-xs text-muted-foreground">
                                    {reconReport.bank_account?.bank} — {reconReport.bank_account?.number} ({reconReport.bank_account?.currency})
                                </div>
                            </div>
                            <div className="space-y-2 border-t pt-2">
                                <div className="flex justify-between"><span>{t('Ledger Book Balance')}</span><span className="font-medium">{formatCurrency(reconReport.summary?.ledger_book_balance || 0)}</span></div>
                                <div className="flex justify-between"><span>{t('Uncredited Deposits in Transit (+)')}</span><span className="font-medium">{formatCurrency(reconReport.summary?.uncredited_deposits_in_transit || 0)}</span></div>
                                <div className="flex justify-between"><span>{t('Outstanding Payments (-)')}</span><span className="font-medium">{formatCurrency(reconReport.summary?.unpresented_payments_outstanding || 0)}</span></div>
                                <div className="flex justify-between"><span>{t('Unreconciled Bank Credits (+)')}</span><span className="font-medium">{formatCurrency(reconReport.summary?.unreconciled_statement_credits || 0)}</span></div>
                                <div className="flex justify-between"><span>{t('Unreconciled Bank Debits (-)')}</span><span className="font-medium">{formatCurrency(reconReport.summary?.unreconciled_statement_debits || 0)}</span></div>
                                <div className="flex justify-between border-t pt-2 font-semibold"><span>{t('Adjusted Book Balance')}</span><span>{formatCurrency(reconReport.summary?.adjusted_book_balance || 0)}</span></div>
                                <div className="flex justify-between font-semibold"><span>{t('Reconciled Statement Balance')}</span><span>{formatCurrency(reconReport.summary?.reconciled_bank_balance || 0)}</span></div>
                                <div className={`flex justify-between border-t pt-2 font-bold ${Math.abs(reconReport.summary?.variance || 0) < 0.01 ? 'text-green-700' : 'text-red-700'}`}>
                                    <span>{t('Variance')}</span>
                                    <span>{formatCurrency(reconReport.summary?.variance || 0)}</span>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="py-6 text-center text-sm text-red-600">{t('Unable to load reconciliation summary.')}</div>
                    )}
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
