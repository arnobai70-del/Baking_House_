<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PricingMode;
use App\Enums\ProductStatus;
use App\Enums\SellingUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\ProductWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private ProductWriter $writer) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = ProductStatus::tryFrom((string) $request->query('status', ''));

        $products = Product::query()
            ->with(['primaryCategory:id,name', 'variants:id,product_id,sku,pricing_mode,price_paisa,per_kg_paisa,stock_quantity,is_active,selling_unit,pack_size,min_grams,low_stock_threshold'])
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';
                $query->where(fn ($match) => $match->whereRaw('lower(name) like ?', [$like])
                    ->orWhereHas('variants', fn ($variant) => $variant->whereRaw('lower(sku) like ?', [$like])));
            })
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.products.index', ['products' => $products, 'search' => $search, 'status' => $status]);
    }

    public function create(): View
    {
        return view('admin.products.form', $this->formData(new Product(['status' => ProductStatus::Draft])));
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = $this->writer->save(
            new Product,
            $this->productAttributes($request),
            $request->validated('category_ids', []),
            $request->variantAttributes(),
            $request->validated('price_change_reason'),
            $request->user(),
        );

        return redirect()->route('admin.products.edit', $product)->with('status', "Product “{$product->name}” created.");
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', $this->formData($product->load(['variants', 'categories:id'])));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->writer->save(
            $product,
            $this->productAttributes($request),
            $request->validated('category_ids', []),
            $request->variantAttributes(),
            $request->validated('price_change_reason'),
            $request->user(),
        );

        return redirect()->route('admin.products.edit', $product)->with('status', 'Changes saved and published.');
    }

    /**
     * @return array<string, mixed>
     */
    private function productAttributes(ProductRequest $request): array
    {
        return [
            ...$request->safe()->only([
                'name', 'name_bn', 'slug', 'primary_category_id', 'brand_id', 'status', 'is_featured',
                'short_description', 'description', 'search_keywords', 'seo_title', 'seo_description',
            ]),
            'specifications' => $request->specifications(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Product $product): array
    {
        return [
            'product' => $product,
            'categories' => Category::query()->with('children')->roots()->orderBy('sort_order')->orderBy('name')->get(),
            'brands' => Brand::query()->orderBy('name')->get(),
            'statuses' => ProductStatus::cases(),
            'pricingModes' => PricingMode::cases(),
            'sellingUnits' => SellingUnit::cases(),
        ];
    }
}
