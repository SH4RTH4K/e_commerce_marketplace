<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use App\Models\Product;
use App\Support\LandingPageDesigns;
use App\Support\PublicUploader;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LandingPageController extends Controller
{
    public function index()
    {
        $pages = LandingPage::with('product')->latest()->paginate(20);

        return Inertia::render('Admin/LandingPages/Index', compact('pages'));
    }

    public function create(Request $request)
    {
        $design = $request->query('design');

        if (! $design || ! in_array($design, LandingPageDesigns::keys(), true)) {
            $designs = [];
            foreach (LandingPageDesigns::DESIGNS as $key => $meta) {
                $designs[$key] = array_merge($meta, [
                    'visible'     => LandingPageDesigns::isVisible($key),
                    'preview_url' => LandingPageDesigns::previewUrl($key),
                ]);
            }

            return Inertia::render('Admin/LandingPages/Form', [
                'choosingDesign' => true,
                'designs'        => $designs,
            ]);
        }

        if (! LandingPageDesigns::isVisible($design)) {
            return redirect()
                ->route('admin.landing-pages.create')
                ->withErrors(['design' => 'That design is currently hidden. Turn visibility on to use it.']);
        }

        $product = Product::orderBy('name')->first();
        if (! $product) {
            return redirect()
                ->route('admin.landing-pages.create')
                ->withErrors(['design' => 'Add at least one product before creating a landing page.']);
        }

        $label = LandingPageDesigns::DESIGNS[$design]['label'] ?? ucfirst($design);
        $page = LandingPage::create([
            'title'      => $label.' landing',
            'slug'       => LandingPage::makeSlug($label.'-landing'),
            'design'     => $design,
            'product_id' => $product->id,
            'is_active'  => false,
            'content'    => LandingPageDesigns::defaults($design),
        ]);

        return redirect()
            ->route('admin.landing-pages.edit', $page)
            ->with('status', "Draft created with {$label} design. Save each section below when ready.");
    }

    public function previewDesign(string $design)
    {
        abort_unless(in_array($design, LandingPageDesigns::keys(), true), 404);

        $product = Product::published()->with('images')->orderBy('id')->first()
            ?? Product::with('images')->orderBy('id')->first();

        abort_unless($product, 404, 'Add at least one product before previewing a landing design.');

        $page = new LandingPage([
            'title'      => (LandingPageDesigns::DESIGNS[$design]['label'] ?? 'Preview').' preview',
            'slug'       => 'preview-'.$design,
            'design'     => $design,
            'is_active'  => true,
            'content'    => LandingPageDesigns::defaults($design),
            'product_id' => $product->id,
        ]);
        $page->setRelation('product', $product);

        $view = match ($design) {
            'campaign' => 'landing.campaign',
            'nuraya'   => 'landing.nuraya',
            default    => 'landing.chilora',
        };

        return view($view, [
            'page'            => $page,
            'product'         => $product,
            'c'               => $page->content ?? [],
            'isDesignPreview' => true,
        ]);
    }

    public function toggleDesign(Request $request, string $design)
    {
        abort_unless(in_array($design, LandingPageDesigns::keys(), true), 404);

        $next = ! LandingPageDesigns::isVisible($design);
        LandingPageDesigns::setVisible($design, $next);

        $label = LandingPageDesigns::DESIGNS[$design]['label'] ?? $design;

        return back()->with(
            'status',
            $next
                ? "{$label} design is now visible for new landing pages."
                : "{$label} design is now hidden from new landing pages."
        );
    }

    public function store(Request $request)
    {
        // Drafts are created from create?design= — keep store for safety
        return $this->create($request);
    }

    public function edit(LandingPage $landing_page)
    {
        return Inertia::render('Admin/LandingPages/Form', [
            'page'     => $landing_page,
            'products' => Product::orderBy('name')->get(['id', 'name', 'sku', 'regular_price', 'sale_price']),
            'sections' => LandingPageDesigns::sections($landing_page->design),
            'designs'  => LandingPageDesigns::DESIGNS,
            'c'        => $landing_page->content ?? [],
        ]);
    }

    public function updateSection(Request $request, LandingPage $landing_page, string $section)
    {
        $sections = LandingPageDesigns::sections($landing_page->design);
        abort_unless(isset($sections[$section]), 404);

        $meta = $sections[$section];
        $content = $landing_page->content ?? LandingPageDesigns::defaults($landing_page->design);

        if ($section === 'setup') {
            $data = $request->validate([
                'title'      => ['required', 'string', 'max:160'],
                'slug'       => ['nullable', 'string', 'max:160', 'alpha_dash', Rule::unique('landing_pages', 'slug')->ignore($landing_page->id)],
                'product_id' => ['required', 'exists:products,id'],
                'is_active'  => ['nullable', 'boolean'],
                'meta_title' => ['nullable', 'string', 'max:180'],
                'meta_description' => ['nullable', 'string', 'max:500'],
            ]);
            $title = trim($data['title']);
            $slug = trim((string) ($data['slug'] ?? ''));
            $landing_page->update([
                'title'      => $title,
                'slug'       => $slug !== '' ? Str::slug($slug) : LandingPage::makeSlug($title, $landing_page->id),
                'product_id' => $data['product_id'],
                'is_active'  => $request->boolean('is_active'),
            ]);
            $content['meta_title'] = trim((string) ($data['meta_title'] ?? ''));
            $content['meta_description'] = trim((string) ($data['meta_description'] ?? ''));
        } else {
            if (! empty($meta['toggle'])) {
                $content['_sections'] = $content['_sections'] ?? LandingPageDesigns::defaultSectionVisibility($landing_page->design);
                $content['_sections'][$section] = $this->sectionVisibleFromRequest($request);
            }

            foreach ($meta['fields'] as $field) {
                $this->applyField($request, $landing_page, $content, $field);
            }
        }

        $landing_page->update(['content' => $content]);

        $message = ($meta['label'] ?? $section).' saved.';

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            $landing_page->refresh();
            $fresh = $landing_page->content ?? [];

            return response()->json([
                'message' => $message,
                'section' => $section,
                'images'  => $this->sectionImagePayload($landing_page, $meta, $fresh),
            ]);
        }

        return back()
            ->with('status', $message)
            ->with('saved_section', $section);
    }

    public function update(Request $request, LandingPage $landing_page)
    {
        return $this->updateSection($request, $landing_page, 'setup');
    }

    public function toggle(Request $request, LandingPage $landing_page)
    {
        $landing_page->update(['is_active' => ! $landing_page->is_active]);

        $message = $landing_page->is_active
            ? 'Landing page is now visible.'
            : 'Landing page is now hidden.';

        if ($request->expectsJson()) {
            return response()->json([
                'message'   => $message,
                'is_active' => $landing_page->is_active,
            ]);
        }

        return back()->with('status', $message);
    }

    public function destroy(LandingPage $landing_page)
    {
        $title = $landing_page->title;
        $landing_page->delete();

        return redirect()->route('admin.landing-pages.index')->with('status', "Landing page “{$title}” deleted.");
    }

    /** Checkbox may be missing (hidden) or duplicated with a legacy hidden input — never treat arrays as false. */
    private function sectionVisibleFromRequest(Request $request): bool
    {
        $value = $request->input('section_visible');
        if (is_array($value)) {
            $value = end($value);
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array{fields: array<string, array{url: string, path: string}>, testimonials: array<int, array{url: string, path: string}>, catalog: array<int, array{url: string, path: string}>} */
    private function sectionImagePayload(LandingPage $page, array $meta, array $content): array
    {
        $fields = [];
        foreach ($meta['fields'] ?? [] as $field) {
            if (($field['type'] ?? '') !== 'image') {
                continue;
            }
            $name = $field['name'];
            $path = (string) ($content[$name] ?? '');
            $fields[$name] = [
                'path' => $path,
                'url'  => $path ? $page->mediaUrl($path) : '',
            ];
        }

        $mapRows = function (array $rows) use ($page): array {
            $out = [];
            foreach ($rows as $i => $row) {
                $path = (string) ($row['image'] ?? '');
                $out[(int) $i] = [
                    'path' => $path,
                    'url'  => $path ? $page->mediaUrl($path) : '',
                ];
            }

            return $out;
        };

        return [
            'fields'       => $fields,
            'testimonials' => $mapRows($content['testimonials'] ?? []),
            'catalog'      => $mapRows($content['catalog'] ?? []),
        ];
    }

    private function applyField(Request $request, LandingPage $page, array &$content, array $field): void
    {
        $name = $field['name'];
        $type = $field['type'];

        if (str_starts_with($type, 'page_')) {
            return;
        }

        if ($type === 'image') {
            if ($request->boolean('remove_'.$name)) {
                $this->deleteUpload($content[$name] ?? null);
                $content[$name] = '';
                return;
            }

            $path = null;
            if ($request->hasFile($name)) {
                $path = PublicUploader::storeFromRequest($request, $name, 'landing/'.$page->id, 'jpg');
            } elseif ($request->hasFile($name.'_file')) {
                $path = PublicUploader::storeFromRequest($request, $name.'_file', 'landing/'.$page->id, 'jpg');
            } elseif ($request->filled($name.'_b64')) {
                $path = PublicUploader::storeFromRequest($request, $name, 'landing/'.$page->id, 'jpg');
            }

            if ($path) {
                $this->deleteUpload($content[$name] ?? null);
                $content[$name] = $path;
            } elseif ($request->filled($name.'_url')) {
                $content[$name] = trim((string) $request->input($name.'_url'));
            } elseif ($request->has($name) && is_string($request->input($name))) {
                $val = trim((string) $request->input($name));
                if (str_starts_with($val, 'data:image/')) {
                    $decoded = PublicUploader::decodeBase64Payload($val, 'upload.jpg', $name);
                    if ($decoded) {
                        $saved = PublicUploader::storeBytes($decoded[0], 'landing/'.$page->id, $decoded[1] ?: 'jpg', $name);
                        $this->deleteUpload($content[$name] ?? null);
                        $content[$name] = $saved;
                    }
                } elseif ($val !== '') {
                    $content[$name] = $val;
                }
            }

            return;
        }

        if ($type === 'number') {
            if ($request->has($name)) {
                $content[$name] = (float) $request->input($name);
            }

            return;
        }

        if ($type === 'packages_list') {
            $rows = $request->input('package_options', []);
            $out = [];
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $pName = trim((string) ($row['name'] ?? ''));
                    $price = (float) ($row['price'] ?? 0);
                    $qty = (int) ($row['qty'] ?? 1);
                    $badge = trim((string) ($row['badge'] ?? ''));
                    if ($pName === '' && $price <= 0) {
                        continue;
                    }
                    $out[] = [
                        'name'  => $pName,
                        'price' => $price,
                        'qty'   => max(1, $qty),
                        'badge' => $badge,
                    ];
                }
            }
            $content['package_options'] = $out;

            return;
        }

        if ($type === 'features_list') {
            $rows = $request->input('features', []);
            $existing = $content['features'] ?? [];
            $out = [];
            if (is_array($rows)) {
                foreach ($rows as $i => $row) {
                    $title = trim((string) ($row['title'] ?? ''));
                    $body = trim((string) ($row['body'] ?? ''));
                    $image = $existing[$i]['image'] ?? ($row['image'] ?? '');
                    if ($title === '' && $body === '' && empty($image)) {
                        continue;
                    }
                    if ($request->hasFile("features.$i.image_file")) {
                        $path = PublicUploader::storeFromRequest($request, "features.$i.image_file", 'landing/'.$page->id, 'jpg');
                        if ($path) {
                            $image = $path;
                        }
                    } elseif (! empty($row['image'])) {
                        $val = trim((string) $row['image']);
                        if (str_starts_with($val, 'data:image/')) {
                            $decoded = PublicUploader::decodeBase64Payload($val, "feature_{$i}.jpg", "features.{$i}.image");
                            if ($decoded) {
                                $image = PublicUploader::storeBytes($decoded[0], 'landing/'.$page->id, $decoded[1] ?: 'jpg', "features.{$i}.image");
                            }
                        } else {
                            $image = $val;
                        }
                    }
                    $out[] = ['title' => $title, 'body' => $body, 'image' => $image];
                }
            }
            $content['features'] = $out;

            return;
        }

        if ($type === 'testimonials_list') {
            $rows = $request->input('testimonials', []);
            $existing = $content['testimonials'] ?? [];
            $out = [];
            if (is_array($rows)) {
                foreach ($rows as $i => $row) {
                    $nameVal = trim((string) ($row['name'] ?? ''));
                    $textVal = trim((string) ($row['text'] ?? ''));
                    $rating = (int) ($row['rating'] ?? 5);
                    $image = $existing[$i]['image'] ?? ($row['image'] ?? '');
                    if ($nameVal === '' && $textVal === '') {
                        continue;
                    }
                    if ($request->hasFile("testimonials.$i.image_file")) {
                        $path = PublicUploader::storeFromRequest($request, "testimonials.$i.image_file", 'landing/'.$page->id, 'jpg');
                        if ($path) {
                            $image = $path;
                        }
                    } elseif (! empty($row['image'])) {
                        $val = trim((string) $row['image']);
                        if (str_starts_with($val, 'data:image/')) {
                            $decoded = PublicUploader::decodeBase64Payload($val, "testi_{$i}.jpg", "testimonials.{$i}.image");
                            if ($decoded) {
                                $image = PublicUploader::storeBytes($decoded[0], 'landing/'.$page->id, $decoded[1] ?: 'jpg', "testimonials.{$i}.image");
                            }
                        } else {
                            $image = $val;
                        }
                    }
                    $out[] = ['name' => $nameVal, 'text' => $textVal, 'image' => $image, 'rating' => max(1, min(5, $rating))];
                }
            }
            $content['testimonials'] = $out;

            return;
        }

        if ($type === 'benefits_icon_list') {
            $rows = $request->input('benefits', []);
            $out = [];
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $title = trim((string) ($row['title'] ?? ''));
                    $body = trim((string) ($row['body'] ?? ''));
                    $icon = trim((string) ($row['icon'] ?? 'trending-up')) ?: 'trending-up';
                    if ($title === '' && $body === '') {
                        continue;
                    }
                    $out[] = compact('icon', 'title', 'body');
                }
            }
            $content['benefits'] = $out;

            return;
        }

        if ($type === 'benefits_items_list') {
            $rows = $request->input('benefits', []);
            $out = [];
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $title = trim((string) ($row['title'] ?? ''));
                    $items = trim((string) ($row['items'] ?? ''));
                    if ($title === '' && $items === '') {
                        continue;
                    }
                    $out[] = ['title' => $title, 'items' => $items];
                }
            }
            $content['benefits'] = $out;

            return;
        }

        if ($type === 'catalog_list') {
            $rows = $request->input('catalog', []);
            $existing = $content['catalog'] ?? [];
            $out = [];
            if (is_array($rows)) {
                foreach ($rows as $i => $row) {
                    $nameVal = trim((string) ($row['name'] ?? ''));
                    $price = trim((string) ($row['price_label'] ?? ''));
                    if ($nameVal === '' && $price === '') {
                        continue;
                    }
                    $image = $existing[$i]['image'] ?? ($row['image'] ?? '');
                    if ($request->hasFile("catalog.$i.image_file")) {
                        $path = PublicUploader::storeFromRequest($request, "catalog.$i.image_file", 'landing/'.$page->id, 'jpg');
                        if ($path) {
                            $image = $path;
                        }
                    } elseif (! empty($row['image'])) {
                        $val = trim((string) $row['image']);
                        if (str_starts_with($val, 'data:image/')) {
                            $decoded = PublicUploader::decodeBase64Payload($val, "catalog_{$i}.jpg", "catalog.{$i}.image");
                            if ($decoded) {
                                $image = PublicUploader::storeBytes($decoded[0], 'landing/'.$page->id, $decoded[1] ?: 'jpg', "catalog.{$i}.image");
                            }
                        } else {
                            $image = $val;
                        }
                    }
                    $out[] = ['name' => $nameVal, 'price_label' => $price, 'image' => $image];
                }
            }
            $content['catalog'] = $out;

            return;
        }

        if ($request->has($name)) {
            $content[$name] = trim((string) $request->input($name));
        }
    }

    private function deleteUpload(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/')) {
            PublicUploader::delete($path);
        }
    }
}
