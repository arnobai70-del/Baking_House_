<?php

namespace App\Services\Addresses;

use App\Models\DeliveryZone;
use App\Support\BangladeshPhone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Shared rules for saved delivery addresses (retail customers and organizations): validation,
 * phone normalisation, a per-owner limit, and exactly one default address per owner.
 */
class AddressBook
{
    public const MAX_ADDRESSES = 20;

    /**
     * @return array{label: string, recipient_name: string, phone: string, line: string, area: string, district: string}
     *
     * @throws ValidationException
     */
    public function validated(Request $request): array
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

    /**
     * Add an address to an owner's book; the first one becomes the default.
     *
     * @param  HasMany<covariant Model, covariant Model>  $book
     * @param  array<string, string>  $data
     *
     * @throws ValidationException
     */
    public function add(HasMany $book, array $data, bool $makeDefault): Model
    {
        if ((clone $book)->reorder()->count() >= self::MAX_ADDRESSES) {
            throw ValidationException::withMessages(['label' => __('You can save up to :max addresses.', ['max' => self::MAX_ADDRESSES])]);
        }

        $address = $book->make($data);
        $address->is_default = $makeDefault || (clone $book)->reorder()->doesntExist();
        $this->save($book, $address);

        return $address;
    }

    /**
     * @param  HasMany<covariant Model, covariant Model>  $book
     * @param  array<string, string>  $data
     */
    public function update(HasMany $book, Model $address, array $data, bool $makeDefault): void
    {
        $address->fill($data);
        $address->is_default = $address->is_default || $makeDefault;
        $this->save($book, $address);
    }

    /**
     * Delete an address; if it was the default, the oldest remaining one becomes the default.
     *
     * @param  HasMany<covariant Model, covariant Model>  $book
     */
    public function delete(HasMany $book, Model $address): void
    {
        DB::transaction(function () use ($book, $address): void {
            $wasDefault = $address->is_default;
            $address->delete();
            if ($wasDefault) {
                (clone $book)->reorder()->orderBy('id')->first()?->forceFill(['is_default' => true])->save();
            }
        });
    }

    /**
     * @param  HasMany<covariant Model, covariant Model>  $book
     */
    private function save(HasMany $book, Model $address): void
    {
        DB::transaction(function () use ($book, $address): void {
            if ($address->is_default) {
                (clone $book)->reorder()->when($address->exists, fn ($query) => $query->whereKeyNot($address->getKey()))->update(['is_default' => false]);
            }
            $address->save();
        });
    }
}
