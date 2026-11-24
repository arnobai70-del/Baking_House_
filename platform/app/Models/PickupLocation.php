<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Store pickup point (admin-managed). No location exists until the owner adds one, so pickup is not offered
 * until then.
 */
#[Fillable(['name', 'name_bn', 'address', 'phone', 'hours', 'is_active', 'sort_order'])]
class PickupLocation extends Model
{
    use HasTranslations;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }
}
