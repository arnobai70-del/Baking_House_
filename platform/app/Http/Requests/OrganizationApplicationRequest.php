<?php

namespace App\Http\Requests;

use App\Enums\OrganizationType;
use App\Support\BangladeshPhone;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['contact_phone' => BangladeshPhone::normalize($this->input('contact_phone')) ?? $this->input('contact_phone')]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(OrganizationType::class)],
            'name' => ['required', 'string', 'max:160'],
            'business_category' => ['nullable', 'string', 'max:64'],
            'trade_licence_no' => ['nullable', 'string', 'max:64'],
            'contact_phone' => [
                'required',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! BangladeshPhone::isValid((string) $value)) {
                        $fail('Enter a valid Bangladesh mobile number (01XXXXXXXXX).');
                    }
                },
            ],
        ];
    }
}
