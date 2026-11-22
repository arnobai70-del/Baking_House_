<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Everyone starts as a retail customer. Business / reseller access is applied for separately
     * and granted only after staff review.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create($request->safe()->only(['name', 'phone', 'email', 'password']));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('account'))->with('status', __('Welcome to :brand. Your account is ready.', ['brand' => config('brand.name')]));
    }
}
