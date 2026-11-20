<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Owner-supplied information page (about, contact information, delivery / returns / privacy /
 * terms). Body is Markdown with raw HTML stripped. Only published pages are public.
 */
#[Fillable(['slug', 'title', 'title_bn', 'body', 'body_bn', 'seo_title', 'seo_description'])]
class Page extends Model
{
    use HasTranslations;

    /**
     * Pages the footer links to once published (slug => English label). Until then the footer says "pending".
     */
    public const STANDARD = [
        'about-us' => 'About Baking House',
        'contact-information' => 'Contact information',
        'delivery-policy' => 'Delivery policy',
        'returns-policy' => 'Returns & refunds policy',
        'privacy-policy' => 'Privacy policy',
        'terms' => 'Terms & conditions',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'published_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function url(): string
    {
        return route('pages.show', $this->slug);
    }

    /**
     * Safe HTML for the current language: Markdown, raw HTML stripped, unsafe links removed.
     */
    public function html(): string
    {
        return Str::markdown((string) $this->localized('body'), ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    /**
     * Published standard pages keyed by slug, memoised per request (footer on every page).
     *
     * @return array<string, Page>
     */
    public static function publishedStandard(): array
    {
        return once(fn () => self::query()->where('is_published', true)->whereIn('slug', array_keys(self::STANDARD))
            ->get(['id', 'slug', 'title', 'title_bn'])->keyBy('slug')->all());
    }
}
