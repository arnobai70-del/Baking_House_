<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Retail customer dashboard (/account). Shows only the signed-in user's own data.
 */
class AccountController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('account.index', [
            'user' => $user,
            'memberships' => $user->organizations()->orderBy('name')->get(),
        ]);
    }
}
