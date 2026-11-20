<?php

namespace App\Http\Controllers\Portal;

use App\Models\DeliveryZone;
use App\Models\OrganizationAddress;
use App\Services\AuditLogger;
use App\Support\BangladeshPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Saved delivery addresses of the organization (owner and buyers edit; viewers read). They
 * pre-fill checkout; the order still stores its own address snapshot.
 */
class AddressController extends PortalController
{
    public const MAX_ADDRESSES = 20;

    public function __construct(private AuditLogger $audit) {}

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
        $data = $this->validated($request);
        if ($organization->addresses()->count() >= self::MAX_ADDRESSES) {
            throw ValidationException::withMessages(['label' => __('You can save up to :max addresses.', ['max' => self::MAX_ADDRESSES])]);
        }
        $address = $organization->addresses()->make($data);
        $address->is_default = $organization->addresses()->doesntExist() || $request->boolean('is_default');
        $this->saveDefault($address);

        $this->audit->record('organization.address_added', $organization, after: ['label' => $address->label, 'district' => $address->district]);

        return back()->with('status', __('Address saved.'));
    }

    public function update(Request $request, OrganizationAddress $address): RedirectResponse
    {
        $this->requireBuyer($request);
        $this->ownAddress($request, $address);

        $address->fill($this->validated($request));
        $address->is_default = $address->is_default || $request->boolean('is_default');
        $this->saveDefault($address);

        $this->audit->record('organization.address_updated', $this->organization($request), after: ['label' => $address->label, 'district' => $address->district]);

        return back()->with('status', __('Address saved.'));
    }

    public function destroy(Request $request, OrganizationAddress $address): RedirectResponse
    {
        $this->requireBuyer($request);
        $this->ownAddress($request, $address);

        $address->delete();
        $this->audit->record('organization.address_removed', $this->organization($request), ['label' => $address->label]);

        return back()->with('status', __('Address removed.'));
    }

    /**
     * @return array<string, string>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'line' => ['required', 'string', 'max:255'],
            'area' => ['required', 'string', 'max:120'],
            'district' => ['required', Rule::in(DeliveryZone::DISTRICTS)],
        ]);

        $phone = BangladeshPhone::normalize($data['phone']);
        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => __('Enter a valid Bangladesh mobile number (01XXXXXXXXX).')]);
        }
        $data['phone'] = $phone;

        return $data;
    }

    private function saveDefault(OrganizationAddress $address): void
    {
        DB::transaction(function () use ($address): void {
            if ($address->is_default) {
                OrganizationAddress::query()->where('organization_id', $address->organization_id)
                    ->when($address->exists, fn ($query) => $query->whereKeyNot($address->id))
                    ->update(['is_default' => false]);
            }
            $address->save();
        });
    }

    private function ownAddress(Request $request, OrganizationAddress $address): void
    {
        abort_unless($address->organization_id === $this->organization($request)->id, 404);
    }
}
