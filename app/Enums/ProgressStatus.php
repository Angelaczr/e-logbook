<?php

namespace App\Enums;

enum ProgressStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Pending = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'Sedang Dikerjakan',
            self::Completed => 'Selesai',
            self::Pending => 'Tertunda',
        };
    }
}
