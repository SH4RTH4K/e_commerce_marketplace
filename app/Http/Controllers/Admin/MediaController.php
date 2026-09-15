<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MediaController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductImage::with('product:id,name,slug')
            ->orderByDesc('created_at');

        if ($request->filled('q')) {
            $term = $request->input('q');
            $query->where('alt', 'like', "%{$term}%")
                  ->orWhereHas('product', fn($q) => $q->where('name', 'like', "%{$term}%"));
        }

        return Inertia::render('Admin/Media/Index', [
            'images' => $query->paginate(24)->withQueryString()->through(fn($img) => [
                'id'         => $img->id,
                'path'       => $img->path,
                'alt'        => $img->alt,
                'is_primary' => $img->is_primary,
                'created_at' => $img->created_at?->format('d M Y'),
                'product'    => $img->product ? [
                    'id'   => $img->product->id,
                    'name' => $img->product->name,
                ] : null,
            ]),
            'q'          => $request->input('q'),
            'total'      => ProductImage::count(),
        ]);
    }
    public function store(Request $request)
    {
        $path = \App\Support\PublicUploader::storeFromRequest($request, 'image', 'media', 'jpg');
        if (! $path) {
            return back()->withErrors(['image' => 'Please select an image to upload.']);
        }

        $alt = pathinfo((string) ($request->file('image')?->getClientOriginalName() ?? $request->input('image_name', 'media')), PATHINFO_FILENAME);

        ProductImage::create([
            'path' => $path,
            'alt'  => $alt ?: 'Product image',
        ]);

        return back()->with('status', 'Image uploaded successfully.');
    }

    public function destroy(ProductImage $image)
    {
        if (Banner::where('image', $image->path)->exists()) {
            return back()->withErrors(['image' => 'This media image is being used by a banner. Replace or delete the banner first.']);
        }

        // Delete the file from storage if it's a local path
        if (! str_starts_with((string) $image->path, 'http')) {
            $fullPath = public_path($image->path);
            if (is_file($fullPath)) {
                unlink($fullPath);
            }
        }
        $image->delete();

        return back()->with('status', 'Image deleted.');
    }

    /** Convert one or more selected media images into ordered banner entries. */
    public function createBanners(Request $request)
    {
        $data = $request->validate([
            'image_ids'   => ['required', 'array', 'min:1', 'max:50'],
            'image_ids.*' => ['integer', 'distinct', 'exists:product_images,id'],
            'placement'   => ['required', 'in:' . implode(',', array_keys(Banner::PLACEMENTS))],
            'style'       => ['nullable', 'in:' . implode(',', array_keys(Banner::STYLES))],
            'text_position'  => ['nullable', 'in:top-left,top-center,top-right,center-left,center-center,center-right,bottom-left,bottom-center,bottom-right'],
            'image_position' => ['nullable', 'in:top-left,top-center,top-right,center-left,center-center,center-right,bottom-left,bottom-center,bottom-right'],
            'image_orientation' => ['nullable', 'in:landscape,portrait,square'],
        ]);

        $imagesById = ProductImage::whereIn('id', $data['image_ids'])->get()->keyBy('id');
        $images = collect($data['image_ids'])
            ->map(fn (int $id) => $imagesById->get($id))
            ->filter();

        if ($images->isEmpty()) {
            return back()->withErrors(['image_ids' => 'Select at least one media image.']);
        }

        $style = $data['style'] ?? 'brand';
        $textPosition = $data['text_position'] ?? 'center-left';
        $imagePosition = $data['image_position'] ?? 'center-center';
        $imageOrientation = $data['image_orientation'] ?? 'landscape';
        DB::transaction(function () use ($images, $data, $style, $textPosition, $imagePosition, $imageOrientation) {
            $position = (int) Banner::where('placement', $data['placement'])->max('position') + 1;

            foreach ($images as $image) {
                $title = Str::limit(trim((string) ($image->alt ?: $image->product?->name ?: 'Banner')), 180, '');

                Banner::create([
                    'title'       => $title,
                    'product_id'  => $image->product_id,
                    'image'       => $image->path,
                    'placement'   => $data['placement'],
                    'style'       => $style,
                    'text_position' => $textPosition,
                    'image_position' => $imagePosition,
                    'image_orientation' => $imageOrientation,
                    'position'    => $position++,
                    'is_active'   => true,
                ]);
            }
        });

        $type = $data['placement'] === 'hero' ? 'hero slider' : 'middle banner';

        return redirect()->route('admin.banners.index')
            ->with('status', $images->count() . ' ' . Str::plural($type, $images->count()) . ' created from Media Manager.');
    }
}
