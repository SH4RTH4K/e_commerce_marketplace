<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\CategoryHierarchyService;
use App\Services\StorefrontPageCache;
use App\Support\PublicUploader;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Categories/Index', [
            'categories' => app(CategoryHierarchyService::class)->options(Category::with('parent')->withCount('products')->orderBy('menu_order')->orderBy('name')->get()),
        ]);
    }

    public function ordering()
    {
        return Inertia::render('Admin/Categories/Ordering', [
            'categories' => app(CategoryHierarchyService::class)->options(),
        ]);
    }

    public function saveOrdering(Request $request)
    {
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*.id' => ['required', 'integer', 'exists:categories,id'],
            'order.*.menu_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($data['order'] as $item) {
            Category::where('id', $item['id'])->update([
                'menu_order' => $item['menu_order'],
                'position' => $item['menu_order'],
            ]);
        }
        app(StorefrontPageCache::class)->invalidate();

        return redirect()->route('admin.categories.index')->with('status', 'Category ordering updated.');
    }

    public function create(Request $request)
    {
        $parents = $this->parentOptions();
        $parentId = collect($parents)->firstWhere('id', (int) $request->input('parent_id'))['id'] ?? null;
        return Inertia::render('Admin/Categories/Form', [
            'category' => new Category(['is_active' => true, 'parent_id' => $parentId]),
            'parents'  => $parents,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data = $this->sanitizeCategoryData($data);
        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? '') ?: $data['name']);
        $data['is_active'] = $request->boolean('is_active');
        $data['show_in_menu'] = $request->boolean('show_in_menu', true);
        $this->handleImage($request, $data);

        Category::create($data);

        return redirect()->route('admin.categories.index')->with('status', 'Category created.');
    }

    public function edit(Category $category)
    {
        return Inertia::render('Admin/Categories/Form', [
            'category' => $category,
            'parents'  => $this->parentOptions($category),
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validateData($request, $category);
        $data = $this->sanitizeCategoryData($data);
        $data['slug'] = $this->uniqueSlug(($data['slug'] ?? '') ?: $data['name'], $category->id);
        $data['is_active'] = $request->boolean('is_active');
        $data['show_in_menu'] = $request->boolean('show_in_menu', true);
        $this->handleImage($request, $data, $category);

        $category->update($data);

        return redirect()->route('admin.categories.index')->with('status', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        $this->ensureEmpty([$category->id]);
        $this->deleteStoredImage($category->image);
        $category->delete();

        return redirect()->route('admin.categories.index')->with('status', 'Category deleted.');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids'         => ['required', 'array', 'min:1', 'max:100'],
            'ids.*'       => ['integer', 'distinct', 'exists:categories,id'],
            'bulk_action' => ['required', 'in:activate,deactivate,delete'],
        ]);

        $ids = $data['ids'];
        Cache::forget('sitemap_urls_v2');
        $count = count($ids);

        if ($data['bulk_action'] === 'activate') {
            Category::whereIn('id', $ids)->update(['is_active' => true]);
            app(StorefrontPageCache::class)->invalidate();

            return back()->with('status', "{$count} categor" . ($count === 1 ? 'y' : 'ies') . ' set to Active.');
        }

        if ($data['bulk_action'] === 'deactivate') {
            Category::whereIn('id', $ids)->update(['is_active' => false]);
            app(StorefrontPageCache::class)->invalidate();

            return back()->with('status', "{$count} categor" . ($count === 1 ? 'y' : 'ies') . ' set to Hidden.');
        }

        $this->ensureEmpty($ids);
        $categories = Category::whereIn('id', $ids)->get();
        foreach ($categories as $category) {
            $this->deleteStoredImage($category->image);
            $category->delete();
        }

        return back()->with('status', "{$count} categor" . ($count === 1 ? 'y' : 'ies') . ' deleted.');
    }

    private function validateData(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name'             => ['required', 'string', 'max:120'],
            'slug'             => ['nullable', 'string', 'max:120'],
            'parent_id'        => ['nullable', 'integer', 'exists:categories,id'],
            'icon'             => ['nullable', 'string', 'max:16'],
            'description'      => ['nullable', 'string', 'max:500'],
            'position'         => ['nullable', 'integer', 'min:0'],
            'meta_title'       => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords'    => ['nullable', 'string', 'max:500'],
            'image_file'       => ['nullable', 'file', 'extensions:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);
        app(CategoryHierarchyService::class)->validateParent(! empty($data['parent_id']) ? (int) $data['parent_id'] : null, $category);
        return $data;
    }

    private function ensureEmpty(array $ids): void
    {
        if (Category::whereIn('id', $ids)->where(fn ($query) => $query->has('products')->orHas('children'))->exists()) {
            throw ValidationException::withMessages(['category' => 'Move the products and child categories before deleting this category.']);
        }
    }

    private function handleImage(Request $request, array &$data, ?Category $category = null): void
    {
        unset($data['image_file']);
        $path = PublicUploader::storeFromRequest($request, 'image_file', 'categories', 'jpg');
        if (! $path) {
            return;
        }

        if ($category?->image) {
            $this->deleteStoredImage($category->image);
        }
        $data['image'] = $path;
    }

    private function deleteStoredImage(?string $path): void
    {
        if (! $path || str_starts_with($path, 'http')) {
            return;
        }

        $relative = ltrim($path, '/');
        if (str_starts_with($relative, 'uploads/')) {
            PublicUploader::delete($relative);

            return;
        }

        Storage::disk('public')->delete($relative);
    }

    private function sanitizeCategoryData(array $data): array
    {
        $data['name'] = trim((string) ($data['name'] ?? ''));
        $data['parent_id'] = (! empty($data['parent_id']) && is_numeric($data['parent_id']))
            ? (int) $data['parent_id']
            : null;
        $data['position'] = (isset($data['position']) && trim((string) $data['position']) !== '')
            ? max(0, (int) $data['position'])
            : 0;
        // Position is the public admin priority (10, 20, 30...). Keep the
        // legacy column synchronized so every storefront query uses the same order.
        $data['menu_order'] = $data['position'];

        foreach (['icon', 'description', 'meta_title', 'meta_description', 'meta_keywords'] as $field) {
            if (isset($data[$field])) {
                $trimmed = trim((string) $data[$field]);
                $data[$field] = $trimmed !== '' ? $trimmed : null;
            }
        }

        return $data;
    }

    private function parentOptions(?Category $exclude = null)
    {
        $excludedIds = $exclude
            ? $exclude->selfAndDescendantIds()
            : [];

        $options = collect(app(CategoryHierarchyService::class)->options());
        $height = 1;
        if ($exclude) foreach ($options as $option) {
            $index = array_search((int) $exclude->id, $option['path_ids'], true);
            if ($index !== false) $height = max($height, count($option['path_ids']) - $index);
        }
        return $options
            ->filter(fn ($option) => ! in_array($option['id'], $excludedIds) && $option['depth'] + $height <= 3 && $option['hierarchy_valid'])
            ->values();
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: Str::random(8);
        $base = Str::limit($base, 100, '');
        $slug = $base;
        $i = 2;
        while (Category::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
