<?php

namespace App\Http\Requests\Auth;

use App\Support\BangladeshPhone;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => BangladeshPhone::normalize($this->input('phone')) ?? $this->input('phone'),
            'email' => $this->filled('email') ? mb_strtolower(trim((string) $this->input('email'))) : null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! BangladeshPhone::isValid((string) $value)) {
                        $fail('Enter a valid Bangladesh mobile number (01XXXXXXXXX).');
                    }
                },
                'unique:users,phone',
            ],
            'email' => ['nullable', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
