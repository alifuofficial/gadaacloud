<?php

namespace Workdo\Taskly\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\User;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Workdo\Taskly\Events\CreateProject;
use Workdo\Taskly\Events\CreateProjectMilestone;
use Workdo\Taskly\Events\DestroyProject;
use Workdo\Taskly\Events\DestroyProjectMilestone;
use Workdo\Taskly\Events\ProjectDeleteClient;
use Workdo\Taskly\Events\ProjectDeleteShareToClient;
use Workdo\Taskly\Events\ProjectInviteMember;
use Workdo\Taskly\Events\ProjectShareToClient;
use Workdo\Taskly\Events\UpdateProject;
use Workdo\Taskly\Events\UpdateProjectMilestone;
use Workdo\Taskly\Models\Project;
use Workdo\Taskly\Http\Requests\UpdateMilestoneRequest;
use Workdo\Taskly\Http\Requests\StoreMilestoneRequest;
use Workdo\Taskly\Http\Requests\StoreProjectRequest;
use Workdo\Taskly\Http\Requests\UpdateProjectRequest;
use Workdo\Taskly\Models\ActivityLog;
use Workdo\Taskly\Models\ProjectMilestone;
use Workdo\Taskly\Models\ProjectTask;
use Workdo\Taskly\Models\ProjectBug;
use Workdo\Taskly\Models\ProjectClient;
use Workdo\Taskly\Models\ProjectUser;
use Workdo\Taskly\Models\ProjectFile;
use Workdo\Taskly\Traits\TasklyControllerHelpers;

class ProjectController extends Controller
{
    use TasklyControllerHelpers;
    public function index()
    {
        if (Auth::user()->can('manage-project')) {
            $items = Project::query()
                ->select('id', 'name', 'description', 'budget', 'start_date', 'end_date', 'status', 'created_by')
                ->with(['teamMembers:' . implode(',', $this->userSelectColumns())])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-project')) {
                         $q->where('created_by', creatorId());
                    } else if(Auth::user()->can('manage-own-project')) {
                        // For all other users, show projects they are involved in
                        $q->where(function($subQ) {
                            // Show own created projects
                            $subQ->where('creator_id', Auth::id());
                            // OR show assigned projects (for staff)
                            if (Auth::user()->type === 'client') {
                                $subQ->orWhereHas('clients', function($clientQ) {
                                    $clientQ->where('client_id', Auth::id());
                                });
                            } else {
                                $subQ->orWhereHas('teamMembers', function($teamQ) {
                                    $teamQ->where('user_id', Auth::id());
                                });
                            }
                        });
                    } else {
                        // If no permissions, show nothing
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('name'), fn($q) => $q->where('name', 'like', '%' . request('name') . '%'))
                ->when(request('status'), fn($q) => $q->where('status', request('status')))
                ->when(request('scope'), fn($q, $s) => $q->where('project_scope', $s))
                ->when(request('date'), fn($q) => $q->where('start_date', '<=', request('date'))->where('end_date', '>=', request('date')))
                ->when(true, fn($q) => $this->applySafeSort($q, [
                    'name' => 'name',
                    'budget' => 'budget',
                    'start_date' => 'start_date',
                    'end_date' => 'end_date',
                    'status' => 'status',
                    'created_at' => 'created_at',
                ], 'latest'))
                ->paginate($this->safePerPage(10, 100))
                ->withQueryString();

            $creatorId = creatorId();
            $users = User::where(function ($query) use ($creatorId) {
                    $query->where(function ($ownerQuery) use ($creatorId) {
                        $ownerQuery->where('id', $creatorId)
                            ->where('is_disable', 0)
                            ->where('is_enable_login', 1);
                    })->orWhere(function ($q) use ($creatorId) {
                        $q->where('created_by', $creatorId)->activeEmp();
                    });
                })
                ->get(['id', 'name']);

            // Transform items to include team_members with avatars, lifecycle flags, and task count
            $items->getCollection()->transform(function ($project) {
                $project->team_members = $project->teamMembers->map(function ($member) {
                    return $this->formatProjectUserSummary($member);
                });
                $project->task_count = ProjectTask::where('project_id', $project->id)->count();

                // Add user relationship status
                $project->is_creator = $project->creator_id == Auth::id();
                $project->is_assigned = $project->teamMembers->contains('id', Auth::id());
                $project->user_role = $project->is_creator ? 'Creator' : ($project->is_assigned ? 'Team Member' : 'Other');

                return $project;
            });

            $companyDefaults = [
                'default_project_scope' => company_setting('taskly_default_project_scope') ?: 'client',
                'default_internal_category' => company_setting('taskly_default_internal_category') ?: 'opex',
                'default_currency' => company_setting('taskly_default_currency') ?: 'ETB',
            ];

            return Inertia::render('Taskly/Project/Index', [
                'items' => $items,
                'users' => $users,
                'companyDefaults' => $companyDefaults,
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreProjectRequest $request)
    {
        if (Auth::user()->can('create-project')) {
            $validated = $request->validated();

            DB::transaction(function () use ($request, $validated) {
                $project                = new Project();
                $project->name          = $validated['name'];
                $project->project_type  = $validated['project_type'] ?? 'general';
                $project->project_scope = $validated['project_scope'] ?? (company_setting('taskly_default_project_scope') ?: 'client');
                $project->internal_category = $validated['internal_category'] ?? ($project->project_scope === 'internal' ? (company_setting('taskly_default_internal_category') ?: 'opex') : null);
                $project->project_sponsor_id = $validated['project_sponsor_id'] ?? null;
                $project->business_case = $validated['business_case'] ?? null;
                $project->target_roi_savings = $validated['target_roi_savings'] ?? null;
                $project->currency_code = $validated['currency_code'] ?? (company_setting('taskly_default_currency') ?: 'ETB');
                $project->description   = \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null);
                $project->budget        = $validated['budget'] ?? 0;
                $project->estimated_hrs = $validated['estimated_hrs'] ?? 0;
                $project->start_date    = $validated['start_date'];
                $project->end_date      = $validated['end_date'];
                $project->branch_id     = $validated['branch_id'] ?? null;
                $project->department_id = $validated['department_id'] ?? null;
                $project->cost_center_id= $validated['cost_center_id'] ?? null;
                $project->status        = 'Ongoing';
                $project->creator_id    = Auth::id();
                $project->created_by    = creatorId();
                $project->save();

                $project->ensureShareToken();

                $project->teamMembers()->sync($validated['user_ids']);

                CreateProject::dispatch($request, $project);
            });

            return redirect()->route('project.index')->with('success', __('The project has been created successfully.'));
        } else {
            return redirect()->route('project.index')->with('error', __('Permission denied'));
        }
    }

    public function show(Project $project)
    {
        if (Auth::user()->can('view-project') && $project->created_by == creatorId()) {
            $project->load([
                'teamMembers:' . implode(',', $this->userSelectColumns()),
                'clients:id,name',
                'projectSponsor:id,name,avatar',
                'milestones.approver:id,name',
                'timesheets.user:id,name,avatar',
                'timesheets.task:id,title',
                'boqs',
                'siteDiaries.loggedBy:id,name',
                'logistics'
            ]);

            $openTaskCounts = [];
            ProjectTask::where('project_id', $project->id)
                ->whereDoesntHave('taskStage', fn ($q) => $q->where('complete', true))
                ->get(['id', 'assigned_to'])
                ->each(function ($task) use (&$openTaskCounts) {
                    foreach ($this->parseAssignedIds($task->assigned_to) as $uid) {
                        $openTaskCounts[$uid] = ($openTaskCounts[$uid] ?? 0) + 1;
                    }
                });

            $openBugCounts = [];
            ProjectBug::where('project_id', $project->id)
                ->whereDoesntHave('bugStage', fn ($q) => $q->where('complete', true))
                ->get(['id', 'assigned_to'])
                ->each(function ($bug) use (&$openBugCounts) {
                    foreach ($this->parseAssignedIds($bug->assigned_to) as $uid) {
                        $openBugCounts[$uid] = ($openBugCounts[$uid] ?? 0) + 1;
                    }
                });

            $project->teamMembers->each(function ($member) use ($openTaskCounts, $openBugCounts) {
                $member->setAttribute('is_deactivated', $member->is_deactivated);
                $member->setAttribute('account_status_label', $member->account_status_label);
                $member->setAttribute('open_task_count', $openTaskCounts[(int) $member->id] ?? 0);
                $member->setAttribute('open_bug_count', $openBugCounts[(int) $member->id] ?? 0);
            });

            $evmMetrics = $project->getEvmMetrics();
            $shareUrl = url('/p/track/' . $project->ensureShareToken());
            $tasksWithDeps = $project->tasks()->with(['stage:id,name,color', 'predecessor:id,title'])->get();

            if(!Auth::user()->can('manage-any-project') && $project->creator_id != Auth::id())
            {
                if (Auth::user()->can('manage-own-project'))
                {
                    // Check if user is team member or client
                    $isTeamMember = $project->teamMembers->contains('id', Auth::id());
                    $isClient = $project->clients->contains('id', Auth::id());

                    if (!$isTeamMember && !$isClient) {
                        return redirect()->route('project.index')->with('error', __('Access denied'));
                    }
                }
                else {
                    return redirect()->route('project.index')->with('error', __('Access denied'));
                }
            }

            $teamMembers = User::activeEmp()
                ->where('created_by', creatorId())
                ->whereNotIn('id', function ($q) use ($project) {
                    $q->select('user_id')->from('project_users')->where('project_id', '=', $project->id);
                })
                ->get(['id', 'name']);

            $available_clients = User::where('type', 'client')
                ->where('created_by', creatorId())
                ->whereNotIn('id', function ($q) use ($project) {
                    $q->select('client_id')->from('project_clients')->where('project_id', '=', $project->id);
                })
                ->get(['id', 'name']);

            // Get project statistics
            $taskCount = ProjectTask::where('project_id', $project->id)->count();
            $bugCount = ProjectBug::where('project_id', $project->id)->where('created_by', creatorId())->count();

            // Calculate days left
            $daysLeft = $project->end_date ? max(0, now()->diffInDays($project->end_date, false)) : 0;

            // Get task stages and last 5 months stage-wise task counts
            $taskStages = \Workdo\Taskly\Models\TaskStage::where('created_by', creatorId())->orderBy('order')->get();
            $chartData = [];
            for ($i = 4; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $monthData = ['name' => $date->format('M')];

                foreach ($taskStages as $stage) {
                    $stageTaskCount = ProjectTask::where('project_id', $project->id)
                        ->where('created_by', creatorId())
                        ->where('stage_id', $stage->id)
                        ->whereRaw('YEAR(STR_TO_DATE(SUBSTRING_INDEX(duration, " - ", 1), "%Y-%m-%d")) = ?', [$date->year])
                        ->whereRaw('MONTH(STR_TO_DATE(SUBSTRING_INDEX(duration, " - ", 1), "%Y-%m-%d")) = ?', [$date->month])
                        ->count();
                    $monthData[$stage->name] = $stageTaskCount;
                }
                $chartData[] = $monthData;
            }

            // Prepare lines configuration for multiple line chart
            $chartLines = [];
            foreach ($taskStages as $stage) {
                $chartLines[] = [
                    'dataKey' => $stage->name,
                    'color' => $stage->color,
                    'name' => $stage->name
                ];
            }
            // Get recent activity logs
            $activityLogs = ActivityLog::where('project_id', $project->id)
                ->with('user:id,name')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($log) {
                    return [
                        'id' => $log->id,
                        'log_type' => $log->log_type,
                        'remark' => $log->getRemark(),
                        'created_at' => $log->created_at,
                        'user' => $log->user
                    ];
                });

            return Inertia::render('Taskly/Project/View', [
                'project' => $project,
                'teamMembers' => $teamMembers,
                'available_clients' => $available_clients,
                'projectStats' => [
                    'taskCount' => $taskCount,
                    'bugCount' => $bugCount,
                    'daysLeft' => $daysLeft,
                    'budget' => $project->budget,
                    'currency' => $project->currency_code ?? 'ETB',
                    'project_type' => $project->project_type ?? 'general',
                    'project_scope' => $project->project_scope ?? 'client',
                    'internal_category' => $project->internal_category,
                    'project_sponsor' => $project->projectSponsor,
                    'business_case' => $project->business_case,
                    'target_roi_savings' => $project->target_roi_savings,
                ],
                'evmMetrics' => $evmMetrics,
                'shareUrl' => $shareUrl,
                'timesheets' => $project->timesheets,
                'boqs' => $project->boqs,
                'siteDiaries' => $project->siteDiaries,
                'logistics' => $project->logistics,
                'projectTasks' => $tasksWithDeps,
                'chartData' => $chartData,
                'chartLines' => $chartLines,
                'activityLogs' => $activityLogs,
                'projectFiles' => ProjectFile::where('project_id', $project->id)->get(),
                'companyPreferences' => [
                    'enable_evm' => company_setting('taskly_enable_evm') ?? 'on',
                    'enable_construction' => company_setting('taskly_enable_construction') ?? 'on',
                    'enable_logistics' => company_setting('taskly_enable_logistics') ?? 'on',
                    'enable_gantt_dependencies' => company_setting('taskly_enable_gantt_dependencies') ?? 'on',
                    'enable_milestone_approvals' => company_setting('taskly_enable_milestone_approvals') ?? 'on',
                    'cpi_warning_threshold' => (float)(company_setting('taskly_cpi_warning_threshold') ?: 0.90),
                    'spi_warning_threshold' => (float)(company_setting('taskly_spi_warning_threshold') ?: 0.90),
                ],
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function edit(Project $project)
    {
        if (Auth::user()->can('edit-project') && $project->created_by == creatorId()) {
            return response()->json([
                'project' => $project->only([
                    'id', 'name', 'description', 'budget', 'start_date', 'end_date', 'status',
                    'project_type', 'project_scope', 'internal_category', 'project_sponsor_id',
                    'business_case', 'target_roi_savings'
                ])
            ]);
        } else {
            return response()->json(['error' => 'Permission denied'], 403);
        }
    }


    public function update(UpdateProjectRequest $request, Project $project)
    {
        if (Auth::user()->can('edit-project') && $this->assertBelongsToTenant($project)) {
            $validated = $request->validated();

            DB::transaction(function () use ($request, $project, $validated) {
                $project->name          = $validated['name'];
                if (isset($validated['project_type'])) $project->project_type = $validated['project_type'];
                if (isset($validated['project_scope'])) $project->project_scope = $validated['project_scope'];
                if (isset($validated['internal_category'])) $project->internal_category = $validated['internal_category'];
                if (array_key_exists('project_sponsor_id', $validated)) $project->project_sponsor_id = $validated['project_sponsor_id'];
                if (array_key_exists('business_case', $validated)) $project->business_case = $validated['business_case'];
                if (array_key_exists('target_roi_savings', $validated)) $project->target_roi_savings = $validated['target_roi_savings'];
                if (isset($validated['currency_code'])) $project->currency_code = $validated['currency_code'];
                $project->description   = \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null);
                $project->budget        = $validated['budget'] ?? 0;
                if (isset($validated['estimated_hrs'])) $project->estimated_hrs = $validated['estimated_hrs'];
                $project->start_date    = $validated['start_date'];
                $project->end_date      = $validated['end_date'];
                if (isset($validated['branch_id'])) $project->branch_id = $validated['branch_id'];
                if (isset($validated['department_id'])) $project->department_id = $validated['department_id'];
                if (isset($validated['cost_center_id'])) $project->cost_center_id = $validated['cost_center_id'];
                if (isset($validated['status'])) $project->status = $validated['status'];
                if (isset($validated['is_client_visible'])) $project->is_client_visible = (bool) $validated['is_client_visible'];
                $project->save();

                UpdateProject::dispatch($request, $project);
            });

            return back()->with('success', __('The project details are updated successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied'));
        }
    }

    public function destroy(Project $project)
    {
        if (Auth::user()->can('delete-project') && $this->assertBelongsToTenant($project)) {
            DB::transaction(function () use ($project) {
                ProjectTask::where('project_id', '=', $project->id)->delete();
                ProjectBug::where('project_id', '=', $project->id)->delete();
                ProjectMilestone::where('project_id', '=', $project->id)->delete();
                ActivityLog::where('project_id', '=', $project->id)->delete();
                ProjectClient::where('project_id', '=', $project->id)->delete();
                ProjectUser::where('project_id', '=', $project->id)->delete();

                DestroyProject::dispatch($project);
                $project->delete();
            });
           return back()->with('success', __('The project has been deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied'));
        }
    }

    public function duplicate(Request $request, Project $project)
    {
        if (Auth::user()->can('duplicate-project')) {
            $originalProject = Project::with(['bugs.comments', 'tasks.subtasks', 'tasks.comments', 'activityLogs', 'files'])->find($project->id);
            $newProject = $originalProject->replicate();
            $newProject->name = $originalProject->name . ' (Copy)';
            $newProject->status = $originalProject->status;
            $newProject->creator_id = Auth::id();
            $newProject->created_by = creatorId();
            $newProject->save();

            // Duplicate based on selected options (only active team members)
            if ($request->get('all') || $request->get('teamMembers')) {
                $teamMemberIds = $originalProject->teamMembers()
                    ->where('users.is_disable', 0)
                    ->where('users.is_enable_login', 1)
                    ->pluck('user_id')
                    ->toArray();
                $newProject->teamMembers()->sync($teamMemberIds);
            }

            if ($request->get('all') || $request->get('clients')) {
                $clientIds = $originalProject->clients()->pluck('client_id')->toArray();
                $newProject->clients()->sync($clientIds);
            }

            if ($request->get('all') || $request->get('milestones')) {
                foreach ($originalProject->milestones ?? [] as $milestone) {
                    $newMilestone = $milestone->replicate();
                    $newMilestone->project_id = $newProject->id;
                    $newMilestone->save();
                }
            }

            if ($request->get('all') || $request->get('tasks')) {
                foreach ($originalProject->tasks ?? [] as $task) {
                    $newTask = $task->replicate();
                    $newTask->project_id = $newProject->id;
                    $newTask->save();

                    if ($request->get('all') || $request->get('taskSubtasks')) {
                        foreach ($task->subtasks ?? [] as $subtask) {
                            $newSubtask = $subtask->replicate();
                            $newSubtask->task_id = $newTask->id;
                            $newSubtask->save();
                        }
                    }

                    if ($request->get('all') || $request->get('taskComments')) {
                        foreach ($task->comments ?? [] as $comment) {
                            $newComment = $comment->replicate();
                            $newComment->task_id = $newTask->id;
                            $newComment->save();
                        }
                    }
                }
            }
            if ($request->get('all') || $request->get('bugs')) {
                foreach ($originalProject->bugs ?? [] as $bug) {
                    $newBug = $bug->replicate();
                    $newBug->project_id = $newProject->id;
                    $newBug->save();

                    if ($request->get('all') || $request->get('bugComments')) {
                        foreach ($bug->comments ?? [] as $comment) {
                            $newComment = $comment->replicate();
                            $newComment->bug_id = $newBug->id;
                            $newComment->save();
                        }
                    }
                }
            }

            if ($request->get('all') || $request->get('activity')) {
                foreach ($originalProject->activityLogs ?? [] as $log) {
                    $newLog = $log->replicate();
                    $newLog->project_id = $newProject->id;
                    $newLog->save();
                }
            }

            if ($request->get('all') || $request->get('projectFiles')) {
                foreach ($originalProject->files ?? [] as $file) {
                    $newFile = $file->replicate();
                    $newFile->project_id = $newProject->id;
                    $newFile->save();
                }
            }

            return redirect()->route('project.index')->with('success', __('The project has been duplicated successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied'));
        }
    }

    public function invite(Request $request, Project $project)
    {
        if (Auth::user()->can('invite-project-member') && $this->assertBelongsToTenant($project)) {
            $creatorId = creatorId();
            $validated = $request->validate([
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => [
                    'integer',
                    \Illuminate\Validation\Rule::exists('users', 'id')->where(function ($query) use ($creatorId) {
                        $query->where(function ($q) use ($creatorId) {
                            $q->where('created_by', $creatorId)->orWhere('id', $creatorId);
                        })->where('is_disable', 0)->where('is_enable_login', 1);
                    }),
                ],
            ]);

            DB::transaction(function () use ($project, $validated) {
                $project->teamMembers()->syncWithoutDetaching($validated['user_ids']);

                foreach ($validated['user_ids'] as $userId) {
                    ActivityLog::create([
                        'user_id' => Auth::user()->id,
                        'user_type' => get_class(Auth::user()),
                        'project_id' => $project->id,
                        'log_type' => 'Invite User',
                        'remark' => json_encode(['user_id' => $userId]),
                    ]);
                }
            });

            ProjectInviteMember::dispatch($request, $project);

            return back()->with('success', __('User added to project successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    /**
     * Reassign open tasks & bugs from a separated/departing member to an active member
     * while preserving completed tasks, comments, and logged timesheets untouched.
     */
    public function reassignMemberWork(Request $request, Project $project)
    {
        if (
            !(Auth::user()->can('edit-project') || Auth::user()->can('edit-project-task') || Auth::user()->can('invite-project-member'))
            || !$this->assertBelongsToTenant($project)
        ) {
            return back()->with('error', __('Permission denied'));
        }

        $creatorId = (int) creatorId();
        $validated = $request->validate([
            'from_user_id' => ['required', 'integer', self::tenantExists('users', 'id')],
            'to_user_id' => [
                'required',
                'integer',
                'different:from_user_id',
                \Illuminate\Validation\Rule::exists('users', 'id')->where(function ($query) use ($creatorId) {
                    $query->where(function ($q) use ($creatorId) {
                        $q->where('created_by', $creatorId)->orWhere('id', $creatorId);
                    })->where('is_disable', 0)->where('is_enable_login', 1);
                }),
            ],
            'remove_from_team' => ['nullable', 'boolean'],
        ]);

        $fromUserId = (int) $validated['from_user_id'];
        $toUserId = (int) $validated['to_user_id'];
        $reassignedTasks = 0;
        $reassignedBugs = 0;

        DB::transaction(function () use ($project, $fromUserId, $toUserId, $validated, &$reassignedTasks, &$reassignedBugs) {
            // Reassign open tasks only (preserve Completed history & timesheets)
            $openTasks = ProjectTask::where('project_id', $project->id)
                ->whereDoesntHave('taskStage', fn ($q) => $q->where('complete', true))
                ->get();

            foreach ($openTasks as $task) {
                $ids = $this->parseAssignedIds($task->assigned_to);
                if (!in_array($fromUserId, $ids, true)) {
                    continue;
                }
                $newIds = array_values(array_unique(array_map(
                    fn ($id) => (int) $id === $fromUserId ? $toUserId : (int) $id,
                    $ids
                )));
                $task->assigned_to = json_encode(array_map('strval', $newIds));
                $task->save();
                $reassignedTasks++;
            }

            // Reassign open bugs only
            $openBugs = ProjectBug::where('project_id', $project->id)
                ->whereDoesntHave('bugStage', fn ($q) => $q->where('complete', true))
                ->get();

            foreach ($openBugs as $bug) {
                $ids = $this->parseAssignedIds($bug->assigned_to);
                if (!in_array($fromUserId, $ids, true)) {
                    continue;
                }
                $newIds = array_values(array_unique(array_map(
                    fn ($id) => (int) $id === $fromUserId ? $toUserId : (int) $id,
                    $ids
                )));
                $bug->assigned_to = json_encode(array_map('strval', $newIds));
                $bug->save();
                $reassignedBugs++;
            }

            // Ensure target member is part of project team
            $project->teamMembers()->syncWithoutDetaching([$toUserId]);

            if (!empty($validated['remove_from_team'])) {
                $project->teamMembers()->detach($fromUserId);
            }

            ActivityLog::create([
                'user_id' => Auth::id(),
                'user_type' => get_class(Auth::user()),
                'project_id' => $project->id,
                'log_type' => 'Reassign Member Work',
                'remark' => json_encode([
                    'from_user_id' => $fromUserId,
                    'to_user_id' => $toUserId,
                    'reassigned_tasks' => $reassignedTasks,
                    'reassigned_bugs' => $reassignedBugs,
                ]),
            ]);
        });

        return back()->with('success', __('Reassigned :tasks open task(s) and :bugs open bug(s) successfully.', [
            'tasks' => $reassignedTasks,
            'bugs' => $reassignedBugs,
        ]));
    }

    public function deleteMember(Request $request, Project $project)
    {
        if (Auth::user()->can('delete-project-member') && $this->assertBelongsToTenant($project)) {
            $validated = $request->validate([
                'user_id' => ['required', self::tenantExists('users', 'id')],
            ]);

            DB::transaction(function () use ($project, $validated) {
                $project->teamMembers()->detach($validated['user_id']);
            });

            ProjectDeleteShareToClient::dispatch($request, $project);

            return back()->with('success', __('User removed from project successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function inviteClient(Request $request, Project $project)
    {
        if (Auth::user()->can('invite-project-client') && $this->assertBelongsToTenant($project)) {
            $validated = $request->validate([
                'client_ids' => 'required|array|min:1',
                'client_ids.*' => self::tenantExists('users', 'id'),
            ]);

            DB::transaction(function () use ($project, $validated) {
                $project->clients()->attach($validated['client_ids']);

                foreach ($validated['client_ids'] as $clientId) {
                    $client = User::where('created_by', creatorId())->find($clientId);
                    if ($client) {
                        ActivityLog::create([
                            'user_id' => Auth::user()->id,
                            'user_type' => get_class(Auth::user()),
                            'project_id' => $project->id,
                            'log_type' => 'Share with Client',
                            'remark' => json_encode(['client_id' => $client->id]),
                        ]);
                    }
                }
            });

            ProjectShareToClient::dispatch($request, $project);

            return back()->with('success', __('Client added to project successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function deleteClient(Request $request, Project $project)
    {
        if (Auth::user()->can('delete-project-client') && $this->assertBelongsToTenant($project)) {
            $validated = $request->validate([
                'client_id' => ['required', self::tenantExists('users', 'id')],
            ]);

            ProjectDeleteClient::dispatch($request, $project);

            DB::transaction(function () use ($project, $validated) {
                $project->clients()->detach($validated['client_id']);
            });

            return back()->with('success', __('Client removed from project successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function storeMilestone(StoreMilestoneRequest $request, Project $project)
    {
        if (Auth::user()->can('create-project-milestone')) {
            $validated = $request->validated();

            $milestone              = $project->milestones()->create($validated);
            $milestone->title       = $validated['title'];
            $milestone->cost        = $validated['cost'];
            $milestone->start_date  = $validated['start_date'];
            $milestone->end_date    = $validated['end_date'];
            $milestone->summary     = $validated['summary'];
            $milestone->project_id  = $project->id;
            $milestone->save();

            // Log activity
            ActivityLog::create(
            [
                'user_id' => Auth::user()->id,
                'user_type' => get_class(Auth::user()),
                'project_id' => $project->id,
                'log_type' => 'Create Milestone',
                'remark' => json_encode(['title' => $milestone->title]),
            ]);

            CreateProjectMilestone::dispatch($request, $milestone);

            return back()->with('success', __('The milestone has been created successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function updateMilestone(UpdateMilestoneRequest $request, Project $project)
    {
        if (Auth::user()->can('edit-project-milestone')) {
            $validated = $request->validated();
            $milestone = $project->milestones()->findOrFail($validated['milestone_id']);
            $milestone->title       = $validated['title'];
            $milestone->cost        = $validated['cost'];
            $milestone->start_date  = $validated['start_date'];
            $milestone->end_date    = $validated['end_date'];
            $milestone->summary     = $validated['summary'];
            $milestone->status      = $validated['status'];
            $milestone->progress    = $validated['progress'];
            $milestone->save();

            UpdateProjectMilestone::dispatch($request, $milestone);

            return back()->with('success', __('The milestone details are updated successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function deleteMilestone(Request $request, Project $project)
    {
        if (Auth::user()->can('delete-project-milestone')) {
            $milestone = $project->milestones()->findOrFail($request->id);

            DestroyProjectMilestone::dispatch($milestone);

            $milestone->delete();
            return back()->with('success', __('The milestone has been deleted.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function storeFiles(Request $request, Project $project)
    {
        if (Auth::user()->can('edit-project')) {
            $request->validate([
                'images' => 'required|array',
                'images.*' => 'string'
            ]);

            foreach ($request->images as $imagePath) {
                $mediaFile = Media::where('file_name', basename($imagePath))->first();
                ProjectFile::create([
                    'project_id' => $project->id,
                    'file_name' => $mediaFile ? $mediaFile->name : basename($imagePath),
                    'file_path' => basename($imagePath),
                ]);
            }

            return back()->with('success', __('Files uploaded successfully.'));
        }
        return back()->with('error', __('Permission denied'));
    }

    public function deleteFile(ProjectFile $file)
    {
        if (Auth::user()->can('edit-project')) {
            $file->delete();
            return back()->with('success', __('The files has been deleted.'));
        }
        return back()->with('error', __('Permission denied'));
    }

    public function apiIndex()
    {
        if (Auth::user()->can('manage-project')) {
            $projects = Project::query()
                ->select('id', 'name')
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-project')) {
                         $q->where('created_by', creatorId());
                    } else if(Auth::user()->can('manage-own-project')) {
                        $q->where(function($subQ) {
                            $subQ->where('creator_id', Auth::id());
                            if (Auth::user()->type === 'client') {
                                $subQ->orWhereHas('clients', function($clientQ) {
                                    $clientQ->where('client_id', Auth::id());
                                });
                            } else {
                                $subQ->orWhereHas('teamMembers', function($teamQ) {
                                    $teamQ->where('user_id', Auth::id());
                                });
                            }
                        });
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->get();

            return response()->json($projects);
        }
        return response()->json(['error' => __('Permission denied')], 403);
    }
}
