<?php

namespace App\Http\Controllers\Portal;

use App\Enums\OrganizationRole;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Services\Portal\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Team & access. Everyone sees who is on the team; only owners invite, change roles or remove.
 */
class TeamController extends PortalController
{
    public function __construct(private TeamService $team) {}

    public function index(Request $request): View
    {
        $organization = $this->organization($request);

        return $this->page($request, 'portal.team', 'team', [
            'members' => $organization->members()->orderBy('name')->get(['users.id', 'users.name', 'users.email']),
            'invitations' => $this->role($request)->canManageTeam()
                ? $organization->invitations()->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->get()
                : collect(),
            'roles' => OrganizationRole::cases(),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $this->requireOwner($request);
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'role' => ['required', Rule::enum(OrganizationRole::class)],
        ]);

        $result = $this->team->invite($this->organization($request), $validated['email'], OrganizationRole::from($validated['role']), $request->user());

        return back()->with('status', __('Invitation sent to :email.', ['email' => $result['invitation']->email]))
            ->with('invitation_link', route('invitations.show', $result['token']));
    }

    public function updateRole(Request $request, User $member): RedirectResponse
    {
        $this->requireOwner($request);
        $validated = $request->validate(['role' => ['required', Rule::enum(OrganizationRole::class)]]);

        $this->team->changeRole($this->organization($request), $member, OrganizationRole::from($validated['role']), $request->user());

        return back()->with('status', __('Role updated.'));
    }

    public function remove(Request $request, User $member): RedirectResponse
    {
        $this->requireOwner($request);

        $this->team->remove($this->organization($request), $member, $request->user());

        return $member->is($request->user())
            ? redirect()->route('account')->with('status', __('You left the organization.'))
            : back()->with('status', __('Member removed.'));
    }

    public function revoke(Request $request, OrganizationInvitation $invitation): RedirectResponse
    {
        $this->requireOwner($request);
        abort_unless($invitation->organization_id === $this->organization($request)->id, 404);

        $this->team->revoke($invitation, $request->user());

        return back()->with('status', __('Invitation cancelled.'));
    }
}
