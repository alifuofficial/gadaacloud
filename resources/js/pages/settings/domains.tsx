import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { CheckCircle2, AlertTriangle, Search, Globe, ShieldCheck, CreditCard, RefreshCw } from 'lucide-react';

interface DomainSettingsProps {
    tenantDomain: {
        subdomain: string;
        custom_domain?: string;
        custom_domain_status: 'pending_dns' | 'active' | 'expired' | 'suspended';
        custom_ssl_certificate?: string;
        custom_ssl_private_key?: string;
        is_registered_via_dynadot: boolean;
        registration_expires_at?: string;
    };
    baseDomain: string;
    serverIp: string;
    tldPrices: Record<string, number>;
    orders: Array<{
        id: number;
        domain: string;
        payment_gateway: string;
        payment_status: string;
        amount: number;
        currency: string;
        dynadot_status: string;
        dynadot_error?: string;
        created_at: string;
    }>;
}

export default function Domains({ tenantDomain, baseDomain, serverIp, tldPrices, orders }: DomainSettingsProps) {
    const { t } = useTranslation();
    const [subdomain, setSubdomain] = useState(tenantDomain.subdomain);
    const [customDomain, setCustomDomain] = useState(tenantDomain.custom_domain || '');
    const [customSslCert, setCustomSslCert] = useState(tenantDomain.custom_ssl_certificate || '');
    const [customSslKey, setCustomSslKey] = useState(tenantDomain.custom_ssl_private_key || '');

    // Domain Search State
    const [searchQuery, setSearchQuery] = useState('');
    const [isSearching, setIsSearching] = useState(false);
    const [searchResult, setSearchResult] = useState<{
        domain: string;
        available: boolean;
        price: number;
        currency: string;
        error?: string;
    } | null>(null);

    const [isLoading, setIsLoading] = useState(false);

    // Save Subdomain
    const handleSaveSubdomain = (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        router.post(route('settings.domains.subdomain.save'), { subdomain }, {
            onFinish: () => setIsLoading(false)
        });
    };

    // Verify Custom Domain
    const handleVerifyCustomDomain = (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        router.post(route('settings.domains.custom.verify'), { custom_domain: customDomain }, {
            onFinish: () => setIsLoading(false)
        });
    };

    // Save SSL Certificates
    const handleSaveSsl = (e: React.FormEvent) => {
        e.preventDefault();
        setIsLoading(true);
        router.post(route('settings.domains.ssl.save'), {
            custom_ssl_certificate: customSslCert,
            custom_ssl_private_key: customSslKey
        }, {
            onFinish: () => setIsLoading(false)
        });
    };

    // Search Domain Availability
    const handleSearchDomain = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!searchQuery) return;
        setIsSearching(true);
        setSearchResult(null);

        try {
            const response = await fetch(route('settings.domains.search'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''
                },
                body: JSON.stringify({ domain: searchQuery })
            });
            const data = await response.json();
            if (response.ok) {
                setSearchResult(data);
            } else {
                setSearchResult({ domain: searchQuery, available: false, price: 0, currency: 'USD', error: data.error });
            }
        } catch (err) {
            setSearchResult({ domain: searchQuery, available: false, price: 0, currency: 'USD', error: t('Network error searching domain.') });
        } finally {
            setIsSearching(false);
        }
    };

    // Register / Buy Domain
    const handleBuyDomain = (domainName: string, price: number) => {
        if (!confirm(t('Are you sure you want to buy and register this domain for 1 Year? Price: ') + `$${price} USD`)) return;
        setIsLoading(true);
        router.post(route('settings.domains.purchase'), {
            domain: domainName,
            duration: 1,
            price: price
        }, {
            onFinish: () => {
                setIsLoading(false);
                setSearchResult(null);
                setSearchQuery('');
            }
        });
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Settings'), url: route('settings.index') },
                { label: t('Domains') }
            ]}
            pageTitle={t('Domain Settings')}
        >
            <Head title={t('Domain Settings')} />

            <div className="space-y-8 max-w-5xl mx-auto">
                <Tabs defaultValue="routing" className="w-full">
                    <TabsList className="grid w-full grid-cols-3 max-w-md">
                        <TabsTrigger value="routing">{t('Domain Setup')}</TabsTrigger>
                        <TabsTrigger value="buy">{t('Register Domain')}</TabsTrigger>
                        <TabsTrigger value="ssl">{t('SSL Certificates')}</TabsTrigger>
                    </TabsList>

                    {/* Tab 1: Domain Mapping & Setup */}
                    <TabsContent value="routing" className="space-y-6 mt-6">
                        {/* Subdomain Card */}
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Subdomain Settings')}</CardTitle>
                                <CardDescription>
                                    {t('Your GadaaCloud default URL. This remains active as a permanent fallback.')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSaveSubdomain} className="space-y-4">
                                    <div className="flex items-center gap-2 max-w-lg">
                                        <div className="flex-1">
                                            <Input
                                                value={subdomain}
                                                onChange={(e) => setSubdomain(e.target.value)}
                                                className="text-right pr-2"
                                                required
                                            />
                                        </div>
                                        <span className="text-muted-foreground font-medium">.{baseDomain}</span>
                                    </div>
                                    <Button type="submit" disabled={isLoading}>
                                        {isLoading && <RefreshCw className="h-4 w-4 mr-2 animate-spin" />}
                                        {t('Save Subdomain')}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>

                        {/* Custom Domain Card */}
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Custom Domain Setup')}</CardTitle>
                                <CardDescription>
                                    {t('Access GadaaCloud under your own corporate domain name.')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <form onSubmit={handleVerifyCustomDomain} className="space-y-4">
                                    <div className="grid gap-2 max-w-lg">
                                        <Label htmlFor="custom_domain">{t('Custom Domain')}</Label>
                                        <Input
                                            id="custom_domain"
                                            value={customDomain}
                                            onChange={(e) => setCustomDomain(e.target.value)}
                                            placeholder="e.g. company1.com"
                                            required
                                        />
                                    </div>

                                    {tenantDomain.custom_domain && (
                                        <div className="p-4 rounded-lg flex items-start gap-3 max-w-lg bg-muted">
                                            {tenantDomain.custom_domain_status === 'active' ? (
                                                <>
                                                    <CheckCircle2 className="h-5 w-5 text-emerald-500 mt-0.5" />
                                                    <div>
                                                        <p className="font-semibold text-emerald-700">{t('Domain Active')}</p>
                                                        <p className="text-sm text-muted-foreground">
                                                            {t('Your custom domain is resolved and successfully mapped.')}
                                                        </p>
                                                    </div>
                                                </>
                                            ) : (
                                                <>
                                                    <AlertTriangle className="h-5 w-5 text-amber-500 mt-0.5" />
                                                    <div>
                                                        <p className="font-semibold text-amber-700">{t('Pending DNS Verification')}</p>
                                                        <p className="text-sm text-muted-foreground">
                                                            {t('DNS records are not verified yet. Please configure DNS below.')}
                                                        </p>
                                                    </div>
                                                </>
                                            )}
                                        </div>
                                    )}

                                    <Button type="submit" disabled={isLoading} variant={tenantDomain.custom_domain ? 'outline' : 'default'}>
                                        {isLoading && <RefreshCw className="h-4 w-4 mr-2 animate-spin" />}
                                        {tenantDomain.custom_domain ? t('Verify DNS & Map') : t('Map Custom Domain')}
                                    </Button>
                                </form>

                                <div className="border-t pt-4">
                                    <h4 className="font-medium text-sm mb-2">{t('Required DNS Records')}</h4>
                                    <p className="text-xs text-muted-foreground mb-4">
                                        {t('Point your custom domain to GadaaCloud by adding these records to your domain provider settings:')}
                                    </p>
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>{t('Type')}</TableHead>
                                                <TableHead>{t('Host')}</TableHead>
                                                <TableHead>{t('Points To (Value)')}</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow>
                                                <TableCell className="font-mono">A</TableCell>
                                                <TableCell className="font-mono">@</TableCell>
                                                <TableCell className="font-mono">{serverIp}</TableCell>
                                            </TableRow>
                                            <TableRow>
                                                <TableCell className="font-mono">CNAME</TableCell>
                                                <TableCell className="font-mono">www</TableCell>
                                                <TableCell className="font-mono">{baseDomain}</TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Tab 2: Buy / Register Domain */}
                    <TabsContent value="buy" className="space-y-6 mt-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Search & Register Domain')}</CardTitle>
                                <CardDescription>
                                    {t('Register a domain instantly through GadaaCloud powered by Dynadot API.')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSearchDomain} className="flex gap-2 max-w-lg mb-6">
                                    <div className="relative flex-1">
                                        <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            value={searchQuery}
                                            onChange={(e) => setSearchQuery(e.target.value)}
                                            placeholder={t('Enter domain to search... e.g. company.com')}
                                            className="pl-9"
                                            required
                                        />
                                    </div>
                                    <Button type="submit" disabled={isSearching}>
                                        {isSearching ? <RefreshCw className="h-4 w-4 animate-spin" /> : t('Search')}
                                    </Button>
                                </form>

                                {/* Search Result Display */}
                                {searchResult && (
                                    <div className="p-4 border rounded-lg max-w-lg bg-card shadow-sm space-y-4">
                                        {searchResult.error ? (
                                            <p className="text-destructive text-sm font-medium">{searchResult.error}</p>
                                        ) : (
                                            <div className="flex justify-between items-center">
                                                <div>
                                                    <p className="font-semibold text-lg text-foreground">{searchResult.domain}</p>
                                                    {searchResult.available ? (
                                                        <p className="text-xs text-emerald-600 font-medium">{t('Domain is Available!')}</p>
                                                    ) : (
                                                        <p className="text-xs text-destructive font-medium">{t('Domain is Unavailable.')}</p>
                                                    )}
                                                </div>
                                                {searchResult.available && (
                                                    <div className="text-right">
                                                        <p className="font-bold text-xl text-emerald-700">
                                                            ${searchResult.price} {searchResult.currency}
                                                            <span className="text-xs text-muted-foreground font-normal">/{t('yr')}</span>
                                                        </p>
                                                        <Button
                                                            size="sm"
                                                            onClick={() => handleBuyDomain(searchResult.domain, searchResult.price)}
                                                            className="mt-2"
                                                            disabled={isLoading}
                                                        >
                                                            <CreditCard className="h-4 w-4 mr-2" />
                                                            {t('Register Now')}
                                                        </Button>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                )}

                                {/* Available TLDs baseline info */}
                                <div className="mt-8 border-t pt-4">
                                    <h4 className="font-semibold text-sm mb-3">{t('TLD Annual Pricing')}</h4>
                                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 max-w-xl">
                                        {Object.entries(tldPrices).map(([tld, price]) => (
                                            <div key={tld} className="p-3 border rounded-lg text-center bg-muted/50">
                                                <p className="font-bold text-foreground text-sm uppercase">.{tld}</p>
                                                <p className="text-emerald-700 text-sm font-bold">${price}</p>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Domain Order History */}
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Domain Orders')}</CardTitle>
                                <CardDescription>{t('Track your domain registrations and invoice statuses.')}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>{t('Domain')}</TableHead>
                                            <TableHead>{t('Price')}</TableHead>
                                            <TableHead>{t('Registration Status')}</TableHead>
                                            <TableHead>{t('Date')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {orders.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={4} className="text-center text-muted-foreground py-6">
                                                    {t('No domain orders found.')}
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            orders.map((order) => (
                                                <TableRow key={order.id}>
                                                    <TableCell className="font-medium">{order.domain}</TableCell>
                                                    <TableCell>${order.amount} {order.currency}</TableCell>
                                                    <TableCell>
                                                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                                                            order.dynadot_status === 'completed' ? 'bg-emerald-100 text-emerald-800' :
                                                            order.dynadot_status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'
                                                        }`}>
                                                            {order.dynadot_status.toUpperCase()}
                                                        </span>
                                                        {order.dynadot_error && (
                                                            <p className="text-xs text-red-500 mt-1 max-w-xs">{order.dynadot_error}</p>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>{new Date(order.created_at).toLocaleDateString()}</TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Tab 3: Custom SSL Upload */}
                    <TabsContent value="ssl" className="space-y-6 mt-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Custom SSL Certificate')}</CardTitle>
                                <CardDescription>
                                    {t('Upload Sectigo, Comodo, or custom SSL certificates for your mapped domain.')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={handleSaveSsl} className="space-y-6">
                                    <div className="grid gap-2">
                                        <Label htmlFor="ssl_cert">{t('SSL Certificate (PEM Format)')}</Label>
                                        <Textarea
                                            id="ssl_cert"
                                            value={customSslCert}
                                            onChange={(e) => setCustomSslCert(e.target.value)}
                                            placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----"
                                            rows={6}
                                            className="font-mono text-xs"
                                            required
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="ssl_key">{t('SSL Private Key (PEM Format)')}</Label>
                                        <Textarea
                                            id="ssl_key"
                                            value={customSslKey}
                                            onChange={(e) => setCustomSslKey(e.target.value)}
                                            placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----"
                                            rows={6}
                                            className="font-mono text-xs"
                                            required
                                        />
                                    </div>
                                    <Button type="submit" disabled={isLoading}>
                                        {isLoading && <RefreshCw className="h-4 w-4 mr-2 animate-spin" />}
                                        <ShieldCheck className="h-4 w-4 mr-2" />
                                        {t('Save SSL Certificate')}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </AuthenticatedLayout>
    );
}
