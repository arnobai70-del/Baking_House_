<?php

namespace App\Models\Concerns;

use App\Models\CrmActivity;
use App\Models\CrmTag;
use App\Models\CrmTask;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * CRM timeline, follow-up tasks and tags for leads, customers and organizations.
 */
trait HasCrmRecords
{
    /**
     * @return MorphMany<CrmActivity, $this>
     */
    public function crmActivities(): MorphMany
    {
        return $this->morphMany(CrmActivity::class, 'subject')->latest('occurred_at')->latest('id');
    }

    /**
     * @return MorphMany<CrmTask, $this>
     */
    public function crmTasks(): MorphMany
    {
        return $this->morphMany(CrmTask::class, 'subject')->orderByRaw('completed_at is not null')->orderBy('due_at');
    }

    /**
     * @return MorphToMany<CrmTag, $this>
     */
    public function crmTags(): MorphToMany
    {
        return $this->morphToMany(CrmTag::class, 'taggable', 'crm_taggables')->orderBy('name');
    }
}
