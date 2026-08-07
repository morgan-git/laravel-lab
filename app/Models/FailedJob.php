<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

#[Table(name: 'failed_jobs')]
#[WithoutTimestamps]
class FailedJob extends Model
{
    protected $casts = [
        'failed_at' => 'datetime',
    ];

    public function displayName(): string
    {
        $payload = json_decode($this->payload, true);

        return $payload['displayName'] ?? $payload['job'] ?? 'Unknown job';
    }

    /**
     * The exception column is a full stack trace — this grabs just the
     * first line (the actual error message) for display in a table.
     */
    public function shortException(): string
    {
        $firstLine = strtok($this->exception, "\n");

        return $firstLine !== false ? $firstLine : $this->exception;
    }
}
