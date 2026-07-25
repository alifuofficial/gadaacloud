import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Sparkles, Cpu, Key, ShieldCheck, Zap, Layers, CheckCircle2, Eye, EyeOff, Info, Coins, ShieldAlert } from 'lucide-react';

interface SetupProps {
    aiSettings: {
        provider: string;
        model: string;
        apiKey: string;
        tokenPricePer1k: string;
        temperature: string;
        maxTokens: string;
        systemPrompt: string;
    };
}

export default function CopilotSetup({ aiSettings }: SetupProps) {
    const { t } = useTranslation();
    const [showKey, setShowKey] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        provider: aiSettings.provider || 'gemini',
        model: aiSettings.model || 'gemini-1.5-flash',
        apiKey: aiSettings.apiKey || '',
        tokenPricePer1k: aiSettings.tokenPricePer1k || '0.05',
        temperature: aiSettings.temperature || '0.3',
        maxTokens: aiSettings.maxTokens || '2048',
        systemPrompt: aiSettings.systemPrompt || '',
    });

    let indexUrl = '/settings/copilot';
    let saveUrl = '/settings/copilot/setup';
    try {
        if (typeof route === 'function') {
            indexUrl = route('settings.copilot.index');
            saveUrl = route('settings.copilot.setup.save');
        }
    } catch (e) {}

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(saveUrl);
    };

    const providerModels: Record<string, Array<{ id: string; name: string; desc: string }>> = {
        gemini: [
            { id: 'gemini-1.5-flash', name: 'Google Gemini 1.5 Flash (Recommended)', desc: 'Ultra-fast response (<800ms), 1M token context window, lowest cost for multi-tenant ERP operations.' },
            { id: 'gemini-1.5-pro', name: 'Google Gemini 1.5 Pro', desc: 'Deep financial reasoning, multi-page document PDF parsing, and high-precision tax calculations.' },
        ],
        openai: [
            { id: 'gpt-4o-mini', name: 'OpenAI GPT-4o Mini', desc: 'Fast, lightweight model for basic ERP query answering.' },
            { id: 'gpt-4o', name: 'OpenAI GPT-4o', desc: 'High intelligence multimodal reasoning model for enterprise workflows.' },
        ],
        anthropic: [
            { id: 'claude-3-5-sonnet', name: 'Anthropic Claude 3.5 Sonnet', desc: 'Exceptional instruction following and structured JSON analysis.' },
        ],
        deepseek: [
            { id: 'deepseek-chat', name: 'DeepSeek V3 / R1', desc: 'Open-weights high-efficiency model for financial forecasting.' },
        ],
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('GadaaCloud Copilot'), url: indexUrl }, { label: t('Global AI Setup') }]}
            pageTitle={t('Global AI Model & Token Pricing Setup')}
        >
            <Head title={t('Global AI Model & Token Pricing Setup')} />

            <div className="max-w-6xl mx-auto space-y-6">
                {/* Superadmin Exclusive Banner */}
                <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-950 via-purple-950 to-indigo-950 text-white p-6 md:p-8 shadow-xl border border-purple-500/30">
                    <div className="relative z-10 space-y-4">
                        <div className="flex items-center gap-3">
                            <Badge className="bg-amber-500/20 text-amber-300 border-amber-400/30 px-3 py-1 text-xs font-semibold">
                                <ShieldAlert className="w-3.5 h-3.5 mr-1 text-amber-400" />
                                {t('Superadmin Control Panel')}
                            </Badge>
                            <span className="text-xs text-purple-300 font-mono">Centralized Multi-Tenant Engine</span>
                        </div>

                        <h2 className="text-2xl md:text-3xl font-extrabold tracking-tight">
                            {t('Global AI Provider & Token Metering Rates')}
                        </h2>

                        <p className="text-slate-300 text-sm md:text-base max-w-3xl leading-relaxed">
                            {t('As Superadmin, you configure the central AI provider and API Key. Tenant companies will use this central model automatically, and their token consumption will be metered based on your configured Token Rate.')}
                        </p>
                    </div>
                </div>

                {/* AI Configuration Form */}
                <form onSubmit={handleSubmit} className="space-y-6">
                    <Card className="border shadow-sm">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <Cpu className="w-5 h-5 text-purple-600" />
                                {t('Global Provider & Token Pricing')}
                            </CardTitle>
                            <CardDescription>
                                {t('Configure the API Key and price rate charged per 1,000 AI tokens used by tenant companies.')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Token Pricing Rate */}
                            <div className="p-4 rounded-xl bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200 space-y-2">
                                <Label htmlFor="tokenPricePer1k" className="flex items-center gap-2 text-emerald-900 font-bold">
                                    <Coins className="w-4 h-4 text-emerald-600" />
                                    {t('Token Price Rate (per 1,000 Tokens in ETB / Currency)')}
                                </Label>
                                <div className="flex items-center gap-3">
                                    <Input
                                        id="tokenPricePer1k"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.tokenPricePer1k}
                                        onChange={(e) => setData('tokenPricePer1k', e.target.value)}
                                        className="bg-white font-mono text-sm max-w-xs border-emerald-300"
                                    />
                                    <span className="text-xs text-emerald-800 font-medium">ETB per 1,000 AI Tokens</span>
                                </div>
                                <p className="text-[11px] text-emerald-700">
                                    Example: Setting <code>0.05</code> means 1,000 tokens consumed by a tenant generates 0.05 Br in metered AI value.
                                </p>
                            </div>

                            {/* Provider Selection */}
                            <div className="space-y-2">
                                <Label htmlFor="provider">{t('AI Provider')}</Label>
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    {[
                                        { id: 'gemini', name: 'Google Gemini', badge: 'Recommended', color: 'border-emerald-500 bg-emerald-50/50' },
                                        { id: 'openai', name: 'OpenAI GPT', badge: 'Popular', color: 'border-blue-500 bg-blue-50/50' },
                                        { id: 'anthropic', name: 'Anthropic Claude', badge: 'Powerful', color: 'border-amber-500 bg-amber-50/50' },
                                        { id: 'deepseek', name: 'DeepSeek AI', badge: 'Open-Weights', color: 'border-purple-500 bg-purple-50/50' },
                                    ].map((prov) => (
                                        <div
                                            key={prov.id}
                                            onClick={() => {
                                                setData('provider', prov.id);
                                                setData('model', providerModels[prov.id][0].id);
                                            }}
                                            className={`cursor-pointer border-2 rounded-xl p-4 text-center transition-all ${
                                                data.provider === prov.id ? `${prov.color} shadow-sm font-semibold` : 'border-gray-200 hover:border-gray-300'
                                            }`}
                                        >
                                            <div className="text-sm font-bold text-gray-900">{prov.name}</div>
                                            <Badge variant="outline" className="mt-2 text-[10px]">
                                                {prov.badge}
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Model Selection */}
                            <div className="space-y-3">
                                <Label htmlFor="model">{t('Select Model')}</Label>
                                <div className="space-y-2">
                                    {providerModels[data.provider]?.map((mod) => (
                                        <label
                                            key={mod.id}
                                            className={`flex items-start gap-3 p-4 rounded-xl border cursor-pointer transition-all ${
                                                data.model === mod.id ? 'border-purple-600 bg-purple-50/30 ring-1 ring-purple-600' : 'border-gray-200 hover:bg-gray-50'
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="model"
                                                value={mod.id}
                                                checked={data.model === mod.id}
                                                onChange={(e) => setData('model', e.target.value)}
                                                className="mt-1 text-purple-600 focus:ring-purple-500"
                                            />
                                            <div className="space-y-1">
                                                <div className="font-semibold text-sm text-gray-900 flex items-center gap-2">
                                                    {mod.name}
                                                    {mod.id.includes('flash') && <Badge className="bg-emerald-600 text-white text-[10px]">Fastest</Badge>}
                                                </div>
                                                <p className="text-xs text-gray-500">{mod.desc}</p>
                                            </div>
                                        </label>
                                    ))}
                                </div>
                            </div>

                            {/* Global API Key */}
                            <div className="space-y-2">
                                <Label htmlFor="apiKey" className="flex items-center gap-2">
                                    <Key className="w-4 h-4 text-gray-500" />
                                    {t('Global AI API Key')}
                                </Label>
                                <div className="relative">
                                    <Input
                                        id="apiKey"
                                        type={showKey ? 'text' : 'password'}
                                        value={data.apiKey}
                                        onChange={(e) => setData('apiKey', e.target.value)}
                                        placeholder={`Enter global ${data.provider.toUpperCase()} API key...`}
                                        className="pr-10 font-mono text-sm"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowKey(!showKey)}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    >
                                        {showKey ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                                    </button>
                                </div>
                            </div>

                            {/* Advanced Parameters */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                                <div className="space-y-2">
                                    <Label htmlFor="temperature">{t('Temperature (Creativity)')}</Label>
                                    <Input
                                        id="temperature"
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        max="1"
                                        value={data.temperature}
                                        onChange={(e) => setData('temperature', e.target.value)}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="maxTokens">{t('Max Output Tokens')}</Label>
                                    <Input
                                        id="maxTokens"
                                        type="number"
                                        step="256"
                                        min="256"
                                        max="8192"
                                        value={data.maxTokens}
                                        onChange={(e) => setData('maxTokens', e.target.value)}
                                    />
                                </div>
                            </div>

                            {/* System Prompt */}
                            <div className="space-y-2">
                                <Label htmlFor="systemPrompt">{t('GadaaCloud Copilot System Prompt')}</Label>
                                <Textarea
                                    id="systemPrompt"
                                    rows={4}
                                    value={data.systemPrompt}
                                    onChange={(e) => setData('systemPrompt', e.target.value)}
                                    className="font-mono text-xs"
                                />
                            </div>
                        </CardContent>
                        <CardFooter className="flex justify-end gap-3 bg-gray-50 border-t px-6 py-4">
                            <Button type="submit" disabled={processing} className="bg-purple-700 hover:bg-purple-800 text-white">
                                <CheckCircle2 className="w-4 h-4 mr-2" />
                                {t('Save Global AI Configuration')}
                            </Button>
                        </CardFooter>
                    </Card>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
