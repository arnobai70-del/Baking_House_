<?php

namespace App\Models;

use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Models\Concerns\HasCrmRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sales lead / opportunity moving through the pipeline. Unassigned leads (assigned_to = null)
 * sit in the shared queue. All attributes are guarded; changes go through App\Services\Crm\CrmService.
 */
class CrmLead extends Model
{
    use HasCrmRecords;

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'stage' => LeadStage::class,
            'estimated_value_paisa' => 'integer',
            'next_follow_up_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function isInSharedQueue(): bool
    {
        return $this->assigned_to === null && ! $this->stage->isClosed();
    }
}
