<?php

namespace App\Http\Requests\Auth;

use App\Support\BangladeshPhone;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Credentials for Auth::attempt — identifier is an email or a BD mobile number.
     *
     * @return array<string, string|null>
     */
    public function credentials(): array
    {
        $identifier = trim((string) $this->input('identifier'));

        $field = BangladeshPhone::looksLikePhone($identifier)
            ? ['phone' => BangladeshPhone::normalize($identifier) ?? $identifier]
            : ['email' => mb_strtolower($identifier)];

        return $field + ['password' => (string) $this->input('password')];
    }
}
