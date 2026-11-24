<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One design-library item for the cake designer (admin-managed). Drawing data lives in `payload` and is
 * made from our own simple vector shapes only (no copyrighted characters or logos). The price add-on is
 * charged once per design when the item is used; `is_test` prices block live checkout until approved.
 *
 * payload by kind:
 *   topper / decoration: {viewBox, paths: [{d, fill}]}
 *   sticker_pack:        {stickers: [{name, viewBox, paths: [{d, fill}]}]}
 *   border:              {style: shell|bead|rope|dots|ruffle, colour: "accent"|#hex}
 *   pattern:             {style: dots|stripes|zigzag|hearts|stars|scallop, colour: "accent"|#hex}
 *   font:                {family, stack, weight, scripts: [en, bn]}
 *   palette:             {colours: [#hex, …]}
 *   template:            {layers: [canvas layers]}
 */
#[Fillable(['kind', 'code', 'name', 'name_bn', 'tags', 'price_paisa', 'is_test', 'complexity', 'allowed_shapes', 'payload', 'is_active', 'sort_order'])]
class CakeLibraryItem extends Model
{
    use HasTranslations;

    public const KINDS = [
        'template' => 'Template',
        'topper' => 'Topper',
        'decoration' => 'Decoration',
        'sticker_pack' => 'Theme sticker pack',
        'border' => 'Border piping',
        'pattern' => 'Side pattern',
        'font' => 'Font',
        'palette' => 'Colour palette',
    ];

    /** Kinds that are placed on the top design as canvas layers. */
    public const PLACEABLE = ['topper', 'decoration', 'sticker_pack'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array', 'price_paisa' => 'integer', 'is_test' => 'boolean', 'allowed_shapes' => 'array',
            'payload' => 'array', 'is_active' => 'boolean', 'sort_order' => 'integer',
        ];
    }

    protected function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function allowsShape(string $shape): bool
    {
        return empty($this->allowed_shapes) || in_array($shape, $this->allowed_shapes, true);
    }

    /**
     * Customer-facing data for the designer (no internal fields).
     *
     * @return array<string, mixed>
     */
    public function toClient(): array
    {
        return [
            'kind' => $this->kind, 'code' => $this->code, 'name' => $this->localized('name'), 'name_en' => $this->name, 'name_bn' => $this->name_bn,
            'tags' => array_values((array) $this->tags), 'price_paisa' => $this->price_paisa, 'shapes' => $this->allowed_shapes,
            'payload' => $this->payload, 'test' => $this->is_test,
        ];
    }
}
