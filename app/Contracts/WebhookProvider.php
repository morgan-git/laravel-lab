<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface WebhookProvider
{
    public function verify(Request $request): bool;

    public function respond(mixed $data): JsonResponse;
}
