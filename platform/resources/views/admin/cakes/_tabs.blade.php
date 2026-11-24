<nav class="row" aria-label="Custom cake sections" style="gap:6px;flex-wrap:wrap;margin-bottom:16px">
    @can('prices.manage')
        @foreach (['admin.cakes.settings' => 'Settings', 'admin.cakes.options' => 'Options & prices', 'admin.cakes.products' => 'Cake products', 'admin.cakes.library' => 'Design library', 'admin.cakes.simulator' => 'Price simulator'] as $route => $label)
            <a class="btn btn-sm {{ request()->routeIs($route.'*') ? 'btn-navy' : 'btn-outline' }}" href="{{ route($route) }}" @if (request()->routeIs($route.'*')) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    @endcan
    @can('orders.manage')
        @foreach (['admin.cakes.calendar' => 'Production calendar', 'admin.cakes.pickup' => 'Pickup locations'] as $route => $label)
            <a class="btn btn-sm {{ request()->routeIs($route.'*') ? 'btn-navy' : 'btn-outline' }}" href="{{ route($route) }}" @if (request()->routeIs($route.'*')) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    @endcan
</nav>
