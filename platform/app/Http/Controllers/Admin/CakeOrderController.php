<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CakeLibraryItem;
use App\Models\CakeOption;
use App\Models\CakeUpload;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\AuditLogger;
use App\Services\Cakes\CakeCanvas;
use App\Services\Cakes\CakeGeometry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Order staff and custom cakes: the customer's private photos (view + approve / reject each edible print)
 * and the printable baker's job sheet with the 3D snapshot, top design and full specification.
 */
class CakeOrderController extends Controller
{
    public function upload(CakeUpload $upload): Response
    {
        abort_unless(Storage::disk('local')->exists($upload->path), 404);

        return Storage::disk('local')->response($upload->path, null, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function review(Request $request, CakeUpload $upload, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in([CakeUpload::APPROVED, CakeUpload::REJECTED])],
            'note' => [Rule::requiredIf($request->input('decision') === CakeUpload::REJECTED), 'nullable', 'string', 'max:255'],
        ], ['note.required' => 'Say why the print is rejected (the team will call the customer).']);

        $before = ['review_status' => $upload->review_status];
        $upload->forceFill([
            'review_status' => $validated['decision'], 'review_note' => $validated['note'] ?? null,
            'reviewed_by' => $request->user()->id, 'reviewed_at' => now(),
        ])->save();
        $audit->record('cake.print_'.$validated['decision'], $upload, $before, ['review_status' => $upload->review_status, 'note' => $upload->review_note]);

        return back()->with('status', $validated['decision'] === CakeUpload::APPROVED ? 'Print approved for production.' : 'Print rejected. Contact the customer for a better photo.');
    }

    public function sheet(Order $order, OrderItem $item, CakeCanvas $canvas): View
    {
        abort_unless($item->order_id === $order->id && $item->isCake(), 404);
        $spec = (array) ($item->components['spec'] ?? []);
        $layers = (array) ($spec['canvas']['layers'] ?? []);
        $uploads = CakeUpload::query()->whereIn('uuid', array_column(array_filter($layers, fn ($l) => ($l['type'] ?? '') === 'image'), 'upload'))->get()->keyBy('uuid');
        $codes = collect($layers)->filter(fn ($l) => ($l['type'] ?? '') === 'item')->map(fn ($l) => $l['kind'].'.'.$l['item'])
            ->merge(array_filter([isset($spec['pattern']) && $spec['pattern'] ? 'pattern.'.$spec['pattern'] : null, ! empty($spec['border_top']) ? 'border.'.$spec['border_top'] : null,
                ! empty($spec['border_bottom']) ? 'border.'.$spec['border_bottom'] : null, ! empty($spec['palette']) ? 'palette.'.$spec['palette'] : null]))->unique();
        $library = CakeLibraryItem::query()->get()->keyBy(fn (CakeLibraryItem $i) => $i->kind.'.'.$i->code);
        $grams = (int) ($spec['grams'] ?? 0);
        $tiers = (int) ($spec['tiers'] ?? 1);

        return view('admin.orders.cake-sheet', [
            'order' => $order->load('pickupLocation'),
            'item' => $item,
            'design' => $item->cakeDesign,
            'spec' => $spec,
            'layers' => $layers,
            'uploads' => $uploads,
            'quality' => collect($canvas->printQuality($layers, $uploads->all(), CakeGeometry::topDiameterCm($grams, $tiers)))->keyBy('upload'),
            'library' => $library,
            'usedItems' => $codes->map(fn ($code) => $library->get($code))->filter()->values(),
            'optionNames' => CakeOption::query()->get(['group', 'code', 'name'])->mapWithKeys(fn (CakeOption $o) => [$o->group.'.'.$o->code => $o->name])->all(),
            'baseCm' => CakeGeometry::baseDiameterCm($grams, $tiers),
            'topCm' => CakeGeometry::topDiameterCm($grams, $tiers),
        ]);
    }
}
