<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Manager = 'manager';
    case Supervisor = 'supervisor';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Manager => 'Manager',
            self::Supervisor => 'Supervisor',
            self::Employee => 'Karyawan',
        };
    }

    public function canReview(): bool
    {
        return match ($this) {
            self::SuperAdmin, self::Manager, self::Supervisor => true,
            self::Employee => false,
        };
    }

    public function canViewAnalytics(): bool
    {
        return match ($this) {
            self::SuperAdmin, self::Manager => true,
            self::Supervisor, self::Employee => false,
        };
    }
}
