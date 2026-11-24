@extends('layouts.admin', ['title' => 'Cake production calendar'])

@use('App\Models\CakeBlackoutDate')

@section('admin')
    <div class="admin-top"><h1>Cake production calendar</h1></div>
    @include('admin.cakes._tabs')

    @if (! $ordersOpen)
        <div class="alert alert-info" style="margin-bottom:16px"><x-icon name="info" /><span>Custom cake orders are closed{{ $capacity === null ? ' (daily capacity is not set)' : '' }}. Change this in <a href="{{ route('admin.cakes.settings') }}">Settings</a> (needs price permissions).</span></div>
    @endif

    <form method="POST" action="{{ route('admin.cakes.dates.store') }}" class="card card-pad" style="margin-bottom:16px" novalidate>
        @csrf
        <h2 style="font-size:var(--bh-fs-md)">Change one date</h2>
        <div class="form-grid">
            <div class="field"><label for="date">Date</label><input class="input" id="date" name="date" type="date" value="{{ old('date') }}" required>@error('date')<span class="field-error">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="kind">Rule</label><select class="input" id="kind" name="kind">
                <option value="{{ CakeBlackoutDate::CLOSED }}">Closed (holiday / no production)</option>
                <option value="{{ CakeBlackoutDate::URGENT_OPEN }}">Accept urgent orders (ignore lead time)</option></select></div>
            <div class="field full"><label for="reason">Reason (internal)</label><input class="input" id="reason" name="reason" value="{{ old('reason') }}" placeholder="e.g. Eid holiday"></div>
        </div>
        <button class="btn btn-navy" type="submit">Save date</button>
    </form>

    <div class="card"><div class="table-wrap"><table class="table">
        <thead><tr><th>Date</th><th>Cakes booked</th><th>Capacity left</th><th>Rule</th><th></th></tr></thead>
        <tbody>
        @foreach ($days as $day)
            @php $exception = $day['exception']; $left = $capacity === null ? null : max(0, $capacity - $day['booked']); @endphp
            <tr>
                <td>{{ $day['date']->format('D j M') }}</td>
                <td class="tnum">{{ $day['booked'] }}</td>
                <td class="tnum">@if ($exception?->kind === CakeBlackoutDate::CLOSED)—@elseif ($left === null)not set@else<span class="status {{ $left === 0 ? 'warn' : 'ok' }}">{{ $left }}</span>@endif</td>
                <td>@if ($exception)<span class="status {{ $exception->kind === CakeBlackoutDate::CLOSED ? 'warn' : 'info' }}">{{ $exception->kind === CakeBlackoutDate::CLOSED ? 'Closed' : 'Urgent orders' }}</span> <span class="small muted">{{ $exception->reason }}</span>@else<span class="small muted">Normal</span>@endif</td>
                <td>@if ($exception)
                    <form method="POST" action="{{ route('admin.cakes.dates.destroy', $exception) }}">@csrf @method('DELETE')<button class="btn btn-outline btn-sm">Back to normal</button></form>
                @endif</td>
            </tr>
        @endforeach
        </tbody>
    </table></div></div>
@endsection
