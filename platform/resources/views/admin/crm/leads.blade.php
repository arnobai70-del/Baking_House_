@extends('layouts.admin', ['title' => 'CRM leads'])

@use('App\Support\Taka')

@section('admin')
    <div class="admin-top"><h1>Leads &amp; pipeline</h1></div>
    @include('admin.crm._nav')

    <form method="GET" class="card card-pad row" style="margin-bottom:16px;align-items:flex-end">
        <div class="field" style="flex:1;margin:0"><label for="q">Search title, contact or phone</label><input class="input" id="q" name="q" value="{{ $search }}"></div>
        <div class="field" style="margin:0"><label for="stage">Stage</label>
            <select class="input" id="stage" name="stage"><option value="">Any</option>@foreach (\App\Enums\LeadStage::cases() as $case)<option value="{{ $case->value }}" @selected($stage === $case)>{{ $case->label() }} ({{ $pipeline[$case->value]['count'] }})</option>@endforeach</select></div>
        <div class="field" style="margin:0"><label for="owner">Owner</label>
            <select class="input" id="owner" name="owner">@foreach (['all' => 'Everyone', 'queue' => 'Shared queue', 'mine' => 'Mine'] as $value => $label)<option value="{{ $value }}" @selected($owner === $value)>{{ $label }}</option>@endforeach</select></div>
        <button class="btn btn-outline" type="submit">Filter</button>
    </form>

    <div class="card" style="margin-bottom:16px"><div class="table-wrap"><table class="table">
        <thead><tr><th>Lead</th><th>Source</th><th>Stage</th><th>Owner</th><th>Est. value</th><th>Next follow-up</th></tr></thead>
        <tbody>
        @forelse ($leads as $lead)
            <tr>
                <td><a href="{{ route('admin.crm.leads.show', $lead) }}"><strong>{{ $lead->title }}</strong></a><div class="muted small">{{ $lead->organization?->name ?? $lead->contact_name }}</div></td>
                <td class="small">{{ $lead->source->label() }}</td>
                <td><span class="status {{ $lead->stage->tone() }}">{{ $lead->stage->label() }}</span></td>
                <td class="small">{{ $lead->assignee?->name ?? 'Shared queue' }}</td>
                <td class="tnum">{{ $lead->estimated_value_paisa ? Taka::format($lead->estimated_value_paisa) : '—' }}</td>
                <td class="small">{{ $lead->next_follow_up_at?->timezone('Asia/Dhaka')->format('d M H:i') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No leads.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
    <div style="margin-bottom:16px">{{ $leads->links() }}</div>

    <form method="POST" action="{{ route('admin.crm.leads.store') }}" class="card card-pad" novalidate>
        @csrf
        <h2 style="font-size:var(--bh-fs-lg)">New lead (phone call, walk-in, referral)</h2>
        <div class="form-grid">
            <div class="field full"><label for="title">Title</label><input class="input" id="title" name="title" value="{{ old('title') }}" required placeholder="e.g. Hotel kitchen fit-out enquiry"></div>
            <div class="field"><label for="source">Source</label><select class="input" id="source" name="source">@foreach ($sources as $source)<option value="{{ $source->value }}">{{ $source->label() }}</option>@endforeach</select></div>
            <div class="field"><label for="organization_id">Existing organization (optional)</label>
                <select class="input" id="organization_id" name="organization_id"><option value="">—</option>@foreach ($organizations as $organization)<option value="{{ $organization->id }}">{{ $organization->name }}</option>@endforeach</select></div>
            <div class="field"><label for="contact_name">Contact name</label><input class="input" id="contact_name" name="contact_name" value="{{ old('contact_name') }}"></div>
            <div class="field"><label for="contact_phone">Mobile</label><input class="input" id="contact_phone" name="contact_phone" value="{{ old('contact_phone') }}" placeholder="01XXXXXXXXX"></div>
            <div class="field"><label for="contact_email">Email</label><input class="input" id="contact_email" name="contact_email" type="email" value="{{ old('contact_email') }}"></div>
            <div class="field"><label for="estimated_value">Estimated value ৳</label><input class="input tnum" id="estimated_value" name="estimated_value" value="{{ old('estimated_value') }}" inputmode="decimal"></div>
            <div class="field"><label class="row" style="gap:6px;margin-top:28px"><input type="checkbox" name="assign_to_me" value="1"> Assign to me (otherwise it goes to the shared queue)</label></div>
        </div>
        <button class="btn btn-primary" type="submit">Create lead</button>
    </form>
@endsection
