<?php

namespace App\Http\Requests\Admin;

use App\Enums\StaffPermission;
use App\Models\Category;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(StaffPermission::ManageCatalog->value);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug((string) ($this->input('slug') ?: $this->input('name'))),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => (int) $this->input('sort_order', 0),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:120'],
            'name_bn' => ['nullable', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:140', Rule::unique('categories', 'slug')->ignore($category?->id)],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id')],
            'summary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'icon' => ['required', Rule::in(Category::ICONS)],
            'sort_order' => ['integer', 'min:0', 'max:10000'],
            'is_active' => ['boolean'],
            'seo_title' => ['nullable', 'string', 'max:120'],
            'seo_description' => ['nullable', 'string', 'max:320'],
        ];
    }

    /**
     * Two levels only, and a category can never be its own parent.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $category = $this->route('category');
                $parentId = $this->input('parent_id');

                if ($category !== null && $parentId !== null && (int) $parentId === $category->id) {
                    $validator->errors()->add('parent_id', 'A category cannot be its own parent.');
                }
                if ($category !== null && $parentId !== null && $category->children()->exists()) {
                    $validator->errors()->add('parent_id', 'This category has subcategories, so it must stay top-level.');
                }
            },
        ];
    }
}
