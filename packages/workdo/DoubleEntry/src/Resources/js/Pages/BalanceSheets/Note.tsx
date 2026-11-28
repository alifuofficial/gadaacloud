import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import { isRichTextEmpty } from '@/utils/helpers';

interface NoteProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    balanceSheetId: number;
}

export default function Note({ open, onOpenChange, balanceSheetId }: NoteProps) {
    const { t } = useTranslation();

    const { data, setData, post, processing, errors, setError, clearErrors, reset } = useForm({
        note_title: '',
        note_content: ''
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isRichTextEmpty(data.note_content)) {
            setError('note_content', t('The note content field is required.'));
            return;
        }
        clearErrors('note_content');
        post(route('double-entry.balance-sheets.add-note', balanceSheetId), {
            onSuccess: () => {
                reset();
                onOpenChange(false);
            }
        });
    };

    const handleClose = () => {
        reset();
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('Add Note')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <Label htmlFor="note_title">{t('Note Title')}</Label>
                        <Input
                            id="note_title"
                            value={data.note_title}
                            onChange={(e) => setData('note_title', e.target.value)}
                            className={errors.note_title ? 'border-red-500' : ''}
                            placeholder={t('Enter note title')}
                            required
                        />
                        {errors.note_title && <p className="text-red-500 text-sm">{errors.note_title}</p>}
                    </div>
                    <div>
                        <Label htmlFor="note_content" required>{t('Note Content')}</Label>
                        <RichTextEditor
                            id="note_content"
                            variant="compact"
                            minHeight="120px"
                            content={data.note_content}
                            onChange={(value) => {
                                clearErrors('note_content');
                                setData('note_content', value);
                            }}
                            placeholder={t('Enter note content')}
                        />
                        {errors.note_content && <p className="text-red-500 text-sm">{errors.note_content}</p>}
                    </div>
                    <div className="flex justify-end gap-2 pt-4">
                        <Button type="button" variant="outline" onClick={handleClose}>
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? t('Adding...') : t('Add Note')}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
