import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Settings, ShieldAlert, Globe, Server, Check, Ban, RefreshCw } from 'lucide-react';

interface DomainRecord {
    id: number;
    company_name: string;
    company_email: string;
    subdomain: string;
    custom_domain?: string;
    custom_domain_status: 'pending_dns' | 'active' | 'expired' | 'suspended';
    is_registered_via_dynadot: boolean;
    registration_expires_at?: string;
    created_at: string;
}

interface AdminDomainsProps {
    domains: DomainRecord[];
    dynadot_api_key?: string;
    dynadot_mode: 'sandbox' | 'production';
    dynadot_markup_percentage: string;
}

export default function AdminDomains({ domains, dynadot_api_key, dynadot_mode, dynadot_markup_percentage }: AdminDomainsProps) {
    const { t } = useTranslation();
    const [apiKey, setApiKey] = useState(dynadot_api_key || '');
    const [mode, setMode] = useState<'sandbox' | 'production'>(dynadot_mode || 'sandbox');
    const [markup, setMarkup] = useState(dynadot_markup_percentage || '0');
    const [isLoading, setIsLoading] = useState(false);

    // Save Reseller Settings
    const handleSaveSettings = (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        router.post(route('admin.domains.settings.update'), {
            dynadot_api_key: apiKey,
            dynadot_mode: mode,
            dynadot_markup_percentage: parseFloat(markup)
        }, {
            onFinish: () => setIsLoading(false)
        });
    };

    // Suspend / Activate Domain
    const handleToggleStatus = (id: number) => {
        if (!confirm(t('Are you sure you want to change the status of this domain mapping?'))) return;
        router.post(route('admin.domains.status.toggle', { id }));
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Settings'), url: route('settings.index') },
                { label: t('Tenant Domain Manager') }
            ]}
            pageTitle={t('Tenant Domain Manager')}
        >
            <Head title={t('Tenant Domain Manager')} />

            <div className="space-y-8 max-w-6xl mx-auto">
                {/* Dynadot Settings Card */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Settings className="h-5 w-5 text-muted-foreground" />
                            {t('Dynadot API Reseller Configuration')}
                        </CardTitle>
                        <CardDescription>
                            {t('Configure your Dynadot API key and markups for domain search & registration.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSaveSettings} className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="api_key">{t('Dynadot API Key')}</Label>
                                    <Input
                                        id="api_key"
                                        type="password"
                                        value={apiKey}
                                        onChange={(e) => setApiKey(e.target.value)}
                                        placeholder={t('Enter API Key')}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="mode">{t('API Environment')}</Label>
                                    <Select value={mode} onValueChange={(value: 'sandbox' | 'production') => setMode(value)}>
                                        <SelectTrigger id="mode">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="sandbox">{t('Sandbox (Testing)')}</SelectItem>
                                            <SelectItem value="production">{t('Production (Live)')}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="markup">{t('Pricing Markup (%)')}</Label>
                                    <Input
                                        id="markup"
                                        type="number"
                                        step="0.1"
                                        value={markup}
                                        onChange={(e) => setMarkup(e.target.value)}
                                        placeholder="0"
                                        min="0"
                                    />
                                </div>
                            </div>

                            <div className="flex justify-between items-center border-t pt-4">
                                <p className="text-xs text-muted-foreground max-w-xl">
                                    <strong>{t('Important Tip:')}</strong> {t('Ensure you whitelist GadaaCloud server IP address in your Dynadot API console settings tools to authorize outgoing requests.')}
                                </p>
                                <Button type="submit" disabled={isLoading}>
                                    {isLoading && <RefreshCw className="h-4 w-4 mr-2 animate-spin" />}
                                    {t('Save Configuration')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Tenant Domains Mappings Listing Table */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Globe className="h-5 w-5 text-muted-foreground" />
                            {t('Active Domain Mappings')}
                        </CardTitle>
                        <CardDescription>
                            {t('Monitor and manage subdomains and custom domains mapped to tenant workspaces.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Company')}</TableHead>
                                    <TableHead>{t('Subdomain')}</TableHead>
                                    <TableHead>{t('Custom Domain')}</TableHead>
                                    <TableHead>{t('Status')}</TableHead>
                                    <TableHead>{t('Type')}</TableHead>
                                    <TableHead>{t('Actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {domains.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="text-center text-muted-foreground py-6">
                                            {t('No domain mappings registered.')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    domains.map((record) => (
                                        <TableRow key={record.id}>
                                            <TableCell>
                                                <div>
                                                    <p className="font-medium text-foreground">{record.company_name}</p>
                                                    <p className="text-xs text-muted-foreground">{record.company_email}</p>
                                                </div>
                                            </TableCell>
                                            <TableCell className="font-mono text-sm">{record.subdomain}</TableCell>
                                            <TableCell className="font-mono text-sm">
                                                {record.custom_domain ? (
                                                    <div>
                                                        <p>{record.custom_domain}</p>
                                                        {record.registration_expires_at && (
                                                            <p className="text-[10px] text-muted-foreground">
                                                                {t('Expires:')} {new Date(record.registration_expires_at).toLocaleDateString()}
                                                            </p>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground italic text-xs">{t('Not mapped')}</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                                                    record.custom_domain_status === 'active' ? 'bg-emerald-100 text-emerald-800' :
                                                    record.custom_domain_status === 'suspended' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'
                                                }`}>
                                                    {record.custom_domain_status.toUpperCase()}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                {record.is_registered_via_dynadot ? (
                                                    <span className="font-medium text-blue-600">{t('Dynadot Registry')}</span>
                                                ) : (
                                                    <span className="text-muted-foreground">{t('External Mapping')}</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {record.custom_domain && (
                                                    <Button
                                                        size="sm"
                                                        variant={record.custom_domain_status === 'active' ? 'destructive' : 'default'}
                                                        onClick={() => handleToggleStatus(record.id)}
                                                    >
                                                        {record.custom_domain_status === 'active' ? (
                                                            <>
                                                                <Ban className="h-3.5 w-3.5 mr-1" />
                                                                {t('Suspend')}
                                                            </>
                                                        ) : (
                                                            <>
                                                                <Check className="h-3.5 w-3.5 mr-1" />
                                                                {t('Activate')}
                                                            </>
                                                        )}
                                                    </Button>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
