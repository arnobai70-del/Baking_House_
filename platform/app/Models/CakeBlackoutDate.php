<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A production calendar exception: "closed" (holiday / no production) or "urgent_open" (admin accepts
 * orders on this date even inside the normal lead time, still within the daily capacity).
 */
#[Fillable(['date', 'kind', 'reason', 'created_by'])]
class CakeBlackoutDate extends Model
{
    public const CLOSED = 'closed';

    public const URGENT_OPEN = 'urgent_open';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }
}
