@if ($subject instanceof \App\Models\CrmLead)
    <a href="{{ route('admin.crm.leads.show', $subject) }}">Lead: {{ $subject->title }}</a>
@elseif ($subject instanceof \App\Models\Organization)
    <a href="{{ route('admin.crm.organizations.show', $subject) }}">Organization: {{ $subject->name }}</a>
@elseif ($subject instanceof \App\Models\User)
    <a href="{{ route('admin.crm.customers.show', $subject) }}">Customer: {{ $subject->name }}</a>
@else
    —
@endif
