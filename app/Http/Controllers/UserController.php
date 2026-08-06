<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function toggleAdmin(User $user): RedirectResponse
    {
        // Guard against locking yourself out as the only admin.
        if ($user->is_admin && $user->id === auth()->id() && User::where('is_admin', true)->count() === 1) {
            return redirect()
                ->route('admin.users.index')
                ->with('status', "Can't remove admin from yourself — you're the only admin.");
        }

        $user->update(['is_admin' => ! $user->is_admin]);

        $state = $user->is_admin ? 'granted' : 'revoked';

        return redirect()
            ->route('admin.users.index')
            ->with('status', "Admin access {$state} for {$user->name}.");
    }
}
