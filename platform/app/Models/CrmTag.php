<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Free-form staff label for segmentation ("bakery", "high value", "Chattogram").
 */
#[Fillable(['name'])]
class CrmTag extends Model
{
    /**
     * @return MorphToMany<User, $this>
     */
    public function users(): MorphToMany
    {
        return $this->morphedByMany(User::class, 'taggable', 'crm_taggables');
    }

    /**
     * @return MorphToMany<Organization, $this>
     */
    public function organizations(): MorphToMany
    {
        return $this->morphedByMany(Organization::class, 'taggable', 'crm_taggables');
    }
}
