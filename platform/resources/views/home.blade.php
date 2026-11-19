@extends('layouts.app')

@section('content')
    <section class="hero">
        <div class="hero-grid">
            <div>
                <h1>Complete Supply Solution for Bakery, Restaurant, <em>Café &amp; Food Businesses</em> in Bangladesh</h1>
                <p style="color:#dbe7ef">Platform core (Phase 2). The full storefront arrives in Phase 3 — see the interactive prototype for the approved design.</p>
                <div class="row">
                    <a class="btn btn-primary" href="{{ route('register') }}">Create an account</a>
                    <a class="btn btn-outline" href="{{ route('login') }}">Login</a>
                </div>
            </div>
        </div>
    </section>
@endsection
