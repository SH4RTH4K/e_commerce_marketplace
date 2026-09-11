<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductImage;
use Illuminate\Http\Request;
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
}
