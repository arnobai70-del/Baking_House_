@extends('portal.layout', ['heading' => __('Orders'), 'subheading' => __('Every order placed for :organization, by any team member.', ['organization' => $organization->name])])

@section('portal')
    <div class="card">
        <div class="panel-title">
            <h2>{{ __('Organization orders') }}</h2>
            <form method="GET" class="row" style="gap:6px">
                <label class="sr-only" for="status">{{ __('Status') }}</label>
                <select class="input" id="status" name="status" style="min-height:36px">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (\App\Enums\OrderStatus::cases() as $case)
                        <option value="{{ $case->value }}" @selected($status === $case)>{{ __($case->label()) }}</option>
                    @endforeach
                </select>
                <button class="btn btn-outline btn-sm" type="submit">{{ __('Filter') }}</button>
            </form>
        </div>
        @include('portal._orders-table', ['orders' => $orders])
    </div>
    <div style="margin-top:12px">{{ $orders->links() }}</div>
    <p class="hint">{{ __('Reorder adds the same items to your cart at today\'s prices and stock; nothing is ordered until you check out.') }}</p>
@endsection
