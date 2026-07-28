<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'provider',
    'requester_id',
    'requester_type',
    'payload_in',
    'payload_out',
    'action',
    'status',
])]
class WebhookRequest extends Model
{
    protected $casts = [
        'payload_in' => 'array',
        'payload_out' => 'array',
    ];
}
