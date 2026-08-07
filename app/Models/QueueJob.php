<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

#[Table(name: 'jobs')]
#[WithoutTimestamps]
class QueueJob extends Model
{
    protected $casts = [
        'reserved_at' => 'datetime',
        'available_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Pull the job's class name out of its serialized payload.
     */
    public function displayName(): string
    {
        $payload = json_decode($this->payload, true);

        return $payload['displayName'] ?? $payload['job'] ?? 'Unknown job';
    }

    public function isReserved(): bool
    {
        return $this->reserved_at !== null;
    }

    /**
     * A job reserved for longer than this is almost certainly stuck —
     * the worker that picked it up probably crashed or was killed.
     */
    public function isStuck(): bool
    {
        return $this->isReserved()
            && $this->reserved_at->lt(now()->subMinutes(5));
    }
}
