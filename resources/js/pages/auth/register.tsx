import InputError from '@/components/ui/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function Register({ baseDomain = 'gadaa.cloud' }: { baseDomain?: string }) {
    const { t } = useTranslation();
    const [step, setStep] = useState(1);
    const [subdomainStatus, setSubdomainStatus] = useState<'checking' | 'available' | 'taken' | 'invalid' | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        subdomain: '',
        password: '',
        password_confirmation: '',
    });

    useEffect(() => {
        return () => {
            reset('password', 'password_confirmation');
        };
    }, []);

    // Jump to the step with server validation errors
    useEffect(() => {
        if (errors.name || errors.email) {
            setStep(1);
        } else if (errors.subdomain) {
            setStep(2);
        } else if (errors.password || errors.password_confirmation) {
            setStep(3);
        }
    }, [errors]);

    // Live Subdomain Availability Check with Debounce
    useEffect(() => {
        if (!data.subdomain.trim()) {
            setSubdomainStatus(null);
            return;
        }

        const subdomainRegex = /^[a-z0-9-]+$/;
        if (!subdomainRegex.test(data.subdomain)) {
            setSubdomainStatus('invalid');
            errors.subdomain = t('Subdomain can only contain lowercase letters, numbers, and dashes');
            return;
        }

        setSubdomainStatus('checking');
        errors.subdomain = '';

        const timer = setTimeout(async () => {
            try {
                const response = await fetch(`/check-subdomain?subdomain=${data.subdomain}`);
                const res = await response.json();
                if (res.available) {
                    setSubdomainStatus('available');
                    errors.subdomain = '';
                } else {
                    setSubdomainStatus('taken');
                    errors.subdomain = res.message || t('This subdomain is already taken');
                }
            } catch (err) {
                setSubdomainStatus(null);
            }
        }, 500);

        return () => clearTimeout(timer);
    }, [data.subdomain]);

    const validateStep1 = () => {
        let valid = true;
        if (!data.name.trim()) {
            errors.name = t('Name is required');
            valid = false;
        } else {
            errors.name = '';
        }

        if (!data.email.trim()) {
            errors.email = t('Email address is required');
            valid = false;
        } else if (!data.email.includes('@')) {
            errors.email = t('Please enter a valid email address');
            valid = false;
        } else {
            errors.email = '';
        }

        setData({ ...data }); // Trigger state update to display local errors
        return valid;
    };

    const validateStep2 = () => {
        let valid = true;
        const subdomainRegex = /^[a-z0-9-]+$/;
        if (!data.subdomain.trim()) {
            errors.subdomain = t('Subdomain is required');
            valid = false;
        } else if (!subdomainRegex.test(data.subdomain)) {
            errors.subdomain = t('Subdomain can only contain lowercase letters, numbers, and dashes');
            valid = false;
        } else if (subdomainStatus === 'taken') {
            errors.subdomain = t('Subdomain is already taken');
            valid = false;
        } else if (subdomainStatus === 'checking') {
            errors.subdomain = t('Checking availability...');
            valid = false;
        } else if (subdomainStatus === 'invalid') {
            errors.subdomain = t('Invalid subdomain format');
            valid = false;
        } else {
            errors.subdomain = '';
        }

        setData({ ...data });
        return valid;
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (step < 3) {
            handleNext();
            return;
        }
        post(route('register'));
    };

    const handleNext = () => {
        if (step === 1 && validateStep1()) {
            setStep(2);
        } else if (step === 2 && validateStep2()) {
            setStep(3);
        }
    };

    const handleBack = () => {
        if (step > 1) {
            setStep(step - 1);
        }
    };

    const stepsCount = 3;

    return (
        <AuthLayout
            title={t('Create an account')}
            description={t('Enter your details below to create your account')}
        >
            <Head title={t('Register')} />

            {/* Step Progress Indicator */}
            <div className="mb-8 px-2">
                <div className="flex justify-between items-center relative">
                    {/* Background line */}
                    <div className="absolute left-0 top-1/2 -translate-y-1/2 w-full h-[2px] bg-gray-200 dark:bg-gray-700 z-0"></div>
                    {/* Active line */}
                    <div 
                        className="absolute left-0 top-1/2 -translate-y-1/2 h-[2px] bg-primary transition-all duration-300 z-0"
                        style={{ width: `${((step - 1) / (stepsCount - 1)) * 100}%` }}
                    ></div>

                    {/* Step dots */}
                    {[1, 2, 3].map((num) => (
                        <div 
                            key={num} 
                            className={`w-8 h-8 rounded-full flex items-center justify-center font-semibold text-xs border-2 z-10 transition-all duration-300 ${
                                step >= num 
                                    ? 'bg-primary border-primary text-white scale-110 shadow-md' 
                                    : 'bg-white dark:bg-slate-800 border-gray-300 dark:border-gray-600 text-gray-500'
                            }`}
                        >
                            {num}
                        </div>
                    ))}
                </div>
                <div className="flex justify-between mt-2 text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    <span className={step >= 1 ? 'text-primary' : ''}>{t('Profile')}</span>
                    <span className={step >= 2 ? 'text-primary' : ''}>{t('Subdomain')}</span>
                    <span className={step >= 3 ? 'text-primary' : ''}>{t('Security')}</span>
                </div>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <div className="space-y-4 min-h-[180px]">
                    {/* STEP 1: Name and Email */}
                    {step === 1 && (
                        <div className="space-y-4 animate-in fade-in duration-300">
                            <div className="space-y-2">
                                <Label htmlFor="name" className="text-sm font-medium text-gray-900 dark:text-white">{t('Name')}</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    name="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    placeholder={t('Full name')}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:outline-none transition-colors placeholder-gray-400 dark:bg-slate-700 dark:text-white"
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="email" className="text-sm font-medium text-gray-900 dark:text-white">{t('Email address')}</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:outline-none transition-colors placeholder-gray-400 dark:bg-slate-700 dark:text-white"
                                />
                                <InputError message={errors.email} />
                            </div>
                        </div>
                    )}

                    {/* STEP 2: Subdomain Selection */}
                    {step === 2 && (
                        <div className="space-y-4 animate-in fade-in duration-300">
                            <div className="space-y-2">
                                <Label htmlFor="subdomain" className="text-sm font-medium text-gray-900 dark:text-white">
                                    {t('Choose your Subdomain')}
                                </Label>
                                <div className="flex items-center mt-1 rounded-md shadow-sm border border-gray-300 dark:border-gray-600 focus-within:border-primary dark:focus-within:border-primary overflow-hidden bg-white dark:bg-slate-700">
                                    <Input
                                        id="subdomain"
                                        type="text"
                                        name="subdomain"
                                        value={data.subdomain}
                                        onChange={(e) => setData('subdomain', e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''))}
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        placeholder="your-company"
                                        className="flex-1 border-0 focus:ring-0 focus:outline-none px-3 py-2 text-sm dark:bg-transparent dark:text-white"
                                    />
                                    <span className="px-3 py-2 text-sm bg-gray-50 dark:bg-slate-800 text-gray-500 border-l border-gray-300 dark:border-gray-600 font-medium">
                                        .{baseDomain}
                                    </span>
                                </div>
                                {subdomainStatus === 'checking' && (
                                    <p className="text-xs text-blue-500 mt-1 flex items-center gap-1.5 animate-pulse">
                                        <span className="w-1.5 h-1.5 rounded-full bg-blue-500 animate-ping"></span>
                                        {t('Checking availability...')}
                                    </p>
                                )}
                                {subdomainStatus === 'available' && (
                                    <p className="text-xs text-green-600 dark:text-green-400 mt-1 flex items-center gap-1.5 font-medium">
                                        ✨ {t('Subdomain is available!')}
                                    </p>
                                )}
                                {subdomainStatus === 'taken' && (
                                    <p className="text-xs text-red-500 mt-1 flex items-center gap-1.5 font-medium">
                                        ❌ {t('This subdomain is already taken')}
                                    </p>
                                )}
                                {subdomainStatus === 'invalid' && (
                                    <p className="text-xs text-amber-500 mt-1 flex items-center gap-1.5 font-medium">
                                        ⚠️ {t('Invalid format')}
                                    </p>
                                )}
                                <InputError message={errors.subdomain} />
                                
                                <p className="mt-3 text-xs text-gray-500 dark:text-gray-400 leading-relaxed bg-slate-50 dark:bg-slate-800/40 p-3 rounded-lg border border-dashed border-gray-200 dark:border-gray-700">
                                    💡 {t('You can upgrade your sub-domain to a full custom domain later. You can access your workspace through your subdomain or through current on gadaa.cloud.')}
                                </p>
                            </div>
                        </div>
                    )}

                    {/* STEP 3: Password Security */}
                    {step === 3 && (
                        <div className="space-y-4 animate-in fade-in duration-300">
                            <div className="space-y-2">
                                <Label htmlFor="password" className="text-sm font-medium text-gray-900 dark:text-white">{t('Password')}</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="new-password"
                                    placeholder={t('Password')}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:outline-none transition-colors placeholder-gray-400 dark:bg-slate-700 dark:text-white"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="password_confirmation" className="text-sm font-medium text-gray-900 dark:text-white">
                                    {t('Confirm password')}
                                </Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    required
                                    tabIndex={2}
                                    autoComplete="new-password"
                                    placeholder={t('Confirm password')}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:outline-none transition-colors placeholder-gray-400 dark:bg-slate-700 dark:text-white"
                                />
                                <InputError message={errors.password_confirmation} />
                            </div>
                        </div>
                    )}
                </div>

                {/* Navigation Buttons */}
                <div className="flex gap-3 mt-6">
                    {step > 1 && (
                        <Button
                            type="button"
                            onClick={handleBack}
                            variant="outline"
                            className="flex-1 border border-gray-300 dark:border-gray-600 dark:text-white text-gray-700 py-2.5 text-sm font-medium transition-all duration-200 rounded-md"
                        >
                            {t('BACK')}
                        </Button>
                    )}
                    
                    {step < 3 ? (
                        <Button
                            type="button"
                            onClick={handleNext}
                            disabled={step === 2 && subdomainStatus !== 'available'}
                            className="flex-1 bg-primary text-white py-2.5 text-sm font-medium tracking-wide transition-all duration-200 rounded-md shadow-md hover:shadow-lg transform hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {t('NEXT')}
                        </Button>
                    ) : (
                        <Button
                            type="submit"
                            disabled={processing}
                            className="flex-1 bg-primary text-white py-2.5 text-sm font-medium tracking-wide transition-all duration-200 rounded-md shadow-md hover:shadow-lg transform hover:scale-[1.02]"
                        >
                            {processing ? t('Loading...') : t('CREATE ACCOUNT')}
                        </Button>
                    )}
                </div>

                <div className="text-center mt-5">
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {t('Already have an account?')}{' '}
                        <Link href={route('login')} className="text-primary font-medium hover:underline">
                            {t('Log in')}
                        </Link>
                    </p>
                </div>
            </form>
        </AuthLayout>
    );
}
