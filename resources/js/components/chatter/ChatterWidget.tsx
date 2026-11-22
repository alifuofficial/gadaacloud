import React, { useState, useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import axios from 'axios';
import { toast } from 'sonner';
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from '@/components/ui/dialog';
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import {
    MessageSquare, FileText, Calendar, Paperclip, Send, CheckCircle2,
    Clock, AlertCircle, Phone, Mail, Users, Check, X, UserPlus,
    UserCheck, ChevronDown, ChevronUp, FileIcon, Download
} from 'lucide-react';

interface ChatterAttachment {
    id: number;
    file_name: string;
    file_path: string;
    file_type: string;
    file_size: number;
}

interface ChatterUser {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
}

interface ChatterMessage {
    id: number;
    user_id: number;
    user?: ChatterUser;
    message: string;
    type: 'note' | 'message' | 'activity' | 'diff';
    metadata?: any;
    attachments?: ChatterAttachment[];
    created_at: string;
}

interface ChatterActivity {
    id: number;
    activity_type: 'call' | 'email' | 'meeting' | 'todo' | 'review';
    title: string;
    due_date: string;
    status: 'pending' | 'completed';
    notes?: string | null;
    feedback?: string | null;
    user?: ChatterUser;
    assignee?: ChatterUser;
    completed_at?: string | null;
}

interface ChatterFollower {
    id: number;
    user_id: number;
    user?: ChatterUser;
}

export interface ChatterWidgetProps {
    modelType?: string;
    modelId?: number | string;
    model?: string;
    recordId?: number | string;
    title?: string;
    className?: string;
}

export default function ChatterWidget({
    modelType,
    modelId,
    model,
    recordId,
    title,
    className = ''
}: ChatterWidgetProps) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;

    const targetModel = modelType || model || '';
    const targetId = Number(modelId || recordId || 0);

    // SaaS subscription tier gating: Check if tenant has Chatter active in their plan
    const activatedPackages: string[] = auth?.user?.activatedPackages || [];
    const isSuperAdmin = auth?.user?.type === 'superadmin';
    const isChatterSubscribed = isSuperAdmin || activatedPackages.includes('Chatter') || activatedPackages.includes('chatter');

    if (!isChatterSubscribed) {
        return null; // Gracefully hidden if not subscribed
    }

    const [messages, setMessages] = useState<ChatterMessage[]>([]);
    const [activities, setActivities] = useState<ChatterActivity[]>([]);
    const [followers, setFollowers] = useState<ChatterFollower[]>([]);
    const [companyUsers, setCompanyUsers] = useState<ChatterUser[]>([]);
    const [isFollowing, setIsFollowing] = useState(false);
    const [loading, setLoading] = useState(true);

    // Active composer tab: null (collapsed), 'note', 'message', 'activity'
    const [activeTab, setActiveTab] = useState<'note' | 'message' | null>(null);
    const [messageText, setMessageText] = useState('');
    const [selectedFiles, setSelectedFiles] = useState<File[]>([]);
    const [isPosting, setIsPosting] = useState(false);

    // Activity modal
    const [showActivityModal, setShowActivityModal] = useState(false);
    const [actTitle, setActTitle] = useState('');
    const [actType, setActType] = useState<'call' | 'email' | 'meeting' | 'todo' | 'review'>('todo');
    const [actDueDate, setActDueDate] = useState('');
    const [actAssignee, setActAssignee] = useState<string>('');
    const [actNotes, setActNotes] = useState('');

    // Activity completion feedback modal
    const [completingActivityId, setCompletingActivityId] = useState<number | null>(null);
    const [activityFeedback, setActivityFeedback] = useState('');

    const fileInputRef = useRef<HTMLInputElement>(null);

    const loadStream = async () => {
        if (!targetModel || !targetId) return;
        try {
            setLoading(true);
            const res = await axios.get(route('chatter.stream', { model: targetModel, id: targetId }));
            if (res.data.success) {
                setMessages(res.data.messages || []);
                setActivities(res.data.activities || []);
                setFollowers(res.data.followers || []);
                setIsFollowing(res.data.is_following || false);
                setCompanyUsers(res.data.company_users || []);
            }
        } catch (err) {
            console.error('Failed to load chatter stream:', err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (targetModel && targetId) {
            loadStream();
        }
    }, [targetModel, targetId]);

    const handleSendMessage = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!messageText.trim() && selectedFiles.length === 0) return;

        try {
            setIsPosting(true);
            const formData = new FormData();
            formData.append('message', messageText);
            formData.append('type', activeTab || 'note');

            selectedFiles.forEach((file) => {
                formData.append('files[]', file);
            });

            const res = await axios.post(route('chatter.send', { model: targetModel, id: targetId }), formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });

            if (res.data.success) {
                setMessages([res.data.message, ...messages]);
                setMessageText('');
                setSelectedFiles([]);
                setActiveTab(null);
                toast.success(activeTab === 'message' ? t('Message sent') : t('Internal note logged'));
            }
        } catch (err: any) {
            toast.error(err.response?.data?.message || t('Failed to post message'));
        } finally {
            setIsPosting(false);
        }
    };

    const handleScheduleActivity = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!actTitle.trim() || !actDueDate) return;

        try {
            const res = await axios.post(route('chatter.activity', { model: targetModel, id: targetId }), {
                title: actTitle,
                activity_type: actType,
                due_date: actDueDate,
                assigned_to: actAssignee ? parseInt(actAssignee) : undefined,
                notes: actNotes || undefined,
            });

            if (res.data.success) {
                setActivities([res.data.activity, ...activities]);
                setShowActivityModal(false);
                setActTitle('');
                setActNotes('');
                setActDueDate('');
                toast.success(t('Activity scheduled successfully'));
                loadStream(); // refresh messages stream
            }
        } catch (err: any) {
            toast.error(err.response?.data?.message || t('Failed to schedule activity'));
        }
    };

    const handleCompleteActivity = async () => {
        if (!completingActivityId) return;

        try {
            const res = await axios.post(route('chatter.activity.toggle', completingActivityId), {
                feedback: activityFeedback || undefined,
            });

            if (res.data.success) {
                setActivities(activities.map(a => a.id === completingActivityId ? res.data.activity : a));
                setCompletingActivityId(null);
                setActivityFeedback('');
                toast.success(t('Activity marked as completed'));
                loadStream();
            }
        } catch (err: any) {
            toast.error(err.response?.data?.message || t('Failed to update activity'));
        }
    };

    const handleToggleFollow = async () => {
        try {
            const res = await axios.post(route('chatter.follow', { model: targetModel, id: targetId }));
            if (res.data.success) {
                setIsFollowing(res.data.is_following);
                setFollowers(res.data.followers || []);
                toast.success(res.data.is_following ? t('You are now following this record') : t('Unfollowed record'));
            }
        } catch (err) {
            toast.error(t('Failed to update follower status'));
        }
    };

    const pendingActivities = activities.filter(a => a.status === 'pending');
    const todayStr = new Date().toISOString().split('T')[0];

    const getActivityBadge = (act: ChatterActivity) => {
        if (act.status === 'completed') {
            return <Badge variant="outline" className="bg-emerald-50 text-emerald-700 border-emerald-200 text-[10px]">✓ {t('Done')}</Badge>;
        }
        if (act.due_date < todayStr) {
            return <Badge variant="destructive" className="text-[10px] animate-pulse">{t('Overdue')}</Badge>;
        }
        if (act.due_date === todayStr) {
            return <Badge variant="outline" className="bg-amber-50 text-amber-700 border-amber-300 text-[10px] font-semibold">{t('Today')}</Badge>;
        }
        return <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 text-[10px]">{t('Planned')}</Badge>;
    };

    const getActivityIcon = (type: string) => {
        switch (type) {
            case 'call':    return <Phone className="h-3.5 w-3.5 text-blue-500" />;
            case 'email':   return <Mail className="h-3.5 w-3.5 text-indigo-500" />;
            case 'meeting': return <Calendar className="h-3.5 w-3.5 text-emerald-500" />;
            case 'review':  return <FileText className="h-3.5 w-3.5 text-purple-500" />;
            default:        return <CheckCircle2 className="h-3.5 w-3.5 text-amber-500" />;
        }
    };

    const formatInitials = (name: string) => {
        return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
    };

    return (
        <Card className={`overflow-hidden border border-gray-200/80 shadow-sm ${className}`}>
            {/* Odoo Style Top Control Bar */}
            <CardHeader className="p-4 bg-gray-50/70 dark:bg-gray-900/40 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant={activeTab === 'message' ? 'default' : 'outline'}
                        onClick={() => setActiveTab(activeTab === 'message' ? null : 'message')}
                        className="gap-1.5 h-8 text-xs font-semibold"
                    >
                        <MessageSquare className="h-3.5 w-3.5" />
                        {t('Send Message')}
                    </Button>

                    <Button
                        type="button"
                        size="sm"
                        variant={activeTab === 'note' ? 'default' : 'outline'}
                        onClick={() => setActiveTab(activeTab === 'note' ? null : 'note')}
                        className="gap-1.5 h-8 text-xs font-semibold"
                    >
                        <FileText className="h-3.5 w-3.5" />
                        {t('Log Note')}
                    </Button>

                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => setShowActivityModal(true)}
                        className="gap-1.5 h-8 text-xs font-semibold hover:border-indigo-300"
                    >
                        <Calendar className="h-3.5 w-3.5 text-indigo-600" />
                        {t('Schedule Activity')}
                        {pendingActivities.length > 0 && (
                            <span className="ml-1 px-1.5 py-0.2 bg-indigo-100 text-indigo-700 rounded-full text-[10px] font-bold">
                                {pendingActivities.length}
                            </span>
                        )}
                    </Button>
                </div>

                {/* Follower Widget */}
                <div className="flex items-center gap-3">
                    <div className="flex items-center -space-x-1.5 overflow-hidden">
                        {followers.slice(0, 4).map((f) => (
                            <Avatar key={f.id} className="inline-block h-6 w-6 rounded-full ring-2 ring-white">
                                {f.user?.avatar ? <AvatarImage src={f.user.avatar} /> : null}
                                <AvatarFallback className="text-[9px] bg-indigo-100 text-indigo-700">
                                    {formatInitials(f.user?.name || 'U')}
                                </AvatarFallback>
                            </Avatar>
                        ))}
                        {followers.length > 4 && (
                            <span className="text-[10px] font-semibold text-gray-500 pl-2">
                                +{followers.length - 4}
                            </span>
                        )}
                    </div>

                    <Button
                        type="button"
                        size="sm"
                        variant={isFollowing ? 'secondary' : 'outline'}
                        onClick={handleToggleFollow}
                        className="h-8 text-xs gap-1.5 border-gray-200"
                    >
                        {isFollowing ? (
                            <>
                                <UserCheck className="h-3.5 w-3.5 text-emerald-600" />
                                <span>{t('Following')}</span>
                            </>
                        ) : (
                            <>
                                <UserPlus className="h-3.5 w-3.5 text-gray-500" />
                                <span>{t('Follow')}</span>
                            </>
                        )}
                    </Button>
                </div>
            </CardHeader>

            <CardContent className="p-0">
                {/* Active Pending Activities Bar */}
                {pendingActivities.length > 0 && (
                    <div className="p-3 bg-indigo-50/30 border-b border-indigo-100/60 divide-y divide-indigo-100/40">
                        <div className="text-xs font-semibold text-indigo-900 mb-2 flex items-center justify-between">
                            <span className="flex items-center gap-1.5">
                                <Clock className="h-3.5 w-3.5 text-indigo-600" />
                                {t('Planned Activities')} ({pendingActivities.length})
                            </span>
                        </div>
                        <div className="space-y-2 pt-2">
                            {pendingActivities.map((act) => (
                                <div key={act.id} className="flex items-center justify-between text-xs py-1">
                                    <div className="flex items-center gap-2">
                                        {getActivityIcon(act.activity_type)}
                                        <span className="font-semibold text-gray-800">{act.title}</span>
                                        <span className="text-gray-400">({act.assignee?.name || t('Staff')})</span>
                                        {getActivityBadge(act)}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-gray-500 text-[11px]">{act.due_date}</span>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setCompletingActivityId(act.id)}
                                            className="h-6 px-2 text-[10px] text-emerald-700 border-emerald-300 hover:bg-emerald-50"
                                        >
                                            <Check className="h-3 w-3 mr-1" />
                                            {t('Mark Done')}
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Message / Note Composer Box */}
                {activeTab && (
                    <form onSubmit={handleSendMessage} className="p-4 bg-white border-b border-gray-100 space-y-3">
                        <div className="flex items-center justify-between text-xs font-semibold text-gray-600">
                            <span>{activeTab === 'message' ? t('Send Message to Followers') : t('Log Internal Note (Private to Staff)')}</span>
                            <button type="button" onClick={() => setActiveTab(null)} className="text-gray-400 hover:text-gray-600">
                                <X className="h-4 w-4" />
                            </button>
                        </div>

                        <Textarea
                            value={messageText}
                            onChange={(e) => setMessageText(e.target.value)}
                            placeholder={activeTab === 'message' ? t('Type message to notify followers... (@mention available)') : t('Write an internal note for staff review...')}
                            rows={3}
                            className="text-sm bg-gray-50/50"
                        />

                        {/* File preview */}
                        {selectedFiles.length > 0 && (
                            <div className="flex flex-wrap gap-2 pt-1">
                                {selectedFiles.map((f, i) => (
                                    <Badge key={i} variant="secondary" className="gap-1.5 py-1 text-xs">
                                        <Paperclip className="h-3 w-3" />
                                        <span className="truncate max-w-[150px]">{f.name}</span>
                                        <button
                                            type="button"
                                            onClick={() => setSelectedFiles(selectedFiles.filter((_, idx) => idx !== i))}
                                            className="text-gray-400 hover:text-red-500"
                                        >
                                            ×
                                        </button>
                                    </Badge>
                                ))}
                            </div>
                        )}

                        <div className="flex items-center justify-between pt-1">
                            <div>
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    multiple
                                    className="hidden"
                                    onChange={(e) => {
                                        if (e.target.files) {
                                            setSelectedFiles([...selectedFiles, ...Array.from(e.target.files)]);
                                        }
                                    }}
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => fileInputRef.current?.click()}
                                    className="h-8 gap-1 text-xs text-gray-500 hover:text-indigo-600"
                                >
                                    <Paperclip className="h-3.5 w-3.5" />
                                    {t('Attach file')}
                                </Button>
                            </div>

                            <div className="flex gap-2">
                                <Button type="button" variant="outline" size="sm" onClick={() => setActiveTab(null)} className="h-8 text-xs">
                                    {t('Cancel')}
                                </Button>
                                <Button type="submit" disabled={isPosting} size="sm" className="h-8 gap-1 text-xs shadow-sm">
                                    <Send className="h-3.5 w-3.5" />
                                    {isPosting ? t('Posting...') : t('Post')}
                                </Button>
                            </div>
                        </div>
                    </form>
                )}

                {/* Timeline Stream */}
                <div className="p-4 space-y-4">
                    {loading ? (
                        <div className="py-8 text-center text-xs text-gray-400">{t('Loading chatter stream...')}</div>
                    ) : messages.length === 0 ? (
                        <div className="py-8 text-center text-xs text-gray-400">
                            <MessageSquare className="h-6 w-6 mx-auto mb-1.5 opacity-40" />
                            {t('No messages or notes recorded yet. Start the conversation above.')}
                        </div>
                    ) : (
                        messages.map((msg) => (
                            <div key={msg.id} className="flex items-start gap-3 text-sm">
                                <Avatar className="h-7 w-7 rounded-full mt-0.5 shrink-0 ring-1 ring-gray-200">
                                    {msg.user?.avatar ? <AvatarImage src={msg.user.avatar} /> : null}
                                    <AvatarFallback className="text-[10px] bg-gray-100 text-gray-700 font-semibold">
                                        {formatInitials(msg.user?.name || 'S')}
                                    </AvatarFallback>
                                </Avatar>

                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className="font-semibold text-xs text-gray-900">{msg.user?.name || t('System')}</span>

                                        {msg.type === 'note' && (
                                            <Badge variant="outline" className="bg-amber-50 text-amber-700 border-amber-200 text-[10px] py-0 px-1.5">
                                                {t('Internal Note')}
                                            </Badge>
                                        )}
                                        {msg.type === 'diff' && (
                                            <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 text-[10px] py-0 px-1.5">
                                                {t('Tracking')}
                                            </Badge>
                                        )}
                                        {msg.type === 'activity' && (
                                            <Badge variant="outline" className="bg-indigo-50 text-indigo-700 border-indigo-200 text-[10px] py-0 px-1.5">
                                                {t('Activity')}
                                            </Badge>
                                        )}

                                        <span className="text-[10px] text-gray-400">
                                            {new Date(msg.created_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' })}
                                        </span>
                                    </div>

                                    <div className="mt-1 text-xs text-gray-700 whitespace-pre-wrap leading-relaxed">
                                        {msg.message}
                                    </div>

                                    {/* Attachments */}
                                    {msg.attachments && msg.attachments.length > 0 && (
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {msg.attachments.map((att) => (
                                                <a
                                                    key={att.id}
                                                    href={att.file_path}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex items-center gap-1.5 px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded text-[11px] text-gray-700 transition-colors border border-gray-200"
                                                >
                                                    <FileIcon className="h-3 w-3 text-indigo-500" />
                                                    <span className="truncate max-w-[140px]">{att.file_name}</span>
                                                    <Download className="h-2.5 w-2.5 text-gray-400 ml-0.5" />
                                                </a>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </CardContent>

            {/* Schedule Activity Dialog */}
            <Dialog open={showActivityModal} onOpenChange={setShowActivityModal}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-base font-semibold">{t('Schedule an Activity')}</DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            {t('Set an actionable task or follow-up for this document.')}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleScheduleActivity} className="space-y-4 py-2">
                        <div className="space-y-1.5">
                            <Label className="text-xs">{t('Activity Type')}</Label>
                            <Select value={actType} onValueChange={(val: any) => setActType(val)}>
                                <SelectTrigger className="h-9 text-xs">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="todo">📋 {t('To-Do Task')}</SelectItem>
                                    <SelectItem value="call">📞 {t('Phone Call')}</SelectItem>
                                    <SelectItem value="email">✉️ {t('Send Email')}</SelectItem>
                                    <SelectItem value="meeting">📅 {t('Meeting')}</SelectItem>
                                    <SelectItem value="review">🔍 {t('Review & Inspection')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1.5">
                            <Label className="text-xs">{t('Summary / Title')}</Label>
                            <Input
                                value={actTitle}
                                onChange={(e) => setActTitle(e.target.value)}
                                placeholder={t('e.g., Call customer for bank deposit receipt')}
                                className="h-9 text-xs"
                                required
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label className="text-xs">{t('Due Date')}</Label>
                                <Input
                                    type="date"
                                    value={actDueDate}
                                    onChange={(e) => setActDueDate(e.target.value)}
                                    className="h-9 text-xs"
                                    required
                                />
                            </div>

                            <div className="space-y-1.5">
                                <Label className="text-xs">{t('Assigned To')}</Label>
                                <Select value={actAssignee} onValueChange={setActAssignee}>
                                    <SelectTrigger className="h-9 text-xs">
                                        <SelectValue placeholder={t('Assign staff...')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {companyUsers.map((u) => (
                                            <SelectItem key={u.id} value={String(u.id)}>
                                                {u.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label className="text-xs">{t('Notes (Optional)')}</Label>
                            <Textarea
                                value={actNotes}
                                onChange={(e) => setActNotes(e.target.value)}
                                placeholder={t('Add background or special instructions...')}
                                rows={2}
                                className="text-xs"
                            />
                        </div>

                        <DialogFooter className="gap-2 pt-2">
                            <Button type="button" variant="outline" size="sm" onClick={() => setShowActivityModal(false)}>
                                {t('Cancel')}
                            </Button>
                            <Button type="submit" size="sm">
                                {t('Schedule')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Complete Activity Dialog */}
            <Dialog open={completingActivityId !== null} onOpenChange={(open) => !open && setCompletingActivityId(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-base font-semibold">{t('Mark Activity as Done')}</DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            {t('Optionally add completion feedback or outcome details.')}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3 py-2">
                        <Label className="text-xs">{t('Feedback / Outcome Note')}</Label>
                        <Textarea
                            value={activityFeedback}
                            onChange={(e) => setActivityFeedback(e.target.value)}
                            placeholder={t('e.g., Customer confirmed payment via Telebirr receipt #TRX8921...')}
                            rows={3}
                            className="text-xs"
                        />
                    </div>

                    <DialogFooter className="gap-2 pt-2">
                        <Button type="button" variant="outline" size="sm" onClick={() => setCompletingActivityId(null)}>
                            {t('Cancel')}
                        </Button>
                        <Button type="button" size="sm" onClick={handleCompleteActivity} className="bg-emerald-600 hover:bg-emerald-700">
                            {t('Done & Log')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Card>
    );
}
