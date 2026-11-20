<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationStatus;
use App\Enums\PriceListAudience;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PriceList;
use App\Models\ProductVariant;
use App\Services\AuditLogger;
use App\Support\AdminInput;
use App\Support\Taka;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Retail / campaign / business / reseller / contract price lists with volume tiers.
 * Every change is audited with before/after tiers; edits need a written reason.
 */
class PriceListController extends Controller
{
    public function index(): View
    {
        return view('admin.pricing.lists-index', [
            'lists' => PriceList::query()->with('organization:id,name')->withCount('items')->orderBy('audience')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new PriceList(['is_active' => true, 'audience' => PriceListAudience::Retail]));
    }

    public function edit(PriceList $priceList): View
    {
        return $this->form($priceList->load('items.variant'));
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $list = $this->save($request, new PriceList, $audit);

        return redirect()->route('admin.price-lists.edit', $list)->with('status', "Price list “{$list->name}” created.");
    }

    public function update(Request $request, PriceList $priceList, AuditLogger $audit): RedirectResponse
    {
        $this->save($request, $priceList, $audit);

        return redirect()->route('admin.price-lists.edit', $priceList)->with('status', 'Price list saved. New prices apply immediately.');
    }

    private function form(PriceList $list): View
    {
        return view('admin.pricing.list-form', [
            'list' => $list,
            'audiences' => PriceListAudience::cases(),
            'organizations' => Organization::query()->where('status', OrganizationStatus::Approved->value)->orderBy('name')->get(['id', 'name', 'type']),
        ]);
    }

    private function save(Request $request, PriceList $list, AuditLogger $audit): PriceList
    {
        $isNew = ! $list->exists;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'audience' => ['required', Rule::enum(PriceListAudience::class)],
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')->where('status', OrganizationStatus::Approved->value)],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['array', 'max:500'],
            'items.*.sku' => ['nullable', 'string', 'max:64'],
            'items.*.min_quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.price' => ['nullable', 'string', 'max:20'],
            'reason' => [$isNew ? 'nullable' : 'required', 'nullable', 'string', 'min:5', 'max:1000'],
        ]);

        $audience = PriceListAudience::from($validated['audience']);
        if ($audience === PriceListAudience::Contract && empty($validated['organization_id'])) {
            throw ValidationException::withMessages(['organization_id' => 'A contract list must belong to one approved organization.']);
        }

        $items = $this->parseItems($validated['items'] ?? []);
        $before = $isNew ? [] : ['items' => $this->summary($list)];

        DB::transaction(function () use ($request, $list, $validated, $audience, $items): void {
            $list->fill([
                'name' => $validated['name'],
                'audience' => $audience,
                'organization_id' => $audience === PriceListAudience::Contract ? $validated['organization_id'] : null,
                'is_active' => $request->boolean('is_active'),
                'starts_at' => AdminInput::dhakaToUtc($validated['starts_at'] ?? null),
                'ends_at' => AdminInput::dhakaToUtc($validated['ends_at'] ?? null),
                'notes' => $validated['notes'] ?? null,
            ])->save();

            $list->items()->delete();
            foreach ($items as $item) {
                $list->items()->create($item);
            }
        });

        $audit->record(
            $isNew ? 'price_list.created' : 'price_list.updated',
            $list,
            $before,
            ['audience' => $audience->value, 'active' => $list->is_active, 'items' => $this->summary($list->fresh())],
            $validated['reason'] ?? null,
        );

        return $list;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{product_variant_id: int, min_quantity: int, price_paisa: int|null, per_kg_paisa: int|null}>
     */
    private function parseItems(array $rows): array
    {
        $items = [];
        $errors = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
            if ($sku === '') {
                continue;
            }

            $variant = ProductVariant::query()->where('sku', $sku)->first();
            $price = Taka::parse($row['price'] ?? null);
            $min = (int) ($row['min_quantity'] ?? 1);

            if ($variant === null) {
                $errors["items.{$index}.sku"] = "Unknown SKU {$sku}.";

                continue;
            }
            if ($variant->listPricePaisa() === null) {
                $errors["items.{$index}.sku"] = "{$sku} is quote-only and cannot be on a price list.";

                continue;
            }
            if ($price === null || $price === 0) {
                $errors["items.{$index}.price"] = "Enter a price in taka for {$sku}.";

                continue;
            }
            if (isset($seen[$sku.'|'.$min])) {
                $errors["items.{$index}.min_quantity"] = "{$sku} already has a tier from {$min}.";

                continue;
            }
            $seen[$sku.'|'.$min] = true;

            $items[] = [
                'product_variant_id' => $variant->id,
                'min_quantity' => max(1, $min),
                'price_paisa' => $variant->isWeight() ? null : $price,
                'per_kg_paisa' => $variant->isWeight() ? $price : null,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function summary(PriceList $list): array
    {
        return $list->items()->with('variant:id,sku')->orderBy('product_variant_id')->orderBy('min_quantity')->get()
            ->map(fn ($item) => "{$item->variant?->sku} from {$item->min_quantity}: ".($item->per_kg_paisa ?? $item->price_paisa))
            ->all();
    }
}
