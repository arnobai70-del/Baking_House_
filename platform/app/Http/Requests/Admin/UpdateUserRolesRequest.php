<?php

namespace App\Http\Requests\Admin;

use App\Enums\StaffPermission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRolesRequest extends FormRequest
{
    /**
     * Role changes are super-admin only, and nobody may change their own roles.
     */
    public function authorize(): bool
    {
        return $this->user()->can(StaffPermission::ManageRoles->value)
            && ! $this->user()->is($this->route('user'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'roles' => ['present', 'array'],
            'roles.*' => ['string', 'distinct', 'exists:roles,name'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
