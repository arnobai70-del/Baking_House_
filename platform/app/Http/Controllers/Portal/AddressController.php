<?php

namespace App\Http\Controllers\Portal;

use App\Models\DeliveryZone;
use App\Models\OrganizationAddress;
use App\Services\Addresses\AddressBook;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Saved delivery addresses of the organization (owner and buyers edit; viewers read). They
 * pre-fill checkout; the order still stores its own address snapshot. Rules live in AddressBook.
 */
class AddressController extends PortalController
{
    public function __construct(private AuditLogger $audit, private AddressBook $book) {}

    public function index(Request $request): View
    {
        return $this->page($request, 'portal.addresses', 'addresses', [
            'addresses' => $this->organization($request)->addresses()->get(),
            'districts' => DeliveryZone::DISTRICTS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireBuyer($request);
        $organization = $this->organization($request);

        $address = $this->book->add($organization->addresses(), $this->book->validated($request), $request->boolean('is_default'));

        $this->audit->record('organization.address_added', $organization, after: ['label' => $address->label, 'district' => $address->district]);

        return back()->with('status', __('Address saved.'));
    }

    public function update(Request $request, OrganizationAddress $address): RedirectResponse
    {
        $this->requireBuyer($request);
        $this->ownAddress($request, $address);

        $this->book->update($this->organization($request)->addresses(), $address, $this->book->validated($request), $request->boolean('is_default'));

        $this->audit->record('organization.address_updated', $this->organization($request), after: ['label' => $address->label, 'district' => $address->district]);

        return back()->with('status', __('Address saved.'));
    }

    public function destroy(Request $request, OrganizationAddress $address): RedirectResponse
    {
        $this->requireBuyer($request);
        $this->ownAddress($request, $address);

        $this->book->delete($this->organization($request)->addresses(), $address);
        $this->audit->record('organization.address_removed', $this->organization($request), ['label' => $address->label]);

        return back()->with('status', __('Address removed.'));
    }

    private function ownAddress(Request $request, OrganizationAddress $address): void
    {
        abort_unless($address->organization_id === $this->organization($request)->id, 404);
    }
}
