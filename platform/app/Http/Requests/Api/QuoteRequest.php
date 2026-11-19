<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Basket to price. Only identities and quantities are accepted — any price fields sent by the
 * client are ignored by design.
 */
class QuoteRequest extends FormRequest
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
            'items' => ['array', 'max:100'],
            'items.*.sku' => ['required', 'string', 'max:64'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
            'bundles' => ['array', 'max:20'],
            'bundles.*.slug' => ['required', 'string', 'max:220'],
            'bundles.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'coupon' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if (empty($this->input('items')) && empty($this->input('bundles'))) {
                    $validator->errors()->add('items', 'Add at least one item or bundle.');
                }
            },
        ];
    }
}
