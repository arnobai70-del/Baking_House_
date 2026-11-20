<div class="row" style="margin-bottom:16px;gap:6px">
    @foreach (['admin.crm.dashboard' => 'Overview', 'admin.crm.leads.index' => 'Leads & pipeline', 'admin.crm.customers.index' => 'Customers & segments', 'admin.crm.tasks.index' => 'Tasks'] as $routeName => $label)
        <a class="fchip" href="{{ route($routeName) }}" @if (request()->routeIs($routeName)) style="background:var(--bh-navy);color:#fff" aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</div>
