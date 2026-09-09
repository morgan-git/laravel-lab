<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebhookRequest;
use Illuminate\View\View;

class WebhookRequestController extends Controller
{
    /**
     * Most recent webhook requests, newest first. This page is a full
     * client-side sortable/filterable table, same as the feed sources
     * admin page, so we cap what gets shipped to the browser rather
     * than sending the entire log as it grows. Bump this or switch to
     * real pagination if 500 stops being enough.
     */
    private const int ROW_LIMIT = 500;

    public function index(): View
    {
        $requests = WebhookRequest::orderByDesc('created_at')
            ->limit(self::ROW_LIMIT)
            ->get();

        return view('admin.webhook-requests.index', [
            'requests' => $requests,
        ]);
    }
}
