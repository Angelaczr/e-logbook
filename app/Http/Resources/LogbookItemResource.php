<?php

namespace App\Http\Resources;

use App\Models\LogbookItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LogbookItem
 */
class LogbookItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'project' => $this->project ? ['id' => $this->project->id, 'code' => $this->project->code, 'name' => $this->project->name] : null,
            'task_description' => $this->task_description,
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            'duration_minutes' => $this->duration_minutes,
            'status_progress' => $this->status_progress->value,
            'status_progress_label' => $this->status_progress->label(),
            'output_link_or_attachment' => $this->output_link_or_attachment,
        ];
    }
}
