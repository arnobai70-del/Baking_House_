<?php

namespace App\Http\Requests\Api;

use App\Enums\ApiAbility;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Validation\Rule;

/**
 * Same credential rules as web login, plus device name and optional requested abilities.
 */
class IssueTokenRequest extends LoginRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'mfa_code' => ['nullable', 'string', 'max:20'],
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => ['string', 'distinct', Rule::enum(ApiAbility::class)],
        ];
    }
}
