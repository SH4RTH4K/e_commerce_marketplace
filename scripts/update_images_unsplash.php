<?php

$productImages = [
    'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&q=80',
    'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=800&q=80',
    'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=800&q=80',
    'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800&q=80',
    'https://images.unsplash.com/photo-1503602642458-232111445657?w=800&q=80',
    'https://images.unsplash.com/photo-1572635196237-14b3f281503f?w=800&q=80',
    'https://images.unsplash.com/photo-1560393464-5c69a73c5770?w=800&q=80',
    'https://images.unsplash.com/photo-1546868871-7041f2a55e12?w=800&q=80',
    'https://images.unsplash.com/photo-1583394838336-acd977736f90?w=800&q=80',
    'https://images.unsplash.com/photo-1611186871348-b1ce696e52c9?w=800&q=80'
];

$bannerImages = [
    'https://images.unsplash.com/photo-1472851294608-062f824d29cc?w=1000&q=80',
    'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?w=1000&q=80',
    'https://images.unsplash.com/photo-1607082349566-187342175e2f?w=1000&q=80',
    'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=1000&q=80',
    'https://images.unsplash.com/photo-1555529771-835f59fc5efe?w=1000&q=80'
];

$categoryImages = [
    'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=400&q=80',
    'https://images.unsplash.com/photo-1534452203293-494d7ddbf7e0?w=400&q=80',
    'https://images.unsplash.com/photo-1607082350899-7e105aa886ae?w=400&q=80',
    'https://images.unsplash.com/photo-1542838132-92c53300491e?w=400&q=80',
    'https://images.unsplash.com/photo-1472851294608-062f824d29cc?w=400&q=80'
];

// Banners
foreach(\App\Models\Banner::all() as $i => $b) {
    if ($b->image && str_contains($b->image, 'loremflickr.com')) {
        $b->image = $bannerImages[$i % count($bannerImages)];
        $b->save();
    }
}

// Categories
foreach(\App\Models\Category::all() as $i => $c) {
    if ($c->image && str_contains($c->image, 'loremflickr.com')) {
        $c->image = $categoryImages[$i % count($categoryImages)];
        $c->save();
    }
}

// Products
foreach(\App\Models\ProductImage::all() as $i => $pi) {
    if ($pi->path && str_contains($pi->path, 'loremflickr.com')) {
        $pi->path = $productImages[$i % count($productImages)];
        $pi->save();
    }
}

// Landing pages
foreach(\App\Models\LandingPage::all() as $lp) {
    $content = $lp->content;
    $changed = false;
    
    // Check common image fields
    $imageFields = ['hero_image', 'product_thumb'];
    foreach($imageFields as $field) {
        if (isset($content[$field]) && str_contains($content[$field], 'loremflickr.com')) {
            $content[$field] = $productImages[array_rand($productImages)];
            $changed = true;
        }
    }
    
    // Check nested arrays like features, testimonials
    if (isset($content['features']) && is_array($content['features'])) {
        foreach($content['features'] as &$feature) {
            if (isset($feature['image']) && str_contains($feature['image'], 'loremflickr.com')) {
                $feature['image'] = $productImages[array_rand($productImages)];
                $changed = true;
            }
        }
    }
    if (isset($content['testimonials']) && is_array($content['testimonials'])) {
        foreach($content['testimonials'] as &$test) {
            if (isset($test['image']) && str_contains($test['image'], 'loremflickr.com')) {
                $test['image'] = $categoryImages[array_rand($categoryImages)];
                $changed = true;
            }
        }
    }
    
    if ($changed) {
        $lp->content = $content;
        $lp->save();
    }
}

echo "Images updated to static Unsplash images successfully.\n";

