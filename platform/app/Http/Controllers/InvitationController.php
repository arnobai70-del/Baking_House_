<?php

namespace App\Http\Controllers;

use App\Models\OrganizationInvitation;
use App\Services\OrganizationContext;
use App\Services\Portal\TeamService;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Accept a team invitation (/invitations/{token}). Requires sign-in with the invited address.
 */
class InvitationController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $invitation = OrganizationInvitation::findByToken($token);
        abort_if($invitation === null, 404);

        if ($request->user() === null) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return view('portal.invitation', [
            'seo' => (new Seo(title: __('Team invitation')))->noindex(),
            'invitation' => $invitation->load('organization:id,name,type,status'),
            'token' => $token,
        ]);
    }

    public function accept(Request $request, string $token, TeamService $team): RedirectResponse
    {
        $invitation = OrganizationInvitation::findByToken($token);
        abort_if($invitation === null, 404);

        $organization = $team->accept($invitation, $request->user());
        (new OrganizationContext($request->session()))->switchTo($request->user(), $organization->id);

        return redirect()->route($organization->type->value.'.dashboard')->with('status', __('Welcome to :organization.', ['organization' => $organization->name]));
    }
}
