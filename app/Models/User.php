<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;

#[Fillable(['name', 'email', 'password', 'status', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_SUSPENDED = 'suspended';

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'previous_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'is_platform_owner' => 'boolean',
            'failed_login_attempts' => 'integer',
            'avatar_media_id' => 'integer',
            'last_activity_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot(['assigned_by', 'expires_at'])
            ->withTimestamps()
            ->where(function ($query): void {
                $query->whereNull('role_user.expires_at')
                    ->orWhere('role_user.expires_at', '>', now());
            });
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function avatar(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'avatar_media_id');
    }

    public function emailChanges(): HasMany
    {
        return $this->hasMany(UserEmailChange::class);
    }

    public function avatarUrl(): ?string
    {
        if ($this->avatar_media_id === null) {
            return null;
        }

        $avatar = $this->relationLoaded('avatar')
            ? $this->getRelation('avatar')
            : $this->avatar()->first();

        if (! $avatar instanceof Media) {
            return null;
        }

        return URL::temporarySignedRoute(
            'account.avatar.download',
            now()->addMinutes(30),
            [
                'locale' => $this->locale ?: 'ar',
                'media' => $this->avatar_media_id,
            ],
        );
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ($this->locked_until === null || $this->locked_until->isPast());
    }

    public function isPlatformOwner(): bool
    {
        return (bool) $this->is_platform_owner;
    }

    /** @return array<string, mixed> */
    public function toAccessArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'locale' => $this->locale,
            'status' => $this->status,
            'is_platform_owner' => $this->isPlatformOwner(),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'email_verified' => $this->hasVerifiedEmail(),
            'last_activity_at' => $this->last_activity_at?->toISOString(),
            'avatar_url' => $this->avatarUrl(),
            'roles' => $this->roles()->pluck('key')->all(),
        ];
    }
}
