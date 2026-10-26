<?php

namespace App\Enums;

enum LogbookStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case RevisionRequested = 'revision_requested';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Diajukan',
            self::Approved => 'Disetujui',
            self::RevisionRequested => 'Perlu Revisi',
            self::Rejected => 'Ditolak',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted],
            self::Submitted => [self::Approved, self::RevisionRequested, self::Rejected],
            self::RevisionRequested => [self::Submitted],
            self::Approved, self::Rejected => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isEditable(): bool
    {
        return match ($this) {
            self::Draft, self::RevisionRequested => true,
            self::Submitted, self::Approved, self::Rejected => false,
        };
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'blue',
            self::Approved => 'green',
            self::RevisionRequested => 'amber',
            self::Rejected => 'red',
        };
    }
}
