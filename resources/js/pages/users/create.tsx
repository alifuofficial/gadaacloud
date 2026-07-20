import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useForm, usePage, router } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import InputError from "@/components/ui/input-error";
import { PhoneInputComponent } from "@/components/ui/phone-input";
import { CreateUserProps, CreateUserFormData } from './types';

export default function Create({ onSuccess, roles = {} }: CreateUserProps) {
    const { t } = useTranslation();
    const { auth, plans = [] } = usePage().props as any;
    const { data, setData, post, processing, errors } = useForm<any>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        mobile_no: '',
        type: '',
        is_enable_login: true,
        subdomain: '',
        custom_domain: '',
        plan_id: '',
        plan_duration: 'month',
    });

    const isSuperAdmin = auth.user?.type === 'superadmin';

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('users.store'), {
            onSuccess: () => {
                onSuccess();
            }
        });
    };

    return (
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{t('Create User')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <Label htmlFor="name">{t('Name')}</Label>
                    <Input
                        id="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder={t('Enter full name')}
                        required
                    />
                    <InputError message={errors.name} />
                </div>
                <div>
                    <Label htmlFor="email">{t('Email')}</Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder={t('Enter email address')}
                        required
                    />
                    <InputError message={errors.email} />
                </div>
                <div>
                    <PhoneInputComponent
                        label={t('Mobile Number')}
                        value={data.mobile_no}
                        onChange={(value) => setData('mobile_no', value)}
                        placeholder="+1234567890"
                        error={errors.mobile_no}
                    />
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <Label htmlFor="password">{t('Password')}</Label>
                        <Input
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder={t('Enter password')}
                            required
                        />
                        <InputError message={errors.password} />
                    </div>
                    <div>
                        <Label htmlFor="password_confirmation">{t('Confirm Password')}</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            placeholder={t('Confirm password')}
                            required
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>
                </div>
                <div className={`grid ${isSuperAdmin ? 'grid-cols-1' : 'grid-cols-2'} gap-4`}>
                    {!isSuperAdmin && (
                        <div>
                            <Label htmlFor="type">{t('Role')}</Label>
                            <Select value={data.type} onValueChange={(value) => setData('type', value)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(roles).map(([id, label]) => (
                                        <SelectItem key={id} value={id}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {Object.keys(roles).length === 0 && auth.user?.permissions?.includes('create-roles') && (
                                <p className="text-xs text-gray-500 mb-1">
                                    {t('Create role here.')} <button onClick={() => router.get(route('roles.create'))} className="text-blue-600 hover:underline">{t('Create role')}</button>
                                </p>
                            )}
                            <InputError message={errors.type} />
                        </div>
                    )}
                    <div>
                        <Label htmlFor="is_enable_login">{t('Login Status')}</Label>
                        <Select value={data.is_enable_login ? "1" : "0"} onValueChange={(value) => setData('is_enable_login', value === "1")}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1">{t('Enabled')}</SelectItem>
                                <SelectItem value="0">{t('Disabled')}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.is_enable_login} />
                    </div>
                </div>

                {isSuperAdmin && (
                    <>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="subdomain">{t('Subdomain')}</Label>
                                <Input
                                    id="subdomain"
                                    value={data.subdomain}
                                    onChange={(e) => setData('subdomain', e.target.value)}
                                    placeholder={t('company1')}
                                />
                                <InputError message={errors.subdomain} />
                            </div>
                            <div>
                                <Label htmlFor="custom_domain">{t('Custom Domain (Optional)')}</Label>
                                <Input
                                    id="custom_domain"
                                    value={data.custom_domain}
                                    onChange={(e) => setData('custom_domain', e.target.value)}
                                    placeholder="e.g. company1.com"
                                />
                                <InputError message={errors.custom_domain} />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="plan_id">{t('Subscription Plan')}</Label>
                                <Select value={data.plan_id} onValueChange={(value) => setData('plan_id', value)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Select Plan')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {plans.map((plan: any) => (
                                            <SelectItem key={plan.id} value={plan.id.toString()}>
                                                {plan.name} (${plan.package_price_monthly}/mo)
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.plan_id} />
                            </div>
                            <div>
                                <Label htmlFor="plan_duration">{t('Plan Duration')}</Label>
                                <Select value={data.plan_duration} onValueChange={(value) => setData('plan_duration', value)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="month">{t('Monthly')}</SelectItem>
                                        <SelectItem value="year">{t('Yearly')}</SelectItem>
                                        <SelectItem value="trial">{t('Trial')}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.plan_duration} />
                            </div>
                        </div>
                    </>
                )}

                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={onSuccess}>
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? t('Creating...') : t('Create')}
                    </Button>
                </div>
            </form>
        </DialogContent>
    );
}
