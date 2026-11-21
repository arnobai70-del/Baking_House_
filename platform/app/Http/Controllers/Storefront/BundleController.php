<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Services\Pricing\PricingContextResolver;
use App\Services\Promotions\BundlePricer;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /combos and /combo/{slug}: bundles the current buyer may purchase, priced on the server.
 */
class BundleController extends Controller
{
    public function __construct(private BundlePricer $pricer, private PricingContextResolver $contexts) {}

    public function index(Request $request): View
    {
        $context = $this->contexts->forWeb($request);
        $bundles = Bundle::query()->effectiveAt($context->at)->with('items.variant.product')->orderBy('name')->get()
            ->filter(fn (Bundle $bundle) => $bundle->allowsAudience($context->audience))
            ->map(fn (Bundle $bundle) => ['bundle' => $bundle, 'quote' => $this->pricer->quote($bundle, 1, $context)]);

        $seo = new Seo(title: __('Combo & bundle offers'), canonical: route('bundles.index'));

        return view('storefront.bundles.index', ['seo' => $seo, 'bundles' => $bundles]);
    }

    public function show(Request $request, string $slug): View
    {
        $context = $this->contexts->forWeb($request);
        $bundle = Bundle::query()->effectiveAt($context->at)->where('slug', $slug)->with('items.variant.product')->firstOrFail();
        abort_unless($bundle->allowsAudience($context->audience), 404);

        $seo = new Seo(title: (string) $bundle->localized('name'), description: $bundle->localized('description'), canonical: $bundle->url());
        $seo->breadcrumbs([
            ['name' => __('Home'), 'url' => route('home')],
            ['name' => __('Combo & bundle offers'), 'url' => route('bundles.index')],
            ['name' => $bundle->name, 'url' => $bundle->url()],
        ]);

        return view('storefront.bundles.show', ['seo' => $seo, 'bundle' => $bundle, 'quote' => $this->pricer->quote($bundle, 1, $context)]);
    }
}
