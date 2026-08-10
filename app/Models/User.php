<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, \Illuminate\Auth\MustVerifyEmail;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /** Owner, admin, or staff — anyone allowed into the dashboard at all. */
    public function canAccessDashboard(): bool
    {
        return in_array($this->role, ['owner', 'admin', 'staff'], true);
    }

    /**
     * Keeps the legacy `role` scalar column in sync with whichever Spatie
     * role this user actually holds, so every existing consumer that reads
     * `$user->role` (API responses, the frontend's `AuthUser.role`) keeps
     * working unchanged — Spatie's `roles`/`model_has_roles` tables remain
     * the actual source of truth for permission checks.
     */
    public function syncRoleColumn(): void
    {
        $primaryRole = $this->roles()->value('name');

        if ($primaryRole && $primaryRole !== $this->role) {
            $this->forceFill(['role' => $primaryRole])->save();
        }
    }
}
