<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrganizationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('decide', $this->route('organization'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(OrganizationStatus::class)->except([OrganizationStatus::Pending])],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
