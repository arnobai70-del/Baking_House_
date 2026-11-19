<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    /**
     * GET /api/v1/admin/organizations?status=pending — admin:read ability + customers.manage permission.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(OrganizationStatus::class)],
        ]);

        $organizations = Organization::query()
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(25);

        return OrganizationResource::collection($organizations);
    }
}
