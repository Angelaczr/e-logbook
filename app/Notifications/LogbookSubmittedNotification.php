<?php

namespace App\Notifications;

use App\Models\Logbook;
use App\Models\User;

class LogbookSubmittedNotification extends LogbookNotification
{
    public function __construct(
        private readonly Logbook $logbook,
        private readonly User $submitter,
    ) {}

    protected function title(): string
    {
        return 'Logbook baru menunggu review';
    }

    protected function message(): string
    {
        return "{$this->submitter->name} mengirim logbook tanggal {$this->logbook->work_date->translatedFormat('j F Y')}.";
    }

    protected function path(): string
    {
        return '/reviews?review='.$this->logbook->id;
    }

    protected function logbookId(): ?int
    {
        return $this->logbook->id;
    }
}
