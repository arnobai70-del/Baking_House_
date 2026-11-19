<?php

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Organization
 */
class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'business_category' => $this->business_category,
            'status' => $this->status->value,
            'member_role' => $this->whenPivotLoaded('organization_user', fn () => $this->pivot->role),
            'approved_at' => $this->approved_at?->toIso8601String(),
        ];
    }
}
