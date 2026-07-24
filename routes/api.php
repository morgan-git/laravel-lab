<?php

use App\Http\Controllers\WebhookController;

Route::post('/webhook/discord', [WebhookController::class, 'discord']);
