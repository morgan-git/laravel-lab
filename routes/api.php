<?php

use App\Http\Controllers\WebhookController;

Route::post('webhook/{provider}', [WebhookController::class, 'handle'])
    ->middleware('throttle:30,1');
