import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useTranslation } from 'react-i18next';
import { MessageCircle } from 'lucide-react';
import { InterviewRound } from './types';
import { RichTextViewer } from '@/components/ui/rich-text-viewer';
import { isRichTextEmpty } from '@/utils/helpers';

interface ViewProps {
    interviewround: InterviewRound;
}

export default function View({ interviewround }: ViewProps) {
    const { t } = useTranslation();

    return (
        <DialogContent className="max-w-2xl">
            <DialogHeader className="flex-shrink-0 pb-4 border-b">
                <div className="flex items-center gap-3">
                    <div className="p-2 bg-primary/10 rounded-lg">
                        <MessageCircle className="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <DialogTitle className="text-xl font-semibold">{t('Job Requisition Details')}</DialogTitle>
                    </div>
                </div>
            </DialogHeader>

            <div className="p-4 space-y-6">
                {/* Two Column Layout */}
                <div className="grid grid-cols-2 gap-8">
                    {/* Left Column */}
                    <div className="space-y-4">
                        <div>
                            <label className="font-medium text-gray-700">{t('Name')}:</label>
                            <p className="text-gray-900">{interviewround.name}</p>
                        </div>

                        <div>
                            <label className="font-medium text-gray-700">{t('Job')}:</label>
                            <p className="text-gray-900">{interviewround.job_posting?.title || '-'}</p>
                        </div>
                    </div>

                    {/* Right Column */}
                    <div className="space-y-4">
                        <div>
                            <label className="font-medium text-gray-700">{t('Sequence Number')}:</label>
                            <div>
                                <span className="px-2 py-1 rounded-full text-sm bg-blue-100 text-blue-800">
                                    {interviewround.sequence_number || '-'}
                                </span>
                            </div>
                        </div>

                        <div>
                            <label className="font-medium text-gray-700">{t('Status')}:</label>
                            <div>
                                <span className={`px-2 py-1 rounded-full text-sm ${
                                    interviewround.status === "0" ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                }`}>
                                    {interviewround.status === "0" ? t('Active') : t('Inactive')}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Description - Full Width */}
                {!isRichTextEmpty(interviewround.description) && (
                    <div>
                        <label className="font-medium text-gray-700 block mb-1">{t('Description')}:</label>
                        <RichTextViewer content={interviewround.description} compact />
                    </div>
                )}
            </div>
        </DialogContent>
    );
}