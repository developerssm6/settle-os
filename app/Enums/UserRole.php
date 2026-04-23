<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Partner = 'partner';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Partner => 'Partner',
            self::Viewer => 'Viewer',
        };
    }

    public function isAdminLike(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin]);
    }
}
