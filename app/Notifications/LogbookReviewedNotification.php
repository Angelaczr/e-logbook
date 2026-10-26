<?php

namespace App\Notifications;

use App\Enums\LogbookStatus;
use App\Models\Logbook;
use App\Models\User;

class LogbookReviewedNotification extends LogbookNotification
{
    public function __construct(
        private readonly Logbook $logbook,
        private readonly User $reviewer,
        private readonly LogbookStatus $decision,
        private readonly ?string $notes,
    ) {}

    protected function title(): string
    {
        return match ($this->decision) {
            LogbookStatus::Approved => 'Logbook Anda disetujui',
            LogbookStatus::RevisionRequested => 'Logbook Anda perlu direvisi',
            LogbookStatus::Rejected => 'Logbook Anda ditolak',
            default => 'Status logbook Anda berubah',
        };
    }

    protected function message(): string
    {
        $text = "{$this->reviewer->name} meninjau logbook tanggal {$this->logbook->work_date->translatedFormat('j F Y')}: {$this->decision->label()}.";

        return $this->notes ? "{$text} Catatan: {$this->notes}" : $text;
    }

    protected function path(): string
    {
        return '/logbooks/'.$this->logbook->id;
    }

    protected function logbookId(): ?int
    {
        return $this->logbook->id;
    }
}
