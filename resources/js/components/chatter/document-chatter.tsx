import React, { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { MessageSquare, Paperclip, Calendar, CheckSquare, Send, AtSign, Clock, FileText, UserCheck, RefreshCw, Plus } from 'lucide-react';

interface ChatterProps {
    model: string;
    recordId: number | string;
    title?: string;
}

interface Message {
    id: number;
    message: string;
    type: 'note' | 'message' | 'activity' | 'diff';
    created_at: string;
    user?: { id: number; name: string; email: string; avatar?: string };
    attachments?: Array<{ id: number; file_name: string; file_path: string; file_size: number }>;
}

interface Activity {
    id: number;
    title: string;
    activity_type: string;
    due_date: string;
    status: 'pending' | 'completed';
    notes?: string;
    assignee?: { id: number; name: string };
}

export default function DocumentChatter({ model, recordId, title }: ChatterProps) {
    const { t } = useTranslation();
    const [activeTab, setActiveTab] = useState<'note' | 'message' | 'activity'>('note');

    const [messages, setMessages] = useState<Message[]>([]);
    const [activities, setActivities] = useState<Activity[]>([]);
    const [companyUsers, setCompanyUsers] = useState<Array<{ id: number; name: string; email: string }>>([]);

    const [textInput, setTextInput] = useState('');
    const [selectedFiles, setSelectedFiles] = useState<File[]>([]);
    const [sending, setSending] = useState(false);
    const [loading, setLoading] = useState(true);

    // Schedule Activity State
    const [activityTitle, setActivityTitle] = useState('');
    const [activityType, setActivityType] = useState('todo');
    const [activityDueDate, setActivityDueDate] = useState('');
    const [activityAssignee, setActivityAssignee] = useState('');
    const [activityNotes, setActivityNotes] = useState('');

    const fileInputRef = useRef<HTMLInputElement>(null);

    const fetchStream = async () => {
        try {
            let url = `/chatter/${model}/${recordId}`;
            try {
                if (typeof route === 'function') {
                    url = route('chatter.stream', { model, id: recordId });
                }
            } catch (e) {}

            const res = await axios.get(url);
            setMessages(res.data.messages || []);
            setActivities(res.data.activities || []);
            setCompanyUsers(res.data.companyUsers || []);
        } catch (e) {
            console.error("Chatter stream error:", e);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchStream();
    }, [model, recordId]);

    const handleSendMessage = async () => {
        if (!textInput.trim() && selectedFiles.length === 0) return;
        setSending(true);

        try {
            let sendUrl = `/chatter/${model}/${recordId}/send`;
            try {
                if (typeof route === 'function') {
                    sendUrl = route('chatter.send', { model, id: recordId });
                }
            } catch (e) {}

            const formData = new FormData();
            formData.append('message', textInput);
            formData.append('type', activeTab);
            selectedFiles.forEach((file) => formData.append('files[]', file));

            const res = await axios.post(sendUrl, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            if (res.data?.message) {
                setMessages((prev) => [res.data.message, ...prev]);
            }
            setTextInput('');
            setSelectedFiles([]);
        } catch (e) {
            console.error("Send chatter message error:", e);
        } finally {
            setSending(false);
        }
    };

    const handleScheduleActivity = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!activityTitle.trim() || !activityDueDate) return;
        setSending(true);

        try {
            let actUrl = `/chatter/${model}/${recordId}/activity`;
            try {
                if (typeof route === 'function') {
                    actUrl = route('chatter.activity', { model, id: recordId });
                }
            } catch (e) {}

            const res = await axios.post(actUrl, {
                title: activityTitle,
                activity_type: activityType,
                due_date: activityDueDate,
                assigned_to: activityAssignee ? parseInt(activityAssignee) : null,
                notes: activityNotes,
            });

            if (res.data?.activity) {
                setActivities((prev) => [...prev, res.data.activity]);
            }
            setActivityTitle('');
            setActivityDueDate('');
            setActivityNotes('');
            setActiveTab('note');
            fetchStream();
        } catch (e) {
            console.error("Schedule activity error:", e);
        } finally {
            setSending(false);
        }
    };

    const handleToggleActivity = async (actId: number) => {
        try {
            let toggleUrl = `/chatter/activity/${actId}/toggle`;
            try {
                if (typeof route === 'function') {
                    toggleUrl = route('chatter.activity.toggle', { id: actId });
                }
            } catch (e) {}

            const res = await axios.post(toggleUrl);
            setActivities((prev) =>
                prev.map((act) => (act.id === actId ? { ...act, status: res.data.activity.status } : act))
            );
        } catch (e) {}
    };

    return (
        <div className="bg-white border rounded-2xl shadow-sm overflow-hidden mt-6">
            {/* Header */}
            <div className="p-4 bg-slate-900 text-white flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <MessageSquare className="w-5 h-5 text-purple-400" />
                    <span className="font-bold text-sm tracking-wide">
                        {title || t('Document Chatter & Activity Stream')}
                    </span>
                </div>
                <Badge className="bg-purple-500/20 text-purple-300 border-purple-400/30 text-xs">
                    {messages.length} Notes / {activities.filter((a) => a.status === 'pending').length} Activities
                </Badge>
            </div>

            {/* Input Composer Tabs */}
            <div className="border-b bg-gray-50/80 p-2 flex items-center gap-2">
                <button
                    onClick={() => setActiveTab('note')}
                    className={`px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all ${
                        activeTab === 'note'
                            ? 'bg-amber-100 text-amber-900 border border-amber-300 shadow-2xs'
                            : 'text-gray-600 hover:bg-gray-100'
                    }`}
                >
                    <FileText className="w-3.5 h-3.5 text-amber-600" />
                    {t('Log Internal Note')}
                </button>
                <button
                    onClick={() => setActiveTab('message')}
                    className={`px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all ${
                        activeTab === 'message'
                            ? 'bg-blue-100 text-blue-900 border border-blue-300 shadow-2xs'
                            : 'text-gray-600 hover:bg-gray-100'
                    }`}
                >
                    <Send className="w-3.5 h-3.5 text-blue-600" />
                    {t('Send Message')}
                </button>
                <button
                    onClick={() => setActiveTab('activity')}
                    className={`px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all ${
                        activeTab === 'activity'
                            ? 'bg-purple-100 text-purple-900 border border-purple-300 shadow-2xs'
                            : 'text-gray-600 hover:bg-gray-100'
                    }`}
                >
                    <Calendar className="w-3.5 h-3.5 text-purple-600" />
                    {t('Schedule Activity')}
                </button>
            </div>

            {/* Input Composer Box */}
            <div className="p-4 bg-white border-b">
                {activeTab !== 'activity' ? (
                    <div className="space-y-3">
                        <Textarea
                            value={textInput}
                            onChange={(e) => setTextInput(e.target.value)}
                            placeholder={
                                activeTab === 'note'
                                    ? t('Type an internal note... (e.g. "@Abebe please verify VAT tax rate")')
                                    : t('Send a message to team & client...')
                            }
                            rows={3}
                            className={`text-xs font-sans border transition-all ${
                                activeTab === 'note' ? 'bg-amber-50/30 border-amber-200' : 'bg-blue-50/30 border-blue-200'
                            }`}
                        />

                        {/* File Attachment Previews */}
                        {selectedFiles.length > 0 && (
                            <div className="flex flex-wrap gap-2 pt-1">
                                {selectedFiles.map((file, idx) => (
                                    <Badge key={idx} variant="outline" className="text-[10px] bg-slate-50 border-slate-300">
                                        <Paperclip className="w-3 h-3 mr-1 text-slate-500" />
                                        {file.name}
                                    </Badge>
                                ))}
                            </div>
                        )}

                        <div className="flex items-center justify-between pt-1">
                            <div className="flex items-center gap-2">
                                <input
                                    type="file"
                                    multiple
                                    ref={fileInputRef}
                                    onChange={(e) => {
                                        if (e.target.files) setSelectedFiles(Array.from(e.target.files));
                                    }}
                                    className="hidden"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => fileInputRef.current?.click()}
                                    className="h-8 text-xs text-gray-600"
                                >
                                    <Paperclip className="w-3.5 h-3.5 mr-1" />
                                    {t('Attach File')}
                                </Button>
                            </div>

                            <Button
                                onClick={handleSendMessage}
                                disabled={sending || (!textInput.trim() && selectedFiles.length === 0)}
                                size="sm"
                                className={activeTab === 'note' ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-blue-600 hover:bg-blue-700 text-white'}
                            >
                                {sending ? <RefreshCw className="w-3.5 h-3.5 animate-spin mr-1.5" /> : <Send className="w-3.5 h-3.5 mr-1.5" />}
                                {activeTab === 'note' ? t('Log Note') : t('Send Message')}
                            </Button>
                        </div>
                    </div>
                ) : (
                    /* Schedule Activity Form */
                    <form onSubmit={handleScheduleActivity} className="space-y-3">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label className="text-[11px] font-semibold text-gray-600 mb-1 block">{t('Activity Title')}</label>
                                <Input
                                    value={activityTitle}
                                    onChange={(e) => setActivityTitle(e.target.value)}
                                    placeholder={t('e.g. Call Client about VAT approval')}
                                    className="h-8 text-xs"
                                    required
                                />
                            </div>

                            <div>
                                <label className="text-[11px] font-semibold text-gray-600 mb-1 block">{t('Activity Type')}</label>
                                <select
                                    value={activityType}
                                    onChange={(e) => setActivityType(e.target.value)}
                                    className="w-full h-8 border rounded-md text-xs px-2 bg-white"
                                >
                                    <option value="todo">{t('To-Do Task')}</option>
                                    <option value="call">{t('Phone Call')}</option>
                                    <option value="meeting">{t('Meeting')}</option>
                                    <option value="email">{t('Send Email')}</option>
                                </select>
                            </div>

                            <div>
                                <label className="text-[11px] font-semibold text-gray-600 mb-1 block">{t('Due Date')}</label>
                                <Input
                                    type="date"
                                    value={activityDueDate}
                                    onChange={(e) => setActivityDueDate(e.target.value)}
                                    className="h-8 text-xs"
                                    required
                                />
                            </div>
                        </div>

                        <div>
                            <label className="text-[11px] font-semibold text-gray-600 mb-1 block">{t('Assign To Staff')}</label>
                            <select
                                value={activityAssignee}
                                onChange={(e) => setActivityAssignee(e.target.value)}
                                className="w-full h-8 border rounded-md text-xs px-2 bg-white"
                            >
                                <option value="">{t('Assign to myself')}</option>
                                {companyUsers.map((u) => (
                                    <option key={u.id} value={u.id}>{u.name} ({u.email})</option>
                                ))}
                            </select>
                        </div>

                        <div className="flex justify-end pt-1">
                            <Button type="submit" disabled={sending} size="sm" className="bg-purple-700 hover:bg-purple-800 text-white">
                                <Plus className="w-3.5 h-3.5 mr-1" />
                                {t('Schedule Activity')}
                            </Button>
                        </div>
                    </form>
                )}
            </div>

            {/* Scheduled Activities Checklist */}
            {activities.length > 0 && (
                <div className="p-4 bg-purple-50/40 border-b space-y-2">
                    <div className="text-xs font-bold text-purple-950 flex items-center gap-1.5">
                        <CheckSquare className="w-4 h-4 text-purple-600" />
                        {t('Pending Planned Activities')} ({activities.filter((a) => a.status === 'pending').length})
                    </div>
                    <div className="space-y-1.5">
                        {activities.map((act) => (
                            <div
                                key={act.id}
                                className={`flex items-center justify-between p-2.5 rounded-xl border text-xs transition-all ${
                                    act.status === 'completed' ? 'bg-gray-100 border-gray-200 opacity-60 line-through' : 'bg-white border-purple-200 shadow-2xs'
                                }`}
                            >
                                <div className="flex items-center gap-2.5">
                                    <input
                                        type="checkbox"
                                        checked={act.status === 'completed'}
                                        onChange={() => handleToggleActivity(act.id)}
                                        className="rounded text-purple-600 focus:ring-purple-500 h-4 w-4"
                                    />
                                    <div>
                                        <span className="font-semibold text-slate-900">{act.title}</span>
                                        <span className="ml-2 text-[10px] text-purple-700 bg-purple-100 px-1.5 py-0.2 rounded-full uppercase">
                                            {act.activity_type}
                                        </span>
                                        <div className="text-[10px] text-gray-500 font-mono flex items-center gap-1">
                                            <Clock className="w-3 h-3 text-gray-400" />
                                            Due: {act.due_date} {act.assignee && `| Assigned to: ${act.assignee.name}`}
                                        </div>
                                    </div>
                                </div>
                                <Badge className={act.status === 'completed' ? 'bg-gray-200 text-gray-700' : 'bg-amber-100 text-amber-800'}>
                                    {act.status}
                                </Badge>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Timeline Stream History */}
            <div className="p-4 space-y-4 max-h-[450px] overflow-y-auto">
                {loading ? (
                    <div className="text-center py-6 text-xs text-gray-500 flex items-center justify-center gap-2">
                        <RefreshCw className="w-4 h-4 animate-spin text-purple-600" />
                        {t('Loading activity stream...')}
                    </div>
                ) : messages.length === 0 ? (
                    <div className="text-center py-6 text-xs text-gray-400 font-sans">
                        {t('No notes or activity recorded yet. Start by logging an internal note above.')}
                    </div>
                ) : (
                    messages.map((msg) => (
                        <div key={msg.id} className="flex gap-3 text-xs">
                            <div className="w-8 h-8 rounded-full bg-slate-800 text-white font-bold flex items-center justify-center text-xs shrink-0 shadow-xs">
                                {msg.user?.name ? msg.user.name.charAt(0).toUpperCase() : 'U'}
                            </div>
                            <div className="flex-1 space-y-1 bg-slate-50 border border-slate-200/80 p-3 rounded-2xl rounded-tl-none">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <span className="font-bold text-slate-900">{msg.user?.name || 'User'}</span>
                                        <Badge
                                            className={`text-[9px] px-1.5 py-0.2 ${
                                                msg.type === 'note'
                                                    ? 'bg-amber-100 text-amber-800 border-amber-200'
                                                    : msg.type === 'activity'
                                                    ? 'bg-purple-100 text-purple-800 border-purple-200'
                                                    : 'bg-blue-100 text-blue-800 border-blue-200'
                                            }`}
                                        >
                                            {msg.type === 'note' ? t('Internal Note') : msg.type === 'activity' ? t('Activity Log') : t('Message')}
                                        </Badge>
                                    </div>
                                    <span className="text-[10px] text-gray-400 font-mono">
                                        {new Date(msg.created_at).toLocaleString()}
                                    </span>
                                </div>

                                <div className="text-slate-800 whitespace-pre-wrap leading-relaxed">
                                    {msg.message}
                                </div>

                                {msg.attachments && msg.attachments.length > 0 && (
                                    <div className="flex flex-wrap gap-2 pt-2 border-t border-slate-200">
                                        {msg.attachments.map((att) => (
                                            <a
                                                key={att.id}
                                                href={att.file_path}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="flex items-center gap-1.5 px-2.5 py-1 bg-white border border-slate-300 rounded-lg text-[10px] text-blue-600 hover:underline shadow-2xs"
                                            >
                                                <Paperclip className="w-3 h-3 text-slate-400" />
                                                {att.file_name}
                                            </a>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}
