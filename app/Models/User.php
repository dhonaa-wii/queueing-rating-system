<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable, SoftDeletes;

    protected $fillable = [
        'username',
        'email',
        'password',
        'account_status_id',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'must_change_password' => 'boolean',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function accountStatus(): BelongsTo
    {
        return $this->belongsTo(AccountStatus::class);
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function administratorProfile(): HasOne
    {
        return $this->hasOne(AdministratorProfile::class);
    }

    public function panelistProfile(): HasOne
    {
        return $this->hasOne(PanelistProfile::class);
    }

    public function attemptPanelAssignments(): HasMany
    {
        return $this->hasMany(AttemptPanelAssignment::class, 'panelist_user_id');
    }

    public function roleCode(): ?string
    {
        static $priority = ['SUPER_ADMIN', 'ADMIN', 'PANELIST'];

        $codes = $this->userRoles()->with('role')->get()->pluck('role.code')->all();

        foreach ($priority as $code) {
            if (in_array($code, $codes, true)) {
                return $code;
            }
        }

        return null;
    }

    public function hasRole(string $code): bool
    {
        return $this->userRoles()->whereHas('role', fn ($q) => $q->where('code', $code))->exists();
    }

    public function mustChangePassword(): bool
    {
        return $this->must_change_password
            || in_array($this->accountStatus->code, ['TEMPORARY_CREDENTIALS_ISSUED', 'PASSWORD_RESET_REQUIRED'], true);
    }

    public function dashboardRouteName(): string
    {
        return match ($this->roleCode()) {
            'SUPER_ADMIN' => 'super-admin.dashboard',
            'ADMIN' => 'admin.dashboard',
            'PANELIST' => 'panelist.dashboard',
            default => 'login',
        };
    }
}
