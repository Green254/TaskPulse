<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'department_id',
        'is_suspended',
        'suspended_until',
        'suspension_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'is_suspended' => 'boolean',
            'suspended_until' => 'datetime',
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_users');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function projects()
    {
        return $this->hasMany(Projects::class, 'created_by');
    }

    public function tasks()
    {
        return $this->hasMany(Tasks::class, 'assigned_to');
    }

    public function subordinates()
    {
        return $this->belongsToMany(User::class, 'manager_subordinates', 'manager_id', 'subordinate_id')
            ->withTimestamps();
    }

    public function managers()
    {
        return $this->belongsToMany(User::class, 'manager_subordinates', 'subordinate_id', 'manager_id')
            ->withTimestamps();
    }

    public function createdAnnouncements()
    {
        return $this->hasMany(Announcement::class, 'created_by');
    }

    public function createdThemes()
    {
        return $this->hasMany(SystemTheme::class, 'created_by');
    }

    public function hasRole($role)
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function isCurrentlySuspended(): bool
    {
        if (!$this->is_suspended) {
            return false;
        }

        if (is_null($this->suspended_until)) {
            return true;
        }

        return $this->suspended_until->isFuture();
    }

    public function scopeCurrentlySuspended(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_suspended', true)
            ->where(function (Builder $builder) use ($now) {
                $builder->whereNull('suspended_until')
                    ->orWhere('suspended_until', '>', $now);
            });
    }
}
