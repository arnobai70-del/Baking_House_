<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CakeLibraryItem;
use App\Models\CakeOption;
use App\Services\AuditLogger;
use App\Services\Cakes\CakeLibrary;
use App\Support\Taka;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin design library for the cake designer: templates, toppers, decorations, theme sticker packs, border
 * piping, side patterns, fonts and colour palettes. Each item has tags, a price add-on, allowed shapes and an
 * active flag. New items and price changes are TEST until the owner approves them (like cake options).
 */
class CakeLibraryController extends Controller
{
    private const AUDITED = ['kind', 'code', 'name', 'price_paisa', 'is_test', 'is_active', 'allowed_shapes', 'complexity'];

    public function __construct(private CakeLibrary $library, private AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $kind = array_key_exists((string) $request->query('kind'), CakeLibraryItem::KINDS) ? (string) $request->query('kind') : null;
        $q = trim((string) $request->query('q'));

        return view('admin.cakes.library', [
            'items' => CakeLibraryItem::query()
                ->when($kind, fn ($query) => $query->where('kind', $kind))
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('name_bn', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")->orWhere('tags', 'like', "%{$q}%")))
                ->orderBy('kind')->orderBy('sort_order')->orderBy('id')->get(),
            'kind' => $kind,
            'q' => $q,
        ]);
    }

    public function create(Request $request): View
    {
        $kind = (string) $request->query('kind', 'topper');
        abort_unless(array_key_exists($kind, CakeLibraryItem::KINDS), 404);

        return $this->form(new CakeLibraryItem(['kind' => $kind, 'is_active' => true, 'is_test' => true, 'complexity' => 'standard', 'payload' => $this->examplePayload($kind)]));
    }

    public function edit(CakeLibraryItem $item): View
    {
        return $this->form($item);
    }

    public function store(Request $request): RedirectResponse
    {
        $kind = (string) $request->input('kind');
        abort_unless(array_key_exists($kind, CakeLibraryItem::KINDS), 422);
        $item = $this->save($request, new CakeLibraryItem(['kind' => $kind]));

        return redirect()->route('admin.cakes.library')->with('status', "“{$item->name}” added to the library as a TEST price. Approve it before launch.");
    }

    public function update(Request $request, CakeLibraryItem $item): RedirectResponse
    {
        $this->save($request, $item);

        return redirect()->route('admin.cakes.library', ['kind' => $item->kind])->with('status', "“{$item->name}” saved.".($item->is_test ? ' Its price is TEST until approved.' : ''));
    }

    /**
     * Owner / super admin only: approve TEST prices of library items.
     */
    public function approve(Request $request): RedirectResponse
    {
        Gate::authorize('prices.approve');
        $ids = array_map('intval', (array) $request->input('items', []));
        $approved = CakeLibraryItem::query()->where('is_test', true)->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))->get();
        foreach ($approved as $item) {
            $item->forceFill(['is_test' => false])->save();
        }
        $this->audit->record('cake.library_prices_approved', null, [], ['items' => $approved->map(fn (CakeLibraryItem $i) => ['id' => $i->id, 'code' => $i->kind.'.'.$i->code, 'price_paisa' => $i->price_paisa])->all()]);

        return redirect()->route('admin.cakes.library')->with('status', $approved->count().' library price(s) approved.');
    }

    private function form(CakeLibraryItem $item): View
    {
        return view('admin.cakes.library-form', [
            'item' => $item,
            'shapes' => CakeOption::query()->where('group', 'shape')->orderBy('sort_order')->get(['code', 'name'])->unique('code'),
            'payloadJson' => json_encode($item->payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function save(Request $request, CakeLibraryItem $item): CakeLibraryItem
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_-]+$/', Rule::unique('cake_library_items', 'code')->where('kind', $item->kind)->ignore($item->id)],
            'name' => ['required', 'string', 'max:120'],
            'name_bn' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'string', 'max:300'],
            'price' => ['required', 'string', 'max:20'],
            'complexity' => ['required', Rule::in(array_keys(CakeOption::COMPLEXITIES))],
            'allowed_shapes' => ['array'],
            'allowed_shapes.*' => ['string', 'max:40'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'payload' => ['required', 'string', 'max:60000'],
        ]);
        $price = Taka::parse($validated['price']);
        if ($price === null || $price < 0) {
            throw ValidationException::withMessages(['price' => 'Enter the price add-on in taka (0 for free).']);
        }
        $payload = json_decode($validated['payload'], true);
        if (! is_array($payload)) {
            throw ValidationException::withMessages(['payload' => 'The drawing data is not valid JSON.']);
        }
        $payload = $this->library->normalisePayload($item->kind, $payload);

        $before = $item->exists ? $item->only(self::AUDITED) : [];
        $priceChanged = ! $item->exists || $item->price_paisa !== $price;
        $item->fill([
            'code' => $validated['code'], 'name' => $validated['name'], 'name_bn' => $validated['name_bn'] ?? null,
            'tags' => array_values(array_filter(array_map(fn ($t) => mb_strtolower(trim($t)), explode(',', (string) ($validated['tags'] ?? ''))))),
            'price_paisa' => $price, 'complexity' => $validated['complexity'],
            'allowed_shapes' => array_values($validated['allowed_shapes'] ?? []) ?: null,
            'is_active' => $request->boolean('is_active'), 'sort_order' => (int) ($validated['sort_order'] ?? 0), 'payload' => $payload,
        ]);
        if ($priceChanged) {
            $item->is_test = true;
        }
        $item->save();
        $this->library->forget();
        $this->audit->record($before === [] ? 'cake.library_created' : 'cake.library_updated', $item, $before, $item->only(self::AUDITED));

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function examplePayload(string $kind): array
    {
        $star = ['viewBox' => '0 0 100 100', 'paths' => [['d' => 'M50 5 L61 38 L96 38 L68 59 L79 93 L50 72 L21 93 L32 59 L4 38 L39 38 Z', 'fill' => 'accent']]];

        return match ($kind) {
            'topper', 'decoration' => $star,
            'sticker_pack' => ['stickers' => [['name' => 'Star'] + $star]],
            'border' => ['style' => 'bead', 'colour' => 'accent'],
            'pattern' => ['style' => 'dots', 'colour' => 'accent'],
            'font' => ['family' => 'Noto Sans', 'stack' => '"Noto Sans", "Noto Sans Bengali", sans-serif', 'weight' => 700, 'scripts' => ['en', 'bn']],
            'palette' => ['colours' => ['#fffaf3', '#f4c2c2', '#103b56']],
            default => ['layers' => [['type' => 'text', 'text' => 'Happy Birthday', 'x' => 0.5, 'y' => 0.5, 'size' => 0.09, 'fill' => '#103b56']]],
        };
    }
}
