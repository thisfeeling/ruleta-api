<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\Translatable;

class Achievement extends Model
{
    use HasFactory, Translatable;

    protected $fillable = [
        'key',
        'name_es',
        'name_en',
        'description_es',
        'description_en',
        'icon',
        'rarity',
        'points',
        'is_secret',
        'unlock_criteria',
    ];

    protected $casts = [
        'is_secret' => 'boolean',
        'unlock_criteria' => 'array',
    ];

    // Relationships
    public function playerAchievements(): HasMany
    {
        return $this->hasMany(PlayerAchievement::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(AchievementProgress::class);
    }

    // Scopes
    public function scopeRarity($query, string $rarity)
    {
        return $query->where('rarity', $rarity);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_secret', false);
    }

    // Helpers
    public function getName(string $locale = 'es'): string
    {
        return $this->translate('name', $locale) ?? '';
    }

    public function getDescription(string $locale = 'es'): string
    {
        return $this->translate('description', $locale) ?? '';
    }
}
