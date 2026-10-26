<?php

namespace App\Notifications;

class PendingReviewReminder extends LogbookNotification
{
    public function __construct(
        private readonly int $count,
        private readonly int $days,
    ) {}

    protected function title(): string
    {
        return 'Pengingat: logbook menunggu review';
    }

    protected function message(): string
    {
        return "{$this->count} logbook bawahan Anda sudah menunggu review lebih dari {$this->days} hari.";
    }

    protected function path(): string
    {
        return '/reviews';
    }
}
