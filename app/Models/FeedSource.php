<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'provider',
    'handle',
    'display_name',
    'active',
    'last_fetched_at',
])]
class FeedSource extends Model
{
    use HasFactory;

    protected $casts = [
        'active' => 'boolean',
        'last_fetched_at' => 'datetime',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(FeedPost::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }
}
