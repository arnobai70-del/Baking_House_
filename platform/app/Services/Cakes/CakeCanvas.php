<?php

namespace App\Services\Cakes;

use App\Models\CakeUpload;
use Illuminate\Support\Str;

/**
 * The hand-made top design: a list of layers on a square that represents the cake top (x / y / width are
 * fractions of the top's width). Everything the browser sends is rebuilt here from known fields with clamped
 * numbers, so only text, library items and the customer's own uploads can appear, and nothing else is stored.
 *
 * Layer: {id, type: text|image|item, x, y, w, rotation, opacity}
 *   text:  text (≤ 60), font (library code), size, fill, stroke, stroke_width, curve (-1 … 1)
 *   image: upload (uuid), crop {x, y, w, h}, mask none|circle, brightness (-0.5 … 0.5)
 *   item:  item (library code), kind topper|decoration|sticker_pack, sticker (index in a pack)
 */
class CakeCanvas
{
    public const MAX_LAYERS = 30;

    public const MAX_IMAGES = 6;

    private const HEX = '/^#[0-9a-f]{6}$/i';

    /**
     * @param  array<int, mixed>  $layers
     * @param  (callable(string): ?CakeUpload)|null  $uploadFor  resolves an upload the current visitor owns, or null
     * @return list<array<string, mixed>>
     */
    public function sanitize(array $layers, ?callable $uploadFor = null): array
    {
        $clean = [];
        $images = 0;
        foreach (array_slice(array_values($layers), 0, self::MAX_LAYERS) as $layer) {
            if (! is_array($layer)) {
                continue;
            }
            $base = [
                'id' => preg_match('/^[a-z0-9]{1,20}$/i', (string) ($layer['id'] ?? '')) ? (string) $layer['id'] : Str::lower(Str::random(8)),
                'type' => (string) ($layer['type'] ?? ''),
                'x' => $this->num($layer['x'] ?? 0.5, -0.2, 1.2), 'y' => $this->num($layer['y'] ?? 0.5, -0.2, 1.2),
                'w' => $this->num($layer['w'] ?? 0.3, 0.03, 1.5), 'rotation' => $this->num($layer['rotation'] ?? 0, -360, 360),
                'opacity' => $this->num($layer['opacity'] ?? 1, 0.1, 1),
            ];

            $typed = match ($base['type']) {
                'text' => $this->text($layer),
                'item' => $this->item($layer),
                'image' => $uploadFor !== null && $images < self::MAX_IMAGES ? $this->image($layer, $uploadFor) : null,
                default => null,
            };
            if ($typed === null) {
                continue;
            }
            $images += $base['type'] === 'image' ? 1 : 0;
            $clean[] = $base + $typed;
        }

        return $clean;
    }

    /**
     * Templates may hold text and library items, never photos.
     *
     * @param  array<int, mixed>  $layers
     * @return list<array<string, mixed>>
     */
    public function sanitizeTemplate(array $layers): array
    {
        return $this->sanitize($layers);
    }

    /**
     * Print quality of every photo layer on a top of the given width (plain arithmetic, see CakeGeometry).
     *
     * @param  list<array<string, mixed>>  $layers
     * @param  array<string, CakeUpload>  $uploads  keyed by uuid
     * @return list<array{upload: string, dpi: int, level: string, print_cm: float}>
     */
    public function printQuality(array $layers, array $uploads, float $topDiameterCm): array
    {
        $result = [];
        foreach ($layers as $layer) {
            $upload = ($layer['type'] ?? '') === 'image' ? ($uploads[$layer['upload']] ?? null) : null;
            if ($upload === null) {
                continue;
            }
            $printCm = round($layer['w'] * $topDiameterCm, 1);
            $result[] = ['upload' => $upload->uuid, 'print_cm' => $printCm] + CakeGeometry::printQuality($upload->width * $layer['crop']['w'], $printCm);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function text(array $layer): ?array
    {
        $text = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) ($layer['text'] ?? '')) ?? ''), 0, 60);
        if ($text === '') {
            return null;
        }

        return [
            'text' => $text,
            'font' => preg_match('/^[a-z0-9_-]{1,40}$/', (string) ($layer['font'] ?? '')) ? (string) $layer['font'] : '',
            'size' => $this->num($layer['size'] ?? 0.08, 0.02, 0.3),
            'fill' => $this->hex($layer['fill'] ?? null, '#103b56'),
            'stroke' => $this->hex($layer['stroke'] ?? null, ''),
            'stroke_width' => $this->num($layer['stroke_width'] ?? 0, 0, 0.02),
            'curve' => $this->num($layer['curve'] ?? 0, -1, 1),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function item(array $layer): ?array
    {
        $code = (string) ($layer['item'] ?? '');
        $kind = (string) ($layer['kind'] ?? '');
        if (! preg_match('/^[a-z0-9_-]{1,40}$/', $code) || ! in_array($kind, ['topper', 'decoration', 'sticker_pack'], true)) {
            return null;
        }

        return ['item' => $code, 'kind' => $kind, 'sticker' => max(0, min(23, (int) ($layer['sticker'] ?? 0)))];
    }

    /**
     * @param  callable(string): ?CakeUpload  $uploadFor
     * @return array<string, mixed>|null
     */
    private function image(array $layer, callable $uploadFor): ?array
    {
        $uuid = (string) ($layer['upload'] ?? '');
        if (! Str::isUuid($uuid) || $uploadFor($uuid) === null) {
            return null;
        }
        $crop = (array) ($layer['crop'] ?? []);
        $cx = $this->num($crop['x'] ?? 0, 0, 0.95);
        $cy = $this->num($crop['y'] ?? 0, 0, 0.95);

        return [
            'upload' => $uuid,
            'crop' => ['x' => $cx, 'y' => $cy, 'w' => $this->num($crop['w'] ?? 1, 0.05, 1 - $cx), 'h' => $this->num($crop['h'] ?? 1, 0.05, 1 - $cy)],
            'mask' => ($layer['mask'] ?? 'none') === 'circle' ? 'circle' : 'none',
            'brightness' => $this->num($layer['brightness'] ?? 0, -0.5, 0.5),
        ];
    }

    private function num(mixed $value, float $min, float $max): float
    {
        $number = is_numeric($value) ? (float) $value : $min;

        return round(max($min, min($max, $number)), 4);
    }

    private function hex(mixed $value, string $default): string
    {
        return is_string($value) && preg_match(self::HEX, $value) ? strtolower($value) : $default;
    }
}
