<?php

namespace App\Policies;

use App\Enums\LogbookStatus;
use App\Enums\UserRole;
use App\Models\Logbook;
use App\Models\User;

class LogbookPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Logbook $logbook): bool
    {
        return $this->owns($user, $logbook) || $this->isReviewerOf($user, $logbook);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Logbook $logbook): bool
    {
        return $this->owns($user, $logbook) && $logbook->status->isEditable();
    }

    public function delete(User $user, Logbook $logbook): bool
    {
        return $this->owns($user, $logbook) && $logbook->status->isEditable();
    }

    public function submit(User $user, Logbook $logbook): bool
    {
        return $this->owns($user, $logbook)
            && $logbook->status->canTransitionTo(LogbookStatus::Submitted)
            && $logbook->items()->exists();
    }

    public function review(User $user, Logbook $logbook): bool
    {
        return $logbook->status === LogbookStatus::Submitted
            && ! $this->owns($user, $logbook)
            && $this->isReviewerOf($user, $logbook);
    }

    private function owns(User $user, Logbook $logbook): bool
    {
        return $logbook->user_id === $user->id;
    }

    /**
     * Super admin, atasan langsung, atau manager di atas hierarki pemilik logbook.
     */
    private function isReviewerOf(User $user, Logbook $logbook): bool
    {
        $owner = $logbook->user;

        if ($owner === null) {
            return false;
        }

        return match ($user->role) {
            UserRole::SuperAdmin => true,
            UserRole::Manager => $user->isAboveInHierarchy($owner),
            UserRole::Supervisor => $user->isDirectSupervisorOf($owner),
            UserRole::Employee => false,
        };
    }
}
