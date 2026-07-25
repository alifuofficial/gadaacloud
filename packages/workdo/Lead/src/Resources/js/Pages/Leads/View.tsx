import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useTranslation } from 'react-i18next';
import { Users } from 'lucide-react';
import { Lead } from './types';
import DocumentChatter from '@/components/chatter/document-chatter';

interface ViewProps {
    lead: Lead;
}

export default function View({ lead }: ViewProps) {
    const { t } = useTranslation();

    return (
        <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
            <DialogHeader className="pb-4 border-b">
                <div className="flex items-center gap-3">
                    <div className="p-2 bg-primary/10 rounded-lg">
                        <Users className="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <DialogTitle className="text-xl font-semibold">{t('Lead Details')}</DialogTitle>
                        <p className="text-sm text-muted-foreground">{lead.name} ({lead.email})</p>
                    </div>
                </div>
            </DialogHeader>

            <div className="space-y-4 pt-2">
                <div className="grid grid-cols-2 gap-4 text-xs p-3 bg-gray-50 rounded-xl border">
                    <div>
                        <span className="text-gray-500 font-semibold">{t('Subject')}:</span>
                        <div className="text-gray-900 font-medium mt-0.5">{lead.subject || lead.name}</div>
                    </div>
                    <div>
                        <span className="text-gray-500 font-semibold">{t('Phone')}:</span>
                        <div className="text-gray-900 font-medium mt-0.5">{lead.phone || 'N/A'}</div>
                    </div>
                </div>

                {/* Universal Document Chatter Component */}
                <DocumentChatter model="lead" recordId={lead.id} title={t('Lead Activity & Communication Stream')} />
            </div>
        </DialogContent>
    );
}