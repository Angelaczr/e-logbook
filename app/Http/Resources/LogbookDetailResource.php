<?php

namespace App\Http\Resources;

use App\Models\Logbook;
use Illuminate\Http\Request;

/**
 * @mixin Logbook
 */
class LogbookDetailResource extends LogbookSummaryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::toArray($request),
            'owner' => ['id' => $this->user->id, 'name' => $this->user->name],
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'approver' => $this->approver ? ['id' => $this->approver->id, 'name' => $this->approver->name] : null,
            'rejection_reason' => $this->rejection_reason,
            'items' => LogbookItemResource::collection($this->items)->resolve($request),
            'histories' => $this->histories->map(fn ($history) => [
                'id' => $history->id,
                'actor' => $history->actor?->name,
                'from_status_label' => $history->from_status?->label(),
                'to_status' => $history->to_status->value,
                'to_status_label' => $history->to_status->label(),
                'notes' => $history->notes,
                'created_at' => $history->created_at->toIso8601String(),
            ])->all(),
            'can' => [
                'update' => $user->can('update', $this->resource),
                'delete' => $user->can('delete', $this->resource),
                'submit' => $user->can('submit', $this->resource),
                'review' => $user->can('review', $this->resource),
            ],
        ];
    }
}
