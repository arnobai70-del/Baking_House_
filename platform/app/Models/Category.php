<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['parent_id', 'name', 'name_bn', 'slug', 'summary', 'description', 'icon', 'sort_order', 'is_active', 'seo_title', 'seo_description'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, SoftDeletes;

    /**
     * UI icons a category may use (simple stroke icons — not product photography).
     */
    public const ICONS = ['whisk', 'wheat', 'cake', 'wok', 'oven', 'box', 'pallet', 'gift', 'mixer', 'tray', 'bottle', 'bowl', 'turntable', 'store'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * @param  Builder<Category>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Category>  $query
     */
    #[Scope]
    protected function roots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * Active top-level categories with their active children for the header and mega-menu.
     * Memoised per request (Laravel refuses to unserialize cached models by default).
     *
     * @return Collection<int, Category>
     */
    public static function navigation(): Collection
    {
        return once(fn () => self::query()
            ->active()
            ->roots()
            ->with(['children' => fn ($query) => $query->active()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get());
    }

    /**
     * This category plus all descendant ids (two levels is the supported depth).
     *
     * @return list<int>
     */
    public function selfAndDescendantIds(): array
    {
        $childIds = $this->children()->pluck('id')->all();
        $grandchildIds = $childIds === [] ? [] : self::query()->whereIn('parent_id', $childIds)->pluck('id')->all();

        return array_values(array_unique([$this->id, ...$childIds, ...$grandchildIds]));
    }

    /**
     * Breadcrumb trail from the root down to this category.
     *
     * @return list<Category>
     */
    public function trail(): array
    {
        $trail = [$this];
        $current = $this;

        while ($current->parent_id !== null && count($trail) < 5) {
            $current = $current->parent()->first();
            if ($current === null) {
                break;
            }
            array_unshift($trail, $current);
        }

        return $trail;
    }

    public function url(): string
    {
        return route('catalog.category', $this->slug);
    }
}
