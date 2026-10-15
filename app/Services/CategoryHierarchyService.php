<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CategoryHierarchyService
{
    /** Preorder options with stable paths; tolerate legacy orphaned/cyclic data. */
    public function options(?Collection $categories = null): array
    {
        $categories ??= Category::orderBy('menu_order')->orderBy('name')->get();
        $byId = $categories->keyBy('id');
        $options = [];
        foreach ($categories as $category) {
            $path = [];
            $seen = [];
            $current = $category;
            $valid = true;
            while ($current) {
                if (isset($seen[$current->id])) {
                    $valid = false;
                    break;
                }
                $seen[$current->id] = true;
                array_unshift($path, $current);
                if ($current->parent_id && ! $byId->has($current->parent_id)) {
                    $valid = false;
                }
                $current = $current->parent_id ? $byId->get($current->parent_id) : null;
            }
            $options[$category->id] = array_merge($category->toArray(), [
                'label' => implode(' / ', array_map(fn ($item) => $item->name, $path)),
                'depth' => count($path),
                'path_ids' => array_map(fn ($item) => (int) $item->id, $path),
                'hierarchy_valid' => $valid,
            ]);
        }
        $ordered = [];
        $visited = [];
        $children = $categories->groupBy(fn ($category) => (int) $category->parent_id);
        $walk = function ($parentId) use (&$walk, &$ordered, &$visited, $children, $options) {
            foreach ($children->get((int) $parentId, collect()) as $category) {
                if (isset($visited[$category->id])) {
                    continue;
                }
                $visited[$category->id] = true;
                $ordered[] = $options[$category->id];
                $walk($category->id);
            }
        };
        $walk(null);
        foreach ($options as $id => $option) {
            if (! isset($visited[$id])) {
                $ordered[] = $option;
            }
        }

        return $ordered;
    }

    public function validateParent(?int $parentId, ?Category $category): void
    {
        $options = collect($this->options())->keyBy('id');
        $parent = $parentId ? $options->get($parentId) : null;
        if ($parentId && (! $parent || ! $parent['hierarchy_valid'])) {
            throw ValidationException::withMessages(['parent_id' => 'Choose a category with a valid parent path.']);
        }
        if ($category && $parent && in_array((int) $category->id, $parent['path_ids'], true)) {
            throw ValidationException::withMessages(['parent_id' => 'A category cannot be its own parent or move inside its descendants.']);
        }
        $height = 1;
        if ($category) {
            foreach ($options as $option) {
                $index = array_search((int) $category->id, $option['path_ids'], true);
                if ($index !== false) {
                    $height = max($height, count($option['path_ids']) - $index);
                }
            }
        }
        if (($parent['depth'] ?? 0) + $height > 3) {
            throw ValidationException::withMessages(['parent_id' => 'Use at most three levels: Category, Subcategory, and Sub-subcategory. Moving this branch would exceed that limit.']);
        }
    }

    public function path(Category $category): array
    {
        $options = collect($this->options())->keyBy('id');
        $option = $options->get($category->id);
        if (! $option || ! $option['hierarchy_valid']) {
            return [];
        }

        return array_map(fn ($id) => $options->get($id), $option['path_ids']);
    }

    /** Two queries regardless of tree size: categories and direct product counts. */
    public function storefrontTree(bool $withCounts = false): array
    {
        $categories = Category::where('is_active', true)->orderBy('menu_order')->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id', 'icon', 'image', 'show_in_menu']);
        $children = $categories->groupBy(fn ($category) => (int) $category->parent_id);
        $counts = $withCounts ? Product::published()->selectRaw('category_id, COUNT(*) AS total')->groupBy('category_id')->pluck('total', 'category_id') : collect();
        $count = function ($id, $seen = []) use (&$count, $children, $counts): int {
            if (in_array($id, $seen, true)) {
                return 0;
            }
            $seen[] = $id;
            $total = (int) $counts->get($id, 0);
            foreach ($children->get($id, collect()) as $child) {
                $total += $count((int) $child->id, $seen);
            }

            return $total;
        };
        $branch = function ($parentId, $seen = []) use (&$branch, $children, $withCounts, $count): array {
            $result = [];
            foreach ($children->get($parentId, collect()) as $category) {
                if (! $category->show_in_menu || in_array((int) $category->id, $seen, true)) {
                    continue;
                }
                $payload = $category->only(['id', 'name', 'slug', 'parent_id', 'icon', 'image']);
                $payload['children'] = $branch((int) $category->id, [...$seen, (int) $category->id]);
                if ($withCounts) {
                    $payload['products_count'] = $count((int) $category->id);
                }
                $result[] = $payload;
            }

            return $result;
        };

        return $branch(0);
    }
}
