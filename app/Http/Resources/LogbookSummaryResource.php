<?php

namespace App\Http\Resources;

use App\Models\Logbook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Logbook
 */
class LogbookSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_date' => $this->work_date->toDateString(),
            'shift' => $this->shift,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'total_minutes' => $this->total_minutes,
            'revision_count' => $this->revision_count,
            'items_count' => $this->items_count ?? $this->items->count(),
        ];
    }
}
