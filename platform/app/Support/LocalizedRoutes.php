<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Language-prefixed public URLs for search engines (decision D-059).
 *
 * English pages keep their URLs (/shop); Bangla pages live under /bn (/bn/shop). Only public,
 * indexable storefront pages are localized; account, cart, checkout, portals and admin keep the
 * visitor's session language. The URL generator adds /bn automatically while the page language is
 * Bangla, so links, canonicals and pagination stay in the visitor's language.
 */
class LocalizedRoutes
{
    public const PREFIX = 'bn';

    /** Exact paths (no leading slash) that exist in both languages. */
    private const EXACT = ['', 'shop', 'search', 'combos', 'custom-cake', 'business', 'reseller', 'contact', 'order/track'];

    /** Path prefixes whose sub-pages exist in both languages. */
    private const PREFIXES = ['category/', 'product/', 'combo/', 'page/'];

    public static function matches(string $path): bool
    {
        $path = trim($path, '/');

        if (in_array($path, self::EXACT, true)) {
            return true;
        }
        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix) && strlen($path) > strlen($prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function banglaEnabled(): bool
    {
        return in_array(self::PREFIX, Setting::enabledLanguages(), true);
    }

    /**
     * Absolute URLs of the current page in each enabled language, or null when the page is not
     * localized or only one language is enabled.
     *
     * @return array{en: string, bn: string}|null
     */
    public static function alternatesFor(Request $request): ?array
    {
        $path = trim((string) $request->attributes->get('localized_path', ''), '/');
        if (! $request->attributes->has('url_locale') || ! self::banglaEnabled() || ! self::matches($path)) {
            return null;
        }

        $root = rtrim(URL::formatRoot(URL::formatScheme()), '/');
        $query = $request->getQueryString();
        $suffix = ($path === '' ? '' : '/'.$path).($query ? '?'.$query : '');

        return [
            'en' => $root.($suffix === '' ? '/' : $suffix),
            'bn' => $root.'/'.self::PREFIX.$suffix,
        ];
    }

    /**
     * Bangla twin of an absolute English URL produced by route() / url() (used by the sitemap).
     */
    public static function banglaUrl(string $englishUrl): string
    {
        $root = rtrim(URL::formatRoot(URL::formatScheme()), '/');
        $path = substr($englishUrl, strlen($root));

        return $root.'/'.self::PREFIX.($path === '' || $path === '/' ? '' : $path);
    }
}
