<?php

namespace App\Exceptions;

use App\Enums\LogbookStatus;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class InvalidLogbookTransitionException extends ConflictHttpException
{
    public static function between(LogbookStatus $from, LogbookStatus $to): self
    {
        return new self("Logbook berstatus {$from->label()} tidak dapat diubah menjadi {$to->label()}.");
    }

    public static function notEditable(LogbookStatus $status): self
    {
        return new self("Logbook berstatus {$status->label()} tidak dapat diedit.");
    }
}
