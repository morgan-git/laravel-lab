<?php

use App\Http\Controllers\WebhookController;


Route::post('/webhook/discord', [WebhookController::class, 'discord'])
    ->middleware('throttle:30,1'); // 30 requests per minute per IP
