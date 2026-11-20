@extends('portal.layout', ['heading' => __('Team & access'), 'subheading' => __('Who can order, see prices and download statements for :organization', ['organization' => $organization->name])])

@section('portal')
    @if (session('invitation_link'))
        <div class="alert alert-info" style="margin-bottom:16px"><x-icon name="info" />
            <div>{{ __('We also show the invitation link once here, in case email is not delivered. Share it only with the invited person; it works only for their email address.') }}
                <div class="tnum small" style="word-break:break-all;margin-top:6px"><code>{{ session('invitation_link') }}</code></div></div></div>
    @endif

    <div class="card" style="margin-bottom:16px">
        <div class="panel-title"><h2>{{ __('Team members') }}</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Role') }}</th><th>{{ __('Can order') }}</th>@if ($role->canManageTeam())<th><span class="sr-only">{{ __('Actions') }}</span></th>@endif</tr></thead>
            <tbody>
            @foreach ($members as $member)
                @php $memberRole = \App\Enums\OrganizationRole::from($member->pivot->role); @endphp
                <tr>
                    <td><strong>{{ $member->name }}</strong>@if ($member->is(auth()->user())) <span class="muted small">({{ __('you') }})</span>@endif @if ($role->canManageTeam())<div class="muted small">{{ $member->email }}</div>@endif</td>
                    <td>{{ __($memberRole->label()) }}</td>
                    <td>{{ $memberRole->canPlaceOrders() ? __('Yes') : __('No') }}</td>
                    @if ($role->canManageTeam())
                        <td class="row" style="gap:6px;flex-wrap:nowrap">
                            <form method="POST" action="{{ route($portal.'.team.role', $member) }}" class="row" style="gap:6px;flex-wrap:nowrap">
                                @csrf @method('PATCH')
                                <label class="sr-only" for="role-{{ $member->id }}">{{ __('Role') }}</label>
                                <select class="input" id="role-{{ $member->id }}" name="role" style="min-height:34px">
                                    @foreach ($roles as $option)<option value="{{ $option->value }}" @selected($option === $memberRole)>{{ __($option->label()) }}</option>@endforeach
                                </select>
                                <button class="btn btn-outline btn-sm" type="submit">{{ __('Save') }}</button>
                            </form>
                            <form method="POST" action="{{ route($portal.'.team.remove', $member) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" type="submit">{{ __('Remove') }}</button></form>
                        </td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>

    @if ($role->canManageTeam())
        <div class="portal-grid">
            <div class="card">
                <div class="panel-title"><h2>{{ __('Invite a member') }}</h2></div>
                <form method="POST" action="{{ route($portal.'.team.invite') }}" class="card-pad" novalidate>
                    @csrf
                    <div class="field"><label for="invite-email">{{ __('Email address') }}</label><input class="input" id="invite-email" name="email" type="email" value="{{ old('email') }}" required autocomplete="off"></div>
                    <div class="field"><label for="invite-role">{{ __('Role') }}</label>
                        <select class="input" id="invite-role" name="role">@foreach ($roles as $option)<option value="{{ $option->value }}" @selected($option->value === 'buyer')>{{ __($option->label()) }}</option>@endforeach</select></div>
                    <p class="hint">{{ __('The invitation is valid for 7 days and only for this email address. The person needs a Baking House account with that email.') }}</p>
                    <button class="btn btn-primary" type="submit"><x-icon name="users" /> {{ __('Send invitation') }}</button>
                </form>
            </div>
            <div class="card">
                <div class="panel-title"><h2>{{ __('Pending invitations') }}</h2></div>
                @if ($invitations->isEmpty())
                    <div class="empty-state" style="padding:24px"><p class="muted small">{{ __('No pending invitations.') }}</p></div>
                @else
                    <div class="table-wrap"><table class="table"><tbody>
                        @foreach ($invitations as $invitation)
                            <tr><td>{{ $invitation->email }}<div class="muted small">{{ __($invitation->role->label()) }} · {{ __('expires :date', ['date' => $invitation->expires_at->timezone('Asia/Dhaka')->format('d M')]) }}</div></td>
                                <td><form method="POST" action="{{ route($portal.'.team.revoke', $invitation) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" type="submit">{{ __('Cancel') }}</button></form></td></tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </div>
        </div>
    @endif

    <div class="card card-pad" style="margin-top:16px">
        <h2 style="font-size:var(--bh-fs-md)">{{ __('Roles') }}</h2>
        <ul class="small" style="margin:0;padding-left:18px">
            <li><strong>{{ __('Owner') }}</strong>: {{ __('everything, including team members, addresses and quotations.') }}</li>
            <li><strong>{{ __('Buyer') }}</strong>: {{ __('orders, bulk orders, quotations and addresses.') }}</li>
            <li><strong>{{ __('Viewer (read only)') }}</strong>: {{ __('sees orders, prices, invoices and statements; cannot order.') }}</li>
        </ul>
    </div>
@endsection
