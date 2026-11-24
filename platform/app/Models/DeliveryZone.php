<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Delivery zone: a set of districts with a delivery charge. Charges are placeholders until a
 * courier agreement exists. The default zone covers districts not listed elsewhere.
 */
#[Fillable(['name', 'name_bn', 'districts', 'is_default', 'charge_paisa', 'free_over_paisa', 'estimate', 'cod_available', 'is_active', 'sort_order',
    'cake_delivery_enabled', 'cake_charge_paisa', 'cake_free_over_paisa', 'cake_min_order_paisa', 'cake_tiered_allowed'])]
class DeliveryZone extends Model
{
    use HasTranslations;

    /**
     * Bangladesh's 64 districts (English names as used on addresses).
     */
    public const DISTRICTS = [
        'Bagerhat', 'Bandarban', 'Barguna', 'Barishal', 'Bhola', 'Bogura', 'Brahmanbaria', 'Chandpur', 'Chapai Nawabganj', 'Chattogram',
        'Chuadanga', "Cox's Bazar", 'Cumilla', 'Dhaka', 'Dinajpur', 'Faridpur', 'Feni', 'Gaibandha', 'Gazipur', 'Gopalganj',
        'Habiganj', 'Jamalpur', 'Jashore', 'Jhalokathi', 'Jhenaidah', 'Joypurhat', 'Khagrachhari', 'Khulna', 'Kishoreganj', 'Kurigram',
        'Kushtia', 'Lakshmipur', 'Lalmonirhat', 'Madaripur', 'Magura', 'Manikganj', 'Meherpur', 'Moulvibazar', 'Munshiganj', 'Mymensingh',
        'Naogaon', 'Narail', 'Narayanganj', 'Narsingdi', 'Natore', 'Netrokona', 'Nilphamari', 'Noakhali', 'Pabna', 'Panchagarh',
        'Patuakhali', 'Pirojpur', 'Rajbari', 'Rajshahi', 'Rangamati', 'Rangpur', 'Satkhira', 'Shariatpur', 'Sherpur', 'Sirajganj',
        'Sunamganj', 'Sylhet', 'Tangail', 'Thakurgaon',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'districts' => 'array',
            'is_default' => 'boolean',
            'charge_paisa' => 'integer',
            'free_over_paisa' => 'integer',
            'cake_delivery_enabled' => 'boolean',
            'cake_charge_paisa' => 'integer',
            'cake_free_over_paisa' => 'integer',
            'cake_min_order_paisa' => 'integer',
            'cake_tiered_allowed' => 'boolean',
            'cod_available' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public static function forDistrict(string $district): ?self
    {
        $zones = self::query()->where('is_active', true)->orderBy('sort_order')->get();

        return $zones->first(fn (self $zone) => in_array($district, $zone->districts ?? [], true))
            ?? $zones->firstWhere('is_default', true);
    }

    public function chargeFor(int $subtotalPaisa): int
    {
        return $this->free_over_paisa !== null && $subtotalPaisa >= $this->free_over_paisa ? 0 : $this->charge_paisa;
    }
}
