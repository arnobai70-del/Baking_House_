@extends('layouts.app')

@section('content')
    <div class="card card-pad" style="max-width:520px;margin:0 auto;border-top:6px solid var(--bh-orange)">
        <span class="status warn">TEST MODE — simulated {{ $payment->provider->label() }}</span>
        <h1 style="font-size:var(--bh-fs-xl);margin-top:10px">Pay {{ \App\Support\Taka::format($payment->amount_paisa) }}</h1>
        <p class="muted small">Order {{ $payment->order->number }} · payment reference {{ $payment->reference }}</p>
        <div class="alert alert-info" style="margin:12px 0"><x-icon name="info" /><span>This page simulates the provider for development. It is not connected to bKash, Nagad or any card network, asks for no card or wallet details, and moves no real money. The outcome is sent to the store as a signed, server-verified callback.</span></div>
        @if ($payment->status->value !== 'initiated')
            <p><strong>This payment is already {{ $payment->status->value }}.</strong></p>
            <a class="btn btn-outline" href="{{ route('orders.show', $payment->order) }}">Back to order</a>
        @else
            <div class="row">
                @foreach (['success' => ['Approve payment', 'btn-primary'], 'fail' => ['Decline', 'btn-outline'], 'cancel' => ['Cancel', 'btn-ghost']] as $outcome => [$label, $class])
                    <form method="POST" action="{{ URL::temporarySignedRoute('payments.test.complete', now()->addMinutes(30), ['reference' => $payment->reference]) }}">
                        @csrf
                        <input type="hidden" name="outcome" value="{{ $outcome }}">
                        <button class="btn {{ $class }}" type="submit">{{ $label }}</button>
                    </form>
                @endforeach
            </div>
        @endif
    </div>
@endsection
