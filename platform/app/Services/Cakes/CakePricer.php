<?php

namespace App\Services\Cakes;

use App\Models\CakeDesign;
use App\Models\CakeLibraryItem;
use App\Models\CakeOption;
use App\Models\CakeProduct;
use App\Models\ProductVariant;
use App\Support\Taka;
use App\Support\Weight;
use Illuminate\Support\Collection;

/**
 * Server-side price of a custom cake design — the only place a cake price is calculated. The base follows the
 * existing weight engine's rule (round_half_up(grams × per-kg ÷ 1000)), every surcharge is a visible line, and
 * nothing the browser sends is trusted except the choices themselves, which are validated against the
 * admin-managed options. Delivery is added later by checkout from the delivery-zone rules.
 *
 * Returns a cart/quote line: ok, error, line_paisa, breakdown[], complexity, requires_quote, uses_test_prices.
 */
class CakePricer
{
    private const COMPLEXITY_RANK = ['simple' => 0, 'standard' => 1, 'complex' => 2];

    /** Cream colours that never cost extra (plain cream white). */
    public const FREE_COLOURS = ['#fffaf3', '#ffffff'];

    public function __construct(private CakeSettings $settings, private CakeSchedule $schedule, private CakeLibrary $library) {}

    /**
     * @return array<string, mixed>
     */
    public function line(CakeDesign $design): array
    {
        $variant = $design->variant()->with('product.cakeProfile')->first();
        $priced = $this->price($variant, (array) $design->spec, $design->scheduled_for?->format('Y-m-d'), $design->time_slot);

        $base = [
            'type' => 'cake',
            'uuid' => $design->uuid,
            'cake_design_id' => $design->id,
            'product_variant_id' => $design->product_variant_id,
            'product_id' => $variant?->product_id,
            'sku' => $variant?->sku,
            'name' => $variant?->product?->name ?? __('Custom cake'),
            'quantity' => 1,
            'quantity_label' => trim('1 cake · '.Weight::format((int) ($design->spec['grams'] ?? 0))),
            'fulfilment' => $design->fulfilment,
            'scheduled_for' => $design->scheduled_for?->format('Y-m-d'),
            'time_slot' => $design->time_slot,
            'pickup_location_id' => $design->pickup_location_id,
            'has_snapshot' => $design->snapshot_path !== null,
            'items' => [], // no stock lines: cakes are made to order
        ];

        if ($priced['ok'] && $design->scheduled_for !== null) {
            $error = $this->schedule->check($variant->product->cakeProfile, $priced['complexity'], $design->scheduled_for->format('Y-m-d'), $design->time_slot);
            if ($error !== null) {
                $priced['ok'] = false;
                $priced['error'] = $error;
            }
        } elseif ($priced['ok']) {
            $priced['ok'] = false;
            $priced['error'] = __('Choose a delivery or pickup date for this cake.');
        }

        return $base + $priced;
    }

    /**
     * Price a specification for a cake product variant.
     *
     * @param  array<string, mixed>  $spec
     * @return array{ok: bool, error: ?string, line_paisa: int, standard_line_paisa: int, breakdown: list<array{label: string, paisa: int}>, complexity: string, requires_quote: bool, uses_test_prices: bool}
     */
    public function price(?ProductVariant $variant, array $spec, ?string $date = null, ?string $slot = null): array
    {
        $fail = fn (string $error, array $extra = []) => $extra + [
            'ok' => false, 'error' => $error, 'line_paisa' => 0, 'standard_line_paisa' => 0, 'breakdown' => [],
            'complexity' => 'standard', 'requires_quote' => false, 'uses_test_prices' => false,
        ];

        $cake = $variant?->product?->cakeProfile;
        if ($cake === null || ! $variant->is_active) {
            return $fail(__('This cake is not available.'));
        }

        $options = $this->optionsFor($cake);
        $grams = (int) ($spec['grams'] ?? 0);
        $tiers = max(1, (int) ($spec['tiers'] ?? 1));

        if (! in_array($grams, $cake->weightChoices(), true)) {
            return $fail(__('Choose a weight between :min and :max.', ['min' => Weight::format($cake->min_grams), 'max' => Weight::format($cake->max_grams)]));
        }
        if ($tiers > $cake->max_tiers) {
            return $fail(__('This cake can have at most :n tiers.', ['n' => $cake->max_tiers]));
        }

        $chosen = [];
        $flavour = $options->get('flavour.'.($spec['flavour'] ?? ''));
        if ($flavour === null) {
            return $fail(__('Choose a flavour.'));
        }
        $chosen[] = $flavour;

        $shape = $options->get('shape.'.($spec['shape'] ?? ''));
        if ($shape === null) {
            return $fail(__('Choose a shape.'));
        }
        $chosen[] = $shape;

        if ($tiers > 1) {
            $tier = $options->get('tier.'.$tiers);
            if ($tier === null) {
                return $fail(__(':n tiers are not offered for this cake.', ['n' => $tiers]));
            }
            $chosen[] = $tier;
        }

        foreach (['filling', 'cream'] as $group) {
            if (($spec[$group] ?? '') !== '') {
                $option = $options->get($group.'.'.$spec[$group]);
                if ($option === null) {
                    return $fail(__('That :group is not available.', ['group' => __($group)]));
                }
                $chosen[] = $option;
            }
        }

        $diets = array_values(array_unique(array_filter((array) ($spec['diets'] ?? []))));
        if (count($diets) > 1 && ! $cake->allow_both_diets) {
            return $fail(__('Eggless and sugar-free cannot be combined for this cake.'));
        }
        foreach ($diets as $diet) {
            $allowed = ($diet === 'eggless' && $cake->allow_eggless) || ($diet === 'sugar_free' && $cake->allow_sugar_free);
            $option = $options->get('diet.'.$diet);
            if (! $allowed || $option === null) {
                return $fail(__('That dietary option is not available for this cake.'));
            }
            $chosen[] = $option;
        }

        foreach (array_unique((array) ($spec['extras'] ?? [])) as $code) {
            $extra = $options->get('extra.'.$code);
            if ($extra === null) {
                return $fail(__('That extra is not available.'));
            }
            $chosen[] = $extra;
        }

        // Part 2: colours, drip, side pattern, borders, template and the hand-made top design.
        $style = $this->style($spec, $options, (string) $spec['shape']);
        if (is_string($style)) {
            return $fail($style);
        }
        [$styleOptions, $libraryItems] = $style;
        array_push($chosen, ...$styleOptions);

        $layers = (array) ($spec['canvas']['layers'] ?? []);
        $hasText = collect($layers)->contains(fn ($layer) => ($layer['type'] ?? '') === 'text') || trim((string) ($spec['message'] ?? '')) !== '';
        $hasPhoto = collect($layers)->contains(fn ($layer) => ($layer['type'] ?? '') === 'image') || ! empty($spec['print']);
        if ($hasText && ($message = $options->get('message.standard')) !== null) {
            $chosen[] = $message;
        }
        if ($hasPhoto) {
            $print = $options->get('print.standard');
            if ($print === null) {
                return $fail(__('Edible prints are not available for this cake.'));
            }
            $chosen[] = $print;
        }

        $breakdown = [];
        $total = 0;
        foreach ($chosen as $option) {
            $paisa = $this->optionPaisa($option, $grams, $tiers);
            $label = $option->group === 'flavour'
                ? __(':flavour · :weight at :rate/kg', ['flavour' => $option->localized('name'), 'weight' => Weight::format($grams), 'rate' => Taka::format($option->price_paisa)])
                : $option->localized('name');
            $breakdown[] = ['label' => $label, 'paisa' => $paisa];
            $total += $paisa;
        }
        foreach ($libraryItems as $item) {
            if ($item->price_paisa > 0) {
                $breakdown[] = ['label' => $item->localized('name'), 'paisa' => $item->price_paisa];
                $total += $item->price_paisa;
            }
        }

        $priced = [...$chosen, ...$libraryItems];
        $complexity = $this->complexity($priced, $tiers);
        $requiresQuote = collect($chosen)->contains(fn (CakeOption $option) => $option->requires_quote);
        $usesTest = collect($priced)->contains(fn (CakeOption|CakeLibraryItem $item) => $item->is_test);

        if ($date !== null) {
            $rush = $this->settings->rushFeeFor($this->schedule->hoursUntil($date, $slot));
            if ($rush > 0) {
                $breakdown[] = ['label' => __('Rush order fee'), 'paisa' => $rush];
                $total += $rush;
            }
        }

        $result = [
            'ok' => true, 'error' => null, 'line_paisa' => $total, 'standard_line_paisa' => $total,
            'breakdown' => $breakdown, 'complexity' => $complexity, 'requires_quote' => $requiresQuote, 'uses_test_prices' => $usesTest,
        ];

        if ($requiresQuote) {
            return ['ok' => false, 'error' => __('This design needs a quotation from our bakery team. Request a quote instead.')] + $result;
        }
        if ($usesTest && ! app()->environment(['local', 'testing'])) {
            return ['ok' => false, 'error' => __('Prices for this cake have not been approved yet, so it cannot be ordered.')] + $result;
        }

        return $result;
    }

    /**
     * Active options for this cake, keyed "group.code"; product-specific options override global ones.
     *
     * @return Collection<string, CakeOption>
     */
    public function optionsFor(CakeProduct $cake): Collection
    {
        return CakeOption::query()->forCake($cake)->get()
            ->sortBy(fn (CakeOption $option) => $option->cake_product_id === null ? 0 : 1)
            ->keyBy(fn (CakeOption $option) => $option->group.'.'.$option->code);
    }

    private function optionPaisa(CakeOption $option, int $grams, int $tiers): int
    {
        return match ($option->price_type) {
            'per_kg' => Taka::divideRoundHalfUp($grams * $option->price_paisa, 1000),
            'per_tier' => $option->price_paisa * max(0, $tiers - 1),
            default => $option->price_paisa,
        };
    }

    /**
     * Colours, drip, pattern, borders, template and every library item / font used on the top. Each library item's
     * add-on is charged once per design. Returns an error message, or [options, library items].
     *
     * @param  array<string, mixed>  $spec
     * @param  Collection<string, CakeOption>  $options
     * @return string|array{0: list<CakeOption>, 1: list<CakeLibraryItem>}
     */
    private function style(array $spec, Collection $options, string $shape): string|array
    {
        $chosenOptions = [];
        $items = [];
        $use = function (string $kind, ?string $code) use (&$items, $shape): ?string {
            if ($code === null || $code === '') {
                return null;
            }
            $item = $this->library->find($kind, $code);
            if ($item === null) {
                return __('A design item you chose is no longer available. Please choose another.');
            }
            if (! $item->allowsShape($shape)) {
                return __(':item is not available for this cake shape.', ['item' => $item->localized('name')]);
            }
            $items[$kind.'.'.$code] = $item;

            return null;
        };

        foreach ([['palette', 'palette'], ['pattern', 'pattern'], ['border', 'border_top'], ['border', 'border_bottom'], ['template', 'template']] as [$kind, $key]) {
            if (($error = $use($kind, isset($spec[$key]) ? (string) $spec[$key] : null)) !== null) {
                return $error;
            }
        }

        $free = [...self::FREE_COLOURS, ...(array) ($items['palette.'.($spec['palette'] ?? '')]->payload['colours'] ?? [])];
        $custom = false;
        foreach (['base_colour', 'accent_colour'] as $key) {
            $colour = strtolower((string) ($spec[$key] ?? ''));
            if ($colour !== '' && ! in_array($colour, $free, true)) {
                $custom = true;
            }
        }
        if ($custom) {
            $option = $options->get('colour.custom');
            if ($option === null) {
                return __('Custom colours are not available. Please choose colours from a palette.');
            }
            $chosenOptions[] = $option;
        }

        if (($spec['drip'] ?? '') !== '') {
            $drip = $options->get('drip.'.$spec['drip']);
            if ($drip === null) {
                return __('That drip is not available.');
            }
            $chosenOptions[] = $drip;
        }

        foreach ((array) ($spec['canvas']['layers'] ?? []) as $layer) {
            $error = match ($layer['type'] ?? '') {
                'item' => $use((string) ($layer['kind'] ?? ''), (string) ($layer['item'] ?? '')),
                'text' => $use('font', (string) ($layer['font'] ?? '')),
                default => null,
            };
            if ($error !== null) {
                return $error;
            }
        }

        return [$chosenOptions, array_values($items)];
    }

    /**
     * @param  list<CakeOption|CakeLibraryItem>  $chosen
     */
    private function complexity(array $chosen, int $tiers): string
    {
        $rank = $tiers > 1 ? self::COMPLEXITY_RANK['complex'] : 0;
        foreach ($chosen as $option) {
            $rank = max($rank, self::COMPLEXITY_RANK[$option->complexity] ?? 1);
        }

        return array_search(max($rank, 0), self::COMPLEXITY_RANK, true) ?: 'standard';
    }
}
