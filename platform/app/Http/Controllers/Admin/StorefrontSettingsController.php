<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Storefront language settings: which of English / Bangla are offered and which is the default.
 */
class StorefrontSettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.storefront', [
            'enabled' => Setting::enabledLanguages(),
            'default' => Setting::defaultLanguage(),
            'languages' => Setting::SUPPORTED_LANGUAGES,
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $supported = array_keys(Setting::SUPPORTED_LANGUAGES);
        $validated = $request->validate([
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => [Rule::in($supported)],
            'default' => ['required', Rule::in($supported)],
        ]);

        $enabled = array_values(array_intersect($supported, $validated['languages']));
        $default = in_array($validated['default'], $enabled, true) ? $validated['default'] : $enabled[0];
        $before = ['languages' => Setting::enabledLanguages(), 'default' => Setting::defaultLanguage()];

        Setting::put(Setting::LANGUAGES_ENABLED, $enabled);
        Setting::put(Setting::LANGUAGE_DEFAULT, $default);
        $audit->record('settings.languages_updated', null, $before, ['languages' => $enabled, 'default' => $default]);

        return back()->with('status', 'Language settings saved.');
    }
}
