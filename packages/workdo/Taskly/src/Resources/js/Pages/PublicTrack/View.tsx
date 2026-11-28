import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { RichTextViewer } from '@/components/ui/rich-text-viewer';
import { stripHtml } from '@/utils/helpers';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { CheckCircle2, Clock, Calendar, CheckSquare, FileText, ArrowRight, ShieldCheck, Download, AlertCircle } from 'lucide-react';

interface Milestone {
    id: number;
    title: string;
    cost?: number;
    start_date?: string;
    end_date?: string;
    summary?: string;
    status: string;
    progress: number;
    approval_status?: 'pending' | 'approved' | 'rejected';
}

interface TaskItem {
    id: number;
    title: string;
    priority: string;
    progress: number;
    start_date?: string;
    due_date?: string;
    stage?: { id: number; name: string };
}

interface PublicTrackProps {
    project: {
        id: number;
        name: string;
        description?: string;
        project_type: string;
        currency_code: string;
        start_date?: string;
        end_date?: string;
        status: string;
        share_token: string;
    };
    milestones: Milestone[];
    tasks: TaskItem[];
    files: Array<{ id: number; file_name: string; file_path: string }>;
    metrics: {
        progress_percent: number;
        total_tasks: number;
        completed_tasks: number;
    };
}

export default function PublicTrackView({ project, milestones, tasks, files, metrics }: PublicTrackProps) {
    const [selectedMilestone, setSelectedMilestone] = useState<Milestone | null>(null);
    const { data, setData, post, processing } = useForm({
        client_note: ''
    });

    const handleApprove = (milestoneId: number) => {
        post(route('project.public.approve-milestone', { token: project.share_token, milestone: milestoneId }), {
            onSuccess: () => {
                setSelectedMilestone(null);
            }
        });
    };

    return (
        <div className="min-h-screen bg-slate-50 dark:bg-slate-950 font-sans antialiased text-slate-800 dark:text-slate-100">
            <Head title={`${project.name} - Live Project Portal`} />

            {/* Top Navigation Bar */}
            <header className="border-b bg-white dark:bg-slate-900 shadow-sm sticky top-0 z-50">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="h-9 w-9 rounded-xl bg-gradient-to-br from-emerald-600 to-teal-800 flex items-center justify-center text-white font-bold shadow-md shadow-emerald-500/20">
                            G
                        </div>
                        <div>
                            <div className="font-bold text-sm leading-tight text-slate-900 dark:text-white flex items-center gap-1.5">
                                GadaaCloud Project Portal
                                <Badge variant="outline" className="text-[10px] bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800">
                                    <ShieldCheck className="w-2.5 h-2.5 mr-1" /> Verified Client View
                                </Badge>
                            </div>
                            <p className="text-[11px] text-slate-500">Live Delivery &amp; Milestone Sign-off</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge className="bg-slate-900 text-white dark:bg-white dark:text-slate-900 capitalize text-xs">
                            {project.status}
                        </Badge>
                    </div>
                </div>
            </header>

            <main className="max-w-6xl mx-auto px-4 sm:px-6 py-8 space-y-8">
                {/* Hero Summary */}
                <div className="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 border shadow-sm">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <div className="space-y-2 max-w-2xl">
                            <div className="flex items-center gap-2">
                                <Badge className="bg-emerald-600 text-white text-xs uppercase tracking-wider font-mono">
                                    {project.project_type}
                                </Badge>
                                {project.start_date && (
                                    <span className="text-xs text-slate-500 flex items-center gap-1">
                                        <Calendar className="w-3.5 h-3.5" />
                                        {project.start_date} {project.end_date ? `to ${project.end_date}` : ''}
                                    </span>
                                )}
                            </div>
                            <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                                {project.name}
                            </h1>
                            {project.description && (
                                <RichTextViewer
                                    content={project.description}
                                    className="text-slate-600 dark:text-slate-300"
                                />
                            )}
                        </div>

                        {/* Progress Gauge */}
                        <div className="bg-slate-50 dark:bg-slate-800/60 p-5 rounded-2xl border min-w-[240px] space-y-3">
                            <div className="flex items-center justify-between text-xs font-semibold">
                                <span>Overall Project Progress</span>
                                <span className="text-emerald-600 dark:text-emerald-400 text-sm font-bold font-mono">
                                    {metrics.progress_percent}%
                                </span>
                            </div>
                            <div className="w-full bg-slate-200 dark:bg-slate-700 h-2.5 rounded-full overflow-hidden">
                                <div
                                    className="bg-emerald-600 h-full rounded-full transition-all duration-500"
                                    style={{ width: `${metrics.progress_percent}%` }}
                                />
                            </div>
                            <div className="flex justify-between text-[11px] text-slate-500 font-mono">
                                <span>{metrics.completed_tasks} Completed</span>
                                <span>{metrics.total_tasks} Total Tasks</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Key Deliverables & Milestones */}
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-lg font-bold text-slate-900 dark:text-white">Project Milestones &amp; Sign-offs</h2>
                            <p className="text-xs text-slate-500">Track phase deliverables and approve completion directly</p>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {milestones.length === 0 ? (
                            <div className="col-span-2 text-center p-8 bg-white dark:bg-slate-900 rounded-2xl border text-sm text-slate-500">
                                No milestones published for this project yet.
                            </div>
                        ) : (
                            milestones.map((milestone) => (
                                <Card key={milestone.id} className="relative overflow-hidden border shadow-sm hover:shadow-md transition-shadow">
                                    <div
                                        className={`absolute top-0 left-0 right-0 h-1 ${
                                            milestone.status === 'Complete' || milestone.approval_status === 'approved'
                                                ? 'bg-emerald-500'
                                                : milestone.progress > 0
                                                ? 'bg-blue-500'
                                                : 'bg-slate-300'
                                        }`}
                                    />
                                    <CardHeader className="pb-3">
                                        <div className="flex items-start justify-between gap-2">
                                            <CardTitle className="text-base font-bold text-slate-900 dark:text-white leading-snug">
                                                {milestone.title}
                                            </CardTitle>
                                            <Badge
                                                variant="outline"
                                                className={`text-xs capitalize ${
                                                    milestone.approval_status === 'approved'
                                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300'
                                                        : milestone.status === 'Complete'
                                                        ? 'bg-blue-50 text-blue-700 border-blue-300 dark:bg-blue-950 dark:text-blue-300'
                                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800'
                                                }`}
                                            >
                                                {milestone.approval_status === 'approved' ? 'Client Approved' : milestone.status}
                                            </Badge>
                                        </div>
                                        {milestone.summary && stripHtml(milestone.summary) && (
                                            <CardDescription className="text-xs text-slate-500 line-clamp-2">
                                                {stripHtml(milestone.summary)}
                                            </CardDescription>
                                        )}
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="space-y-1.5">
                                            <div className="flex justify-between text-[11px] text-slate-500 font-mono">
                                                <span>Phase Progress</span>
                                                <span>{milestone.progress}%</span>
                                            </div>
                                            <div className="w-full bg-slate-100 dark:bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                                <div
                                                    className="bg-emerald-500 h-full rounded-full transition-all"
                                                    style={{ width: `${milestone.progress}%` }}
                                                />
                                            </div>
                                        </div>

                                        <div className="pt-2 flex items-center justify-between border-t text-xs">
                                            <div className="text-slate-500 text-[11px]">
                                                {milestone.end_date ? `Target: ${milestone.end_date}` : 'Ongoing'}
                                            </div>

                                            {milestone.approval_status === 'approved' ? (
                                                <span className="flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-semibold text-xs">
                                                    <CheckCircle2 className="w-4 h-4" /> Signed Off
                                                </span>
                                            ) : (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => setSelectedMilestone(milestone)}
                                                    className="border-emerald-600 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-500 dark:text-emerald-300 h-8 text-xs gap-1"
                                                >
                                                    <CheckSquare className="w-3.5 h-3.5" /> Sign Off Milestone
                                                </Button>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            ))
                        )}
                    </div>
                </div>

                {/* Milestone Sign-off Modal */}
                {selectedMilestone && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in">
                        <div className="bg-white dark:bg-slate-900 border rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                            <div className="flex items-center gap-3">
                                <div className="h-10 w-10 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 flex items-center justify-center text-emerald-600">
                                    <ShieldCheck className="w-5 h-5" />
                                </div>
                                <div>
                                    <h3 className="font-bold text-base text-slate-900 dark:text-white">Sign Off Milestone</h3>
                                    <p className="text-xs text-slate-500">{selectedMilestone.title}</p>
                                </div>
                            </div>

                            <p className="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                                By signing off on this milestone, you verify that the agreed phase deliverables have been inspected, tested, and accepted.
                            </p>

                            <div className="space-y-1">
                                <label className="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Approval Note or Sign-off Reference (Optional)
                                </label>
                                <textarea
                                    value={data.client_note}
                                    onChange={(e) => setData('client_note', e.target.value)}
                                    placeholder="e.g. Deliverable accepted. Proceed to next phase."
                                    rows={3}
                                    className="w-full text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 p-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
                                />
                            </div>

                            <div className="flex items-center justify-end gap-2 pt-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setSelectedMilestone(null)}
                                    disabled={processing}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    size="sm"
                                    onClick={() => handleApprove(selectedMilestone.id)}
                                    disabled={processing}
                                    className="bg-emerald-600 hover:bg-emerald-700 text-white gap-1"
                                >
                                    <ShieldCheck className="w-3.5 h-3.5" />
                                    {processing ? 'Signing off...' : 'Confirm Sign-off'}
                                </Button>
                            </div>
                        </div>
                    </div>
                )}

                {/* Deliverables & Uploaded Files */}
                {files && files.length > 0 && (
                    <div className="space-y-4">
                        <h2 className="text-lg font-bold text-slate-900 dark:text-white">Shared Project Deliverables</h2>
                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                            {files.map((file) => (
                                <a
                                    key={file.id}
                                    href={`/${file.file_path}`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex items-center gap-3 p-3 bg-white dark:bg-slate-900 rounded-xl border hover:border-emerald-500/50 hover:shadow-sm transition-all group"
                                >
                                    <div className="h-9 w-9 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-600 dark:text-slate-300 group-hover:text-emerald-600">
                                        <FileText className="w-4 h-4" />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="text-xs font-semibold text-slate-900 dark:text-white truncate">
                                            {file.file_name}
                                        </p>
                                        <span className="text-[10px] text-slate-500 flex items-center gap-1">
                                            <Download className="w-3 h-3" /> Click to view
                                        </span>
                                    </div>
                                </a>
                            ))}
                        </div>
                    </div>
                )}
            </main>

            <footer className="border-t bg-white dark:bg-slate-900 py-6 text-center text-xs text-slate-500">
                <p>Powered by GadaaCloud Enterprise Project Management System</p>
            </footer>
        </div>
    );
}
