<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar_url',
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
     * The attributes that should be cast.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // Relationships
    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function supervisedShows(): HasMany
    {
        return $this->hasMany(Show::class, 'supervisor_id');
    }

    public function audioPlays(): HasMany
    {
        return $this->hasMany(AudioPlay::class, 'played_by');
    }

    // Scopes
    public function scopeRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopePlayers($query)
    {
        return $query->where('role', 'player');
    }

    public function scopeSupervisors($query)
    {
        return $query->where('role', 'supervisor');
    }

    // Helpers
    public function isSupervisor(): bool
    {
        return $this->role === 'supervisor';
    }

    public function isPlayer(): bool
    {
        return $this->role === 'player';
    }
}
