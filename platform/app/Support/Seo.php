<?php

namespace App\Support;

/**
 * Per-page SEO data rendered by the storefront layout: title, description, canonical URL,
 * robots directive and JSON-LD blocks. Indexing is globally gated by config('seo.indexing_enabled').
 */
class Seo
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $jsonLd = [];

    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $canonical = null,
        public bool $indexable = true,
        public string $ogType = 'website',
    ) {}

    public function fullTitle(): string
    {
        $site = (string) config('seo.site_name');

        return $this->title === $site ? $site : "{$this->title} — {$site}";
    }

    public function metaDescription(): string
    {
        return mb_strimwidth($this->description ?: __((string) config('seo.default_description')), 0, 160, '…');
    }

    public function robots(): string
    {
        return config('seo.indexing_enabled') && $this->indexable ? 'index, follow' : 'noindex, nofollow';
    }

    public function noindex(): static
    {
        $this->indexable = false;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addJsonLd(array $data): static
    {
        $this->jsonLd[] = ['@context' => 'https://schema.org'] + $data;

        return $this;
    }

    /**
     * @param  list<array{name: string, url: string}>  $crumbs
     */
    public function breadcrumbs(array $crumbs): static
    {
        return $this->addJsonLd([
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $crumb, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ], $crumbs, array_keys($crumbs)),
        ]);
    }

    /**
     * @return list<string> JSON strings safe to print inside <script type="application/ld+json">
     */
    public function jsonLdScripts(): array
    {
        return array_map(
            fn (array $block) => json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP),
            $this->jsonLd,
        );
    }
}
