@extends('layouts.admin', ['title' => $lead->title])

@use('App\Support\Taka')
@use('App\Enums\LeadStage')

@section('admin')
    <div class="admin-top">
        <h1>{{ $lead->title }}</h1>
        <span class="status {{ $lead->stage->tone() }}">{{ $lead->stage->label() }}</span>
        <span class="spacer"></span>
        <a class="btn btn-ghost btn-sm" href="{{ route('admin.crm.leads.index') }}">← Leads</a>
    </div>
    @include('admin.crm._nav')

    <div class="admin-grid">
        <section>
            <div class="card card-pad" style="margin-bottom:16px">
                <table class="specs small"><tbody>
                    <tr><th>Source</th><td>{{ $lead->source->label() }}</td></tr>
                    <tr><th>Contact</th><td>{{ $lead->contact_name ?? '—' }}{{ $lead->contact_phone ? ' · '.$lead->contact_phone : '' }}{{ $lead->contact_email ? ' · '.$lead->contact_email : '' }}</td></tr>
                    @if ($lead->organization)<tr><th>Organization</th><td><a href="{{ route('admin.crm.organizations.show', $lead->organization) }}">{{ $lead->organization->name }}</a> ({{ $lead->organization->type->label() }}, {{ $lead->organization->status->value }})</td></tr>@endif
                    @if ($lead->customer)<tr><th>Customer</th><td><a href="{{ route('admin.crm.customers.show', $lead->customer) }}">{{ $lead->customer->name }}</a></td></tr>@endif
                    @if ($lead->quotation)<tr><th>Quotation</th><td>@can('quotations.manage')<a href="{{ route('admin.quotations.show', $lead->quotation) }}">{{ $lead->quotation->number }}</a>@else{{ $lead->quotation->number }}@endcan ({{ $lead->quotation->status->label() }})</td></tr>@endif
                    <tr><th>Estimated value</th><td class="tnum">{{ $lead->estimated_value_paisa ? Taka::format($lead->estimated_value_paisa) : '—' }}</td></tr>
                    <tr><th>Created</th><td>{{ $lead->created_at->timezone('Asia/Dhaka')->format('d M Y H:i') }}</td></tr>
                    @if ($lead->lost_reason)<tr><th>Lost because</th><td>{{ $lead->lost_reason }}</td></tr>@endif
                </tbody></table>
            </div>

            <div class="card card-pad" style="margin-bottom:16px">
                <h2 style="font-size:var(--bh-fs-md)">Responsible</h2>
                <p class="small">{{ $lead->assignee ? $lead->assignee->name : 'Nobody yet — this lead is in the shared queue.' }}</p>
                <form method="POST" action="{{ route('admin.crm.leads.assign', $lead) }}" class="row" style="gap:8px;align-items:flex-end">
                    @csrf
                    <div class="field" style="margin:0"><label for="assigned_to">Assign to</label>
                        <select class="input" id="assigned_to" name="assigned_to">
                            <option value="me">Me</option>
                            @foreach ($crmUsers as $crmUser)<option value="{{ $crmUser->id }}" @selected($lead->assigned_to === $crmUser->id)>{{ $crmUser->name }}</option>@endforeach
                            <option value="queue">Shared queue (unassign)</option>
                        </select></div>
                    <button class="btn btn-outline btn-sm" type="submit">Save</button>
                </form>
            </div>

            <div class="card card-pad">
                <h2 style="font-size:var(--bh-fs-md)">Pipeline stage</h2>
                <form method="POST" action="{{ route('admin.crm.leads.stage', $lead) }}" class="row" style="gap:8px;align-items:flex-end" novalidate>
                    @csrf
                    <div class="field" style="margin:0"><label for="stage">Stage</label>
                        <select class="input" id="stage" name="stage">@foreach (LeadStage::cases() as $case)<option value="{{ $case->value }}" @selected($lead->stage === $case)>{{ $case->label() }}</option>@endforeach</select></div>
                    <div class="field" style="flex:1;margin:0"><label for="reason">Note (required when lost)</label><input class="input" id="reason" name="reason" maxlength="500"></div>
                    <button class="btn btn-teal btn-sm" type="submit">Update</button>
                </form>
                @if ($lead->quotation)<p class="hint" style="margin-top:8px">RFQ leads also move automatically when the quotation is issued, ordered, declined, closed or expires.</p>@endif
            </div>
        </section>
        <section>
            @include('admin.crm._records', ['type' => 'lead', 'id' => $lead->id, 'tags' => $lead->crmTags])
        </section>
    </div>
@endsection
