<?php

namespace Workdo\Taskly\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Workdo\Taskly\Models\Project;

class CreateProject
{
    use Dispatchable, SerializesModels;

    public $request;
    public Project $project;

    public function __construct(
        $request,
        Project $project
    ) {
        $this->request = $request;
        $this->project = $project;
    }

    public function __serialize(): array
    {
        return [
            'project' => $this->project,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->project = $data['project'] ?? null;
        $this->request = request();
    }
}
