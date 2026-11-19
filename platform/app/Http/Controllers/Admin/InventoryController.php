<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InventoryMovementType;
use App\Enums\ReservationStatus;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Stock overview, per-variant ledger, manual movements, physical-count reconciliation and the
 * ledger integrity check. All writes go through InventoryService.
 */
class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $lowOnly = $request->boolean('low');

        $variants = ProductVariant::query()
            ->with('product:id,name')
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';
                $query->where(fn ($match) => $match->whereRaw('lower(sku) like ?', [$like])
                    ->orWhereHas('product', fn ($product) => $product->whereRaw('lower(name) like ?', [$like])));
            })
            ->when($lowOnly, fn ($query) => $query->where('low_stock_threshold', '>', 0)->whereRaw('stock_quantity - reserved_quantity <= low_stock_threshold'))
            ->orderBy('sku')
            ->paginate(50)
            ->withQueryString();

        return view('admin.inventory.index', [
            'variants' => $variants,
            'search' => $search,
            'lowOnly' => $lowOnly,
            'lowCount' => ProductVariant::query()->where('low_stock_threshold', '>', 0)->whereRaw('stock_quantity - reserved_quantity <= low_stock_threshold')->count(),
        ]);
    }

    public function show(ProductVariant $variant): View
    {
        return view('admin.inventory.show', [
            'variant' => $variant->load('product:id,name'),
            'movements' => $variant->movements()->with('actor:id,name')->latest('id')->paginate(30),
            'reservations' => $variant->reservations()->where('status', ReservationStatus::Active->value)->orderBy('expires_at')->get(),
            'types' => InventoryMovementType::manual(),
        ]);
    }

    public function storeMovement(Request $request, ProductVariant $variant): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_map(fn ($type) => $type->value, InventoryMovementType::manual()))],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $delta = $validated['direction'] === 'in' ? (int) $validated['quantity'] : -(int) $validated['quantity'];

        try {
            $this->inventory->record($variant, InventoryMovementType::from($validated['type']), $delta, $validated['reason'], null, $request->user());
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()." Available: {$e->available}."])->withInput();
        }

        return back()->with('status', 'Stock movement recorded.');
    }

    public function reconcile(Request $request, ProductVariant $variant): RedirectResponse
    {
        $validated = $request->validate([
            'counted_quantity' => ['required', 'integer', 'min:0', 'max:100000000'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $movement = $this->inventory->reconcile($variant, (int) $validated['counted_quantity'], $validated['reason'], $request->user());
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['counted_quantity' => $e->getMessage()])->withInput();
        }

        return back()->with('status', "Count recorded (difference {$movement->quantity}).");
    }

    public function verify(): View
    {
        return view('admin.inventory.verify', ['problems' => $this->inventory->discrepancies()]);
    }
}
