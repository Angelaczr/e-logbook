<?php

namespace App\Http\Resources;

use App\Models\Logbook;
use Illuminate\Http\Request;

/**
 * @mixin Logbook
 */
class ReviewRowResource extends LogbookSummaryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'employee' => ['id' => $this->user->id, 'name' => $this->user->name],
            'submitted_at' => $this->submitted_at?->toIso8601String(),
        ];
    }
}
