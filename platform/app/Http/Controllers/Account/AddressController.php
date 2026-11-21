<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\DeliveryZone;
use App\Services\Addresses\AddressBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A customer's own saved delivery addresses (/account/addresses). Only the owner can see or
 * change them; another customer's address id is simply not found.
 */
class AddressController extends Controller
{
    public function __construct(private AddressBook $book) {}

    public function index(Request $request): View
    {
        return view('account.addresses', [
            'addresses' => $request->user()->addresses()->get(),
            'districts' => DeliveryZone::DISTRICTS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->book->add($request->user()->addresses(), $this->book->validated($request), $request->boolean('is_default'));

        return back()->with('status', __('Address saved.'));
    }

    public function update(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->own($request, $address);
        $this->book->update($request->user()->addresses(), $address, $this->book->validated($request), $request->boolean('is_default'));

        return back()->with('status', __('Address saved.'));
    }

    public function destroy(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->own($request, $address);
        $this->book->delete($request->user()->addresses(), $address);

        return back()->with('status', __('Address removed.'));
    }

    private function own(Request $request, CustomerAddress $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 404);
    }
}
