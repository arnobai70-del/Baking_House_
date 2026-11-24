<?php

namespace App\Services\Cakes;

use App\Models\CakeLibraryItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The admin-managed design library: lookups for the designer / pricer and strict validation of each kind's
 * drawing data, so only plain vector path data, known style names, hex colours and safe font names are stored.
 */
class CakeLibrary
{
    public const BORDER_STYLES = ['shell', 'bead', 'rope', 'dots', 'ruffle'];

    public const PATTERN_STYLES = ['dots', 'stripes', 'zigzag', 'hearts', 'stars', 'scallop'];

    public const HEX = '/^#[0-9a-f]{6}$/i';

    /** SVG path data: commands, numbers, separators only. */
    private const PATH_DATA = '/^[MmLlHhVvCcSsQqTtAaZz0-9.,\s\-eE]+$/';

    /** @var array<string, Collection<string, CakeLibraryItem>> */
    private array $memo = [];

    /**
     * Active items keyed "kind.code".
     *
     * @return Collection<string, CakeLibraryItem>
     */
    public function active(): Collection
    {
        return $this->memo['active'] ??= CakeLibraryItem::query()->active()->get()->keyBy(fn (CakeLibraryItem $i) => $i->kind.'.'.$i->code);
    }

    public function find(string $kind, ?string $code): ?CakeLibraryItem
    {
        return $code === null || $code === '' ? null : $this->active()->get($kind.'.'.$code);
    }

    /**
     * Library for the designer, grouped by kind.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function forClient(): array
    {
        return $this->active()->groupBy('kind')->map(fn (Collection $items) => $items->map->toClient()->values()->all())->all();
    }

    public function forget(): void
    {
        $this->memo = [];
    }

    /**
     * Validate and normalise an item's payload for its kind.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function normalisePayload(string $kind, array $payload): array
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['payload' => $message]);

        return match ($kind) {
            'topper', 'decoration' => $this->shape($payload, $fail),
            'sticker_pack' => ['stickers' => collect((array) ($payload['stickers'] ?? []))->take(24)->map(fn ($sticker) => ['name' => mb_substr((string) ($sticker['name'] ?? 'Sticker'), 0, 40)] + $this->shape((array) $sticker, $fail))->values()->all()
                ?: $fail('A sticker pack needs at least one sticker.')],
            'border' => [
                'style' => in_array($payload['style'] ?? null, self::BORDER_STYLES, true) ? $payload['style'] : $fail('Border style must be one of: '.implode(', ', self::BORDER_STYLES).'.'),
                'colour' => $this->colourOrAccent($payload['colour'] ?? 'accent', $fail),
            ],
            'pattern' => [
                'style' => in_array($payload['style'] ?? null, self::PATTERN_STYLES, true) ? $payload['style'] : $fail('Pattern style must be one of: '.implode(', ', self::PATTERN_STYLES).'.'),
                'colour' => $this->colourOrAccent($payload['colour'] ?? 'accent', $fail),
            ],
            'font' => [
                'family' => preg_match('/^[A-Za-z0-9 \-]{2,40}$/', (string) ($payload['family'] ?? '')) ? (string) $payload['family'] : $fail('Font family: letters, numbers, spaces and hyphens only.'),
                'stack' => preg_match('/^[A-Za-z0-9 ,"\-]{2,160}$/', (string) ($payload['stack'] ?? '')) ? (string) $payload['stack'] : $fail('Font stack: font names separated by commas.'),
                'weight' => in_array((int) ($payload['weight'] ?? 400), [400, 500, 600, 700], true) ? (int) ($payload['weight'] ?? 400) : 400,
                'scripts' => array_values(array_intersect((array) ($payload['scripts'] ?? ['en']), ['en', 'bn'])) ?: ['en'],
            ],
            'palette' => (function () use ($payload, $fail): array {
                $colours = array_values(array_unique(array_map('strtolower', (array) ($payload['colours'] ?? []))));
                if (count($colours) < 2 || count($colours) > 8 || collect($colours)->contains(fn ($c) => ! preg_match(self::HEX, (string) $c))) {
                    $fail('A palette needs 2–8 colours written like #f4c2c2.');
                }

                return ['colours' => $colours];
            })(),
            'template' => ['layers' => app(CakeCanvas::class)->sanitizeTemplate((array) ($payload['layers'] ?? []))
                ?: $fail('A template needs at least one text or library layer.')],
            default => $fail('Unknown library kind.'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{viewBox: string, paths: list<array{d: string, fill: string}>}
     */
    private function shape(array $payload, callable $fail): array
    {
        $viewBox = (string) ($payload['viewBox'] ?? '0 0 100 100');
        if (! preg_match('/^-?\d+(\.\d+)? -?\d+(\.\d+)? \d+(\.\d+)? \d+(\.\d+)?$/', $viewBox)) {
            $fail('viewBox must be four numbers, like "0 0 100 100".');
        }
        $paths = collect((array) ($payload['paths'] ?? []))->take(12)->map(function ($path) use ($fail) {
            $d = trim((string) ($path['d'] ?? ''));
            $fill = strtolower((string) ($path['fill'] ?? '#103b56'));
            if ($d === '' || strlen($d) > 4000 || ! preg_match(self::PATH_DATA, $d)) {
                $fail('Each path needs plain SVG path data (M, L, C, Q, A, Z and numbers).');
            }
            if (! preg_match(self::HEX, $fill) && $fill !== 'accent' && $fill !== 'base') {
                $fail('Path colours must be #hex, "accent" or "base".');
            }

            return ['d' => $d, 'fill' => $fill];
        })->values()->all();

        return ['viewBox' => $viewBox, 'paths' => $paths ?: $fail('Add at least one path.')];
    }

    private function colourOrAccent(mixed $colour, callable $fail): string
    {
        $colour = strtolower((string) $colour);

        return $colour === 'accent' || preg_match(self::HEX, $colour) ? $colour : $fail('Colour must be "accent" or #hex.');
    }
}
