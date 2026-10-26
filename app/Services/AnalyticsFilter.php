<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

final class AnalyticsFilter
{
    public function __construct(
        public readonly User $viewer,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?int $departmentId = null,
    ) {}

    public function cacheKey(): string
    {
        return md5(implode('|', [
            $this->viewer->id,
            $this->viewer->role->value,
            $this->from->toDateString(),
            $this->to->toDateString(),
            $this->departmentId ?? 'all',
        ]));
    }
}
