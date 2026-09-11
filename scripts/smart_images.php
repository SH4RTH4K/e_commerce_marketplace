<?php

$images = [
    'phone' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=800&q=80',
    'watch' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=800&q=80',
    'audio' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=800&q=80',
    'cosmetics' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?w=800&q=80',
    'shoes' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800&q=80',
    'bag' => 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=800&q=80',
    'laptop' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=800&q=80',
    'fashion' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=800&q=80',
    'home' => 'https://images.unsplash.com/photo-1503602642458-232111445657?w=800&q=80',
    'default' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&q=80',
];

$bannerImages = [
    'https://images.unsplash.com/photo-1472851294608-062f824d29cc?w=1000&q=80',
    'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?w=1000&q=80',
    'https://images.unsplash.com/photo-1607082349566-187342175e2f?w=1000&q=80',
    'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=1000&q=80',
    'https://images.unsplash.com/photo-1555529771-835f59fc5efe?w=1000&q=80'
];

function getKeyword($text) {
    $text = strtolower($text);
    if (str_contains($text, 'phone') || str_contains($text, 'galaxy') || str_contains($text, 'redmi')) return 'phone';
    if (str_contains($text, 'watch') || str_contains($text, 'band')) return 'watch';
    if (str_contains($text, 'earbud') || str_contains($text, 'headphone') || str_contains($text, 'airpod')) return 'audio';
    if (str_contains($text, 'serum') || str_contains($text, 'vitamin') || str_contains($text, 'cream') || str_contains($text, 'beauty')) return 'cosmetics';
    if (str_contains($text, 'shoe') || str_contains($text, 'sneaker') || str_contains($text, 'boot')) return 'shoes';
    if (str_contains($text, 'bag') || str_contains($text, 'backpack')) return 'bag';
    if (str_contains($text, 'laptop') || str_contains($text, 'macbook')) return 'laptop';
    if (str_contains($text, 'shirt') || str_contains($text, 'fashion') || str_contains($text, 'wear')) return 'fashion';
    if (str_contains($text, 'home') || str_contains($text, 'decor') || str_contains($text, 'furniture')) return 'home';
    return 'default';
}

// Banners
foreach(\App\Models\Banner::all() as $i => $b) {
    $b->image = $bannerImages[$i % count($bannerImages)];
    $b->save();
}

// Categories
foreach(\App\Models\Category::all() as $i => $c) {
    $key = getKeyword($c->name);
    $c->image = $images[$key];
    $c->save();
}

// Products
foreach(\App\Models\Product::with('images')->get() as $p) {
    $key = getKeyword($p->name . ' ' . ($p->category ? $p->category->name : ''));
    $imageUrl = $images[$key];
    
    foreach($p->images as $pi) {
        $pi->path = $imageUrl;
        $pi->save();
    }
}

// Landing pages (reset any broken loremflickr)
foreach(\App\Models\LandingPage::all() as $lp) {
    $content = $lp->content;
    $changed = false;
    
    // Check common image fields
    $imageFields = ['hero_image', 'product_thumb'];
    foreach($imageFields as $field) {
        if (isset($content[$field]) && (str_contains($content[$field], 'loremflickr.com') || str_contains($content[$field], 'picsum'))) {
            $content[$field] = $images['default'];
            $changed = true;
        }
    }
    
    if (isset($content['features']) && is_array($content['features'])) {
        foreach($content['features'] as &$feature) {
            if (isset($feature['image']) && (str_contains($feature['image'], 'loremflickr.com') || str_contains($feature['image'], 'picsum'))) {
                $feature['image'] = $images['default'];
                $changed = true;
            }
        }
    }
    if (isset($content['testimonials']) && is_array($content['testimonials'])) {
        foreach($content['testimonials'] as &$test) {
            if (isset($test['image']) && (str_contains($test['image'], 'loremflickr.com') || str_contains($test['image'], 'picsum'))) {
                $test['image'] = 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=400&q=80'; // person
                $changed = true;
            }
        }
    }
    
    if ($changed) {
        $lp->content = $content;
        $lp->save();
    }
}

echo "Smart images updated successfully.\n";

