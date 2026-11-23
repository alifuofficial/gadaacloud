import React from 'react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { ArrowRight, Link2, Calendar, Clock, AlertCircle, CheckCircle2, Flag } from 'lucide-react';
import { formatDate } from '@/utils/helpers';

interface TaskWithDeps {
    id: number;
    title: string;
    priority: string;
    start_date?: string;
    due_date?: string;
    estimated_hours: number;
    progress: number;
    predecessor_id?: number;
    predecessor?: { id: number; title: string };
    dependency_type: string;
    is_critical: boolean;
    stage?: { id: number; name: string; color?: string };
}

interface GanttTabProps {
    tasks: TaskWithDeps[];
    onEditTask?: (task: TaskWithDeps) => void;
}

export function GanttDependenciesTab({ tasks }: GanttTabProps) {
    return (
        <Card className="border shadow-sm">
            <CardHeader className="p-5 border-b bg-slate-50/50 dark:bg-slate-900/50">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <CardTitle className="text-lg font-bold flex items-center gap-2">
                            <Link2 className="w-5 h-5 text-emerald-600" />
                            Gantt Schedule &amp; Task Dependencies
                        </CardTitle>
                        <CardDescription className="text-xs text-slate-500">
                            Critical Path Method (CPM), predecessor/successor relationships, and execution progress
                        </CardDescription>
                    </div>
                    <div className="flex items-center gap-2 text-xs">
                        <span className="flex items-center gap-1.5 font-medium">
                            <span className="h-2.5 w-2.5 rounded-full bg-red-500" /> Critical Path
                        </span>
                        <span className="flex items-center gap-1.5 font-medium text-slate-500">
                            <span className="h-2.5 w-2.5 rounded-full bg-emerald-500" /> Standard Task
                        </span>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                {tasks.length === 0 ? (
                    <div className="p-8 text-center text-sm text-slate-500">
                        No tasks created for this project yet. Create tasks from the Tasks tab or Kanban view to populate the Gantt schedule.
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs text-left">
                            <thead className="bg-slate-50 dark:bg-slate-800/60 border-b text-slate-500 font-semibold uppercase text-[10px] tracking-wider">
                                <tr>
                                    <th className="py-3 px-4">Task Name</th>
                                    <th className="py-3 px-4">Stage</th>
                                    <th className="py-3 px-4">Priority</th>
                                    <th className="py-3 px-4">Predecessor (Dependency)</th>
                                    <th className="py-3 px-4">Schedule Window</th>
                                    <th className="py-3 px-4 min-w-[160px]">Progress (%)</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {tasks.map((task) => (
                                    <tr
                                        key={task.id}
                                        className={`hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors ${
                                            task.is_critical ? 'bg-red-50/20' : ''
                                        }`}
                                    >
                                        <td className="py-3 px-4 font-medium text-slate-900 dark:text-white">
                                            <div className="flex items-center gap-2">
                                                {task.is_critical ? (
                                                    <Flag className="w-3.5 h-3.5 text-red-500 shrink-0" />
                                                ) : (
                                                    <span className="w-1.5 h-1.5 rounded-full bg-slate-300 dark:bg-slate-600 shrink-0" />
                                                )}
                                                <span className="truncate max-w-[220px]">{task.title}</span>
                                            </div>
                                        </td>
                                        <td className="py-3 px-4">
                                            <Badge
                                                variant="outline"
                                                className="text-[10px] px-2 py-0.5"
                                                style={{ borderColor: task.stage?.color || '#cbd5e1' }}
                                            >
                                                {task.stage?.name || 'Todo'}
                                            </Badge>
                                        </td>
                                        <td className="py-3 px-4">
                                            <span
                                                className={`px-2 py-0.5 rounded-full text-[10px] font-semibold ${
                                                    task.priority === 'High'
                                                        ? 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300'
                                                        : task.priority === 'Medium'
                                                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                                                        : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                                }`}
                                            >
                                                {task.priority}
                                            </span>
                                        </td>
                                        <td className="py-3 px-4">
                                            {task.predecessor ? (
                                                <div className="flex items-center gap-1.5 text-slate-600 dark:text-slate-300">
                                                    <Link2 className="w-3 h-3 text-slate-400" />
                                                    <span className="truncate max-w-[140px]">{task.predecessor.title}</span>
                                                    <Badge variant="secondary" className="text-[9px] uppercase px-1 py-0 font-mono">
                                                        {task.dependency_type === 'finish_to_start'
                                                            ? 'FS'
                                                            : task.dependency_type === 'start_to_start'
                                                            ? 'SS'
                                                            : 'FF'}
                                                    </Badge>
                                                </div>
                                            ) : (
                                                <span className="text-slate-400 text-[11px]">- (Root Task)</span>
                                            )}
                                        </td>
                                        <td className="py-3 px-4 text-slate-500 font-mono text-[11px]">
                                            {task.start_date || task.due_date ? (
                                                <div className="flex items-center gap-1">
                                                    <Calendar className="w-3 h-3 text-slate-400" />
                                                    {task.start_date ? formatDate(task.start_date) : 'Start'}
                                                    <ArrowRight className="w-2.5 h-2.5 text-slate-300" />
                                                    {task.due_date ? formatDate(task.due_date) : 'End'}
                                                </div>
                                            ) : (
                                                '-'
                                            )}
                                        </td>
                                        <td className="py-3 px-4">
                                            <div className="flex items-center gap-2">
                                                <div className="flex-1 bg-slate-100 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                                                    <div
                                                        className={`h-full rounded-full transition-all duration-300 ${
                                                            task.is_critical
                                                                ? 'bg-red-500'
                                                                : task.progress >= 100
                                                                ? 'bg-emerald-500'
                                                                : 'bg-blue-600'
                                                        }`}
                                                        style={{ width: `${task.progress || 0}%` }}
                                                    />
                                                </div>
                                                <span className="text-[11px] font-mono text-slate-600 dark:text-slate-400 min-w-[32px] text-right">
                                                    {task.progress || 0}%
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default GanttDependenciesTab;
