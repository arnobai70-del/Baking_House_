<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A retail customer's saved delivery address (home, office, parents' house). Pre-fills checkout;
 * every order still stores its own address snapshot.
 */
#[Fillable(['label', 'recipient_name', 'phone', 'line', 'area', 'district'])]
class CustomerAddress extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
