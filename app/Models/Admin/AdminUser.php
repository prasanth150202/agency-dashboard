<?php

namespace App\Models\Admin;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * The real `admin_users` table — BRIX's own internal admin accounts,
 * distinct from partner logins (organisations/users). Authenticates via
 * the 'admin' guard (config/auth.php), never the default 'web' guard.
 */
class AdminUser extends Authenticatable
{
    use Notifiable;

    public const ROLE_SUPER_ADMIN = 'SUPER_ADMIN';

    public const ROLE_FINANCE_ADMIN = 'FINANCE_ADMIN';

    public const ROLE_SUPPORT_ADMIN = 'SUPPORT_ADMIN';

    public const ROLE_ANALYST = 'ANALYST';

    /** Roles allowed to approve/reject payouts and edit commission rates. */
    public const FINANCE_ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_FINANCE_ADMIN];

    protected $table = 'admin_users';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'role',
        'status',
        'avatar_color',
        'last_login_at',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** The real column is password_hash, not Laravel's default `password`. */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function isFinance(): bool
    {
        return in_array($this->role, self::FINANCE_ROLES, true);
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN => 'Super Admin',
            self::ROLE_FINANCE_ADMIN => 'Finance Admin',
            self::ROLE_SUPPORT_ADMIN => 'Support Admin',
            self::ROLE_ANALYST => 'Analyst',
            default => $this->role,
        };
    }
}
