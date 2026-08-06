import { Switch } from '@/components/ui/switch';
import { Globe, Type } from 'lucide-react';

interface GeneralProps {
    data: any;
    updateSectionData: (field: string, value: any) => void;
}

export default function General({ data, updateSectionData }: GeneralProps) {
    const { t } = useTranslation();

    return (
        <div className="space-y-6">
            <Card className="border-emerald-200 bg-emerald-50/30">
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-emerald-100 rounded-lg">
                                <Globe className="h-5 w-5 text-emerald-600" />
                            </div>
                            <div>
                                <CardTitle className="text-emerald-950">{t('Landing Page Status')}</CardTitle>
                                <p className="text-sm text-emerald-700">{t('Toggle whether visitors see the public landing page or are redirected directly to login.')}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Label className="text-sm font-bold text-emerald-900">{data.landingPageEnabled ? t('Enabled (Public Landing Page)') : t('Disabled (Redirect to Login)')}</Label>
                            <Switch
                                checked={data.landingPageEnabled !== false && data.landingPageEnabled !== 'off'}
                                onCheckedChange={(checked) => updateSectionData('landingPageEnabled', checked ? 'on' : 'off')}
                            />
                        </div>
                    </div>
                </CardHeader>
            </Card>

            <Card>
                <CardHeader>
                    <div className="flex items-center gap-3">
                        <div className="p-2 bg-blue-100 rounded-lg">
                            <Type className="h-5 w-5 text-blue-600" />
                        </div>
                        <div>
                            <CardTitle>{t('Company Information')}</CardTitle>
                            <p className="text-sm text-gray-500">{t('Basic company details for your landing page')}</p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>{t('Company Name')}</Label>
                            <Input 
                                value={data.company_name || ''}
                                onChange={(e) => updateSectionData('company_name', e.target.value)}
                                placeholder={t('Your Company Name')} 
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('Contact Email')}</Label>
                            <Input 
                                type="email" 
                                value={data.contact_email || ''}
                                onChange={(e) => updateSectionData('contact_email', e.target.value)}
                                placeholder="support@company.com" 
                            />
                        </div>
                        <div className="space-y-2">
                            <PhoneInputComponent
                                label={t('Contact Phone')}
                                value={data.contact_phone || ''}
                                onChange={(value) => updateSectionData('contact_phone', value)}
                                placeholder="+1 (555) 123-4567"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('Contact Address')}</Label>
                            <Input 
                                value={data.contact_address || ''}
                                onChange={(e) => updateSectionData('contact_address', e.target.value)}
                                placeholder="123 Business Ave, City, State" 
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}