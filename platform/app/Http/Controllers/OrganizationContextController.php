<?php

namespace App\Http\Controllers;

use App\Services\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Explicit account-context switch for users who belong to several organizations.
 */
class OrganizationContextController extends Controller
{
    public function __invoke(Request $request, OrganizationContext $context): RedirectResponse
    {
        $validated = $request->validate(['organization_id' => ['required', 'integer']]);

        abort_unless($context->switchTo($request->user(), (int) $validated['organization_id']), 403);

        return back()->with('status', 'Account context switched.');
    }
}
