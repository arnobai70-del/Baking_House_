<?php

namespace App\Services\Portal;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\OrderNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Team seats of a business / reseller organization. Only owners manage the team.
 *
 * - Invitations are one-time links (only the sha256 is stored, 7-day expiry) bound to an email
 *   address: the invitee must be signed in with that exact address to accept, so a forwarded
 *   link is useless to anyone else.
 * - An organization always keeps at least one owner.
 * - A removed member immediately loses portal access and organization prices (membership is
 *   re-checked on every request); their API tokens for that organization stop working too.
 */
class TeamService
{
    public const INVITATION_DAYS = 7;

    public function __construct(private AuditLogger $audit, private OrderNotifier $notifier) {}

    /**
     * @return array{invitation: OrganizationInvitation, token: string}
     *
     * @throws ValidationException
     */
    public function invite(Organization $organization, string $email, OrganizationRole $role, User $owner): array
    {
        $email = mb_strtolower(trim($email));

        if ($organization->members()->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => __('This person is already a member.')]);
        }
        if ($organization->members()->count() + $this->pendingCount($organization) >= (int) config('commerce.organization_max_members', 20)) {
            throw ValidationException::withMessages(['email' => __('Your team has reached the maximum number of members.')]);
        }

        OrganizationInvitation::query()->where('organization_id', $organization->id)->where('email', $email)
            ->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);

        $token = Str::random(40);
        $invitation = new OrganizationInvitation;
        $invitation->forceFill([
            'organization_id' => $organization->id,
            'email' => $email,
            'role' => $role,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $owner->id,
            'expires_at' => now()->addDays(self::INVITATION_DAYS),
        ])->save();

        $this->audit->record('organization.member_invited', $organization, after: ['email' => $email, 'role' => $role->value], actorId: $owner->id);

        $this->notifier->direct('team_invitation', null, $email, 'Invitation to join '.$organization->name.' on '.config('brand.name'),
            "{$owner->name} invited you to join {$organization->name} on ".config('brand.name')." as {$role->label()}. Accept within ".self::INVITATION_DAYS.' days: '.route('invitations.show', $token));

        return ['invitation' => $invitation, 'token' => $token];
    }

    /**
     * @throws ValidationException
     */
    public function accept(OrganizationInvitation $invitation, User $user): Organization
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages(['invitation' => __('This invitation has expired or was already used.')]);
        }
        if (mb_strtolower((string) $user->email) !== $invitation->email) {
            throw ValidationException::withMessages(['invitation' => __('This invitation was sent to a different email address. Sign in with that address to accept it.')]);
        }

        $organization = $invitation->organization()->firstOrFail();
        if (! $organization->isApproved()) {
            throw ValidationException::withMessages(['invitation' => __('This organization is not active.')]);
        }

        DB::transaction(function () use ($invitation, $user, $organization): void {
            $claimed = OrganizationInvitation::query()->whereKey($invitation->id)->whereNull('accepted_at')->whereNull('revoked_at')
                ->update(['accepted_at' => now(), 'accepted_by' => $user->id, 'updated_at' => now()]);
            if ($claimed !== 1) {
                throw ValidationException::withMessages(['invitation' => __('This invitation has expired or was already used.')]);
            }

            if (! $organization->hasMember($user)) {
                $organization->members()->attach($user->id, ['role' => $invitation->role->value]);
            }
        });

        $this->audit->record('organization.member_joined', $organization, after: ['user_id' => $user->id, 'role' => $invitation->role->value], actorId: $user->id);

        return $organization;
    }

    public function revoke(OrganizationInvitation $invitation, User $owner): void
    {
        if ($invitation->revoked_at === null && $invitation->accepted_at === null) {
            $invitation->forceFill(['revoked_at' => now()])->save();
            $this->audit->record('organization.invitation_revoked', $invitation->organization, after: ['email' => $invitation->email], actorId: $owner->id);
        }
    }

    /**
     * @throws ValidationException
     */
    public function changeRole(Organization $organization, User $member, OrganizationRole $role, User $owner): void
    {
        $current = $member->roleIn($organization);
        abort_if($current === null, 404);

        if ($current === OrganizationRole::Owner && $role !== OrganizationRole::Owner) {
            $this->assertAnotherOwner($organization, $member);
        }

        $organization->members()->updateExistingPivot($member->id, ['role' => $role->value]);
        $this->audit->record('organization.member_role_changed', $organization, ['user_id' => $member->id, 'role' => $current->value], ['user_id' => $member->id, 'role' => $role->value], actorId: $owner->id);
    }

    /**
     * @throws ValidationException
     */
    public function remove(Organization $organization, User $member, User $owner): void
    {
        $current = $member->roleIn($organization);
        abort_if($current === null, 404);

        if ($current === OrganizationRole::Owner) {
            $this->assertAnotherOwner($organization, $member);
        }

        $organization->members()->detach($member->id);
        $this->audit->record('organization.member_removed', $organization, ['user_id' => $member->id, 'role' => $current->value], actorId: $owner->id);
    }

    private function pendingCount(Organization $organization): int
    {
        return OrganizationInvitation::query()->where('organization_id', $organization->id)
            ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count();
    }

    /**
     * @throws ValidationException
     */
    private function assertAnotherOwner(Organization $organization, User $member): void
    {
        $otherOwners = DB::table('organization_user')->where('organization_id', $organization->id)
            ->where('role', OrganizationRole::Owner->value)->where('user_id', '!=', $member->id)->count();

        if ($otherOwners === 0) {
            throw ValidationException::withMessages(['member' => __('An organization needs at least one owner. Make someone else an owner first.')]);
        }
    }
}
