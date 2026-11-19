<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetStorefrontLocale;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LanguageController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate(['locale' => ['required', Rule::in(Setting::enabledLanguages())]]);
        $request->session()->put(SetStorefrontLocale::SESSION_KEY, $validated['locale']);

        return back();
    }
}
